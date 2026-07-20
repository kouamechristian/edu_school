<?php

namespace App\Service\GeniusPay;

use App\Entity\CashRegister;
use App\Entity\OnlinePayment;
use App\Entity\Payment;
use App\Entity\School;
use App\Entity\Student;
use App\Entity\StudentFee;
use App\Entity\User;
use App\Repository\CashRegisterRepository;
use App\Repository\OnlinePaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Paiement en ligne de la scolarité via GeniusPay.
 *
 * Deux temps :
 *  1. `initiate()` — on fige ce qui est attendu (élève, frais, montant) dans un
 *     OnlinePayment, puis on demande à GeniusPay une URL de checkout.
 *  2. `confirm()`  — déclenché par le webhook ET par le retour du parent. On
 *     recoupe ce que dit la passerelle avec ce qu'on avait figé, puis on crée
 *     le Payment comptable.
 *
 * Règles non négociables, parce qu'il s'agit d'argent :
 *  - Le montant crédité est TOUJOURS celui figé à l'initiation, jamais celui
 *    annoncé par un appel entrant.
 *  - `confirm()` est idempotent : webhook rejoué ou retour rechargé ne peuvent
 *    pas créer deux encaissements (unicité de la référence + verrou pessimiste).
 *  - Un écart de montant n'est jamais « rattrapé » silencieusement : on refuse
 *    et on journalise pour traitement manuel.
 */
class GeniusPayService
{
    /** Plancher imposé par GeniusPay (XOF). */
    public const MIN_AMOUNT = 200;

    /** Tolérance d'arrondi sur la comparaison des montants. */
    private const AMOUNT_EPSILON = 0.01;

    public function __construct(
        private readonly GeniusPayClient $client,
        private readonly GeniusPayConfigResolver $configResolver,
        private readonly OnlinePaymentRepository $onlinePayments,
        private readonly CashRegisterRepository $cashRegisters,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isAvailableFor(?School $school): bool
    {
        return $this->configResolver->isConfigured($school);
    }

    /**
     * Prépare un paiement et renvoie l'URL vers laquelle rediriger le parent.
     *
     * @throws GeniusPayException si la passerelle refuse ou est injoignable
     */
    public function initiate(
        Student $student,
        StudentFee $studentFee,
        float $amount,
        ?User $initiatedBy,
        string $successUrl,
        string $errorUrl,
    ): OnlinePayment {
        $school = $student->getSchool();
        $config = $this->configResolver->resolve($school);

        if ($config === null || $school === null) {
            throw new GeniusPayException("Le paiement en ligne n'est pas disponible pour cet établissement.");
        }

        if ($amount < self::MIN_AMOUNT) {
            throw new GeniusPayException(sprintf('Le montant minimum d\'un paiement en ligne est de %s F CFA.', number_format(self::MIN_AMOUNT, 0, ',', ' ')));
        }

        // Garde-fou : on ne laisse pas payer plus que le reste dû, même si le
        // formulaire a été manipulé côté navigateur.
        $remaining = $studentFee->getRemainingAmount();
        if ($amount > $remaining + self::AMOUNT_EPSILON) {
            throw new GeniusPayException('Le montant dépasse le reste dû pour ce frais.');
        }

        // GeniusPay travaille en XOF, sans décimale.
        $amountXof = (int) round($amount);

        $data = $this->client->createPayment($config, [
            'amount' => $amountXof,
            'currency' => 'XOF',
            // Pas de `payment_method` : le parent choisit son moyen de paiement
            // sur la page de checkout GeniusPay (approche recommandée).
            'description' => $this->buildDescription($student, $studentFee),
            'customer' => array_filter([
                'name' => $initiatedBy?->getFullName() ?? $student->getFullName(),
                'email' => $initiatedBy?->getEmail(),
                'phone' => $initiatedBy?->getPhone(),
            ]),
            'success_url' => $successUrl,
            'error_url' => $errorUrl,
            // Confort de rapprochement côté tableau de bord GeniusPay. Ces
            // données ne servent JAMAIS à imputer le paiement à la réception :
            // seule notre ligne OnlinePayment fait foi.
            'metadata' => [
                'student_id' => $student->getId(),
                'student_fee_id' => $studentFee->getId(),
                'school_id' => $school->getId(),
                'matricule' => $student->getMatriculeInterne(),
            ],
        ]);

        $reference = (string) ($data['reference'] ?? '');
        if ($reference === '') {
            throw new GeniusPayException('La passerelle n\'a pas renvoyé de référence de transaction.');
        }

        // La doc décrit `checkout_url` (mode checkout) et `payment_url` (mode
        // direct) ; on accepte les deux, dans cet ordre de préférence.
        $redirectUrl = (string) ($data['checkout_url'] ?? $data['payment_url'] ?? '');
        if ($redirectUrl === '') {
            throw new GeniusPayException('La passerelle n\'a pas renvoyé d\'URL de paiement.');
        }

        $onlinePayment = new OnlinePayment();
        $onlinePayment
            ->setReference($reference)
            ->setSchool($school)
            ->setStudent($student)
            ->setStudentFee($studentFee)
            ->setInitiatedBy($initiatedBy)
            ->setAmount(number_format($amountXof, 2, '.', ''))
            ->setEnvironment($config->environment())
            ->setCheckoutUrl($redirectUrl)
            ->setStatus(OnlinePayment::STATUS_PENDING);

        $this->em->persist($onlinePayment);
        $this->em->flush();

        $this->logger->info('GeniusPay : paiement initié', [
            'reference' => $reference,
            'student' => $student->getId(),
            'amount' => $amountXof,
            'environment' => $config->environment(),
        ]);

        return $onlinePayment;
    }

    /**
     * Confirme (ou rejette) une transaction à partir de son état réel chez GeniusPay.
     *
     * Idempotent : un second appel sur une référence déjà soldée ne fait rien.
     *
     * @param array<string, mixed>|null $payload état déjà connu (webhook vérifié) ;
     *                                           si null, on interroge la passerelle
     *
     * @return bool true si l'encaissement vient d'être enregistré
     */
    public function confirm(string $reference, ?array $payload = null, string $source = 'webhook'): bool
    {
        $this->em->beginTransaction();

        try {
            $onlinePayment = $this->onlinePayments->findOneByReferenceForUpdate($reference);

            if ($onlinePayment === null) {
                // Référence inconnue : transaction d'un autre système, ou tentative
                // de forger un encaissement. On ne crée jamais rien à l'aveugle.
                $this->em->rollback();
                $this->logger->warning('GeniusPay : référence inconnue', ['reference' => $reference, 'source' => $source]);

                return false;
            }

            if ($onlinePayment->isCompleted()) {
                $this->em->rollback();

                return false; // déjà encaissé : rien à faire
            }

            // Sans charge utile de confiance, on relit l'état chez la passerelle.
            if ($payload === null) {
                $config = $this->configResolver->resolve($onlinePayment->getSchool());
                if ($config === null) {
                    $this->em->rollback();

                    return false;
                }
                $payload = $this->client->getPayment($config, $reference);
            }

            $onlinePayment->setLastPayload(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: null);

            $status = (string) ($payload['status'] ?? '');

            if ($status !== 'completed') {
                $onlinePayment->setStatus(match ($status) {
                    'failed' => OnlinePayment::STATUS_FAILED,
                    'cancelled' => OnlinePayment::STATUS_CANCELLED,
                    default => OnlinePayment::STATUS_PENDING,
                });
                $this->em->flush();
                $this->em->commit();

                return false;
            }

            // Le montant encaissé doit correspondre à ce qui a été demandé. En cas
            // d'écart, on refuse et on laisse un humain trancher : créditer un
            // montant inattendu serait pire que ne rien faire.
            $expected = (float) $onlinePayment->getAmount();
            $received = (float) ($payload['amount'] ?? 0);

            if (abs($expected - $received) > self::AMOUNT_EPSILON) {
                $onlinePayment
                    ->setStatus(OnlinePayment::STATUS_FAILED)
                    ->setFailureReason(sprintf('Écart de montant : attendu %.2f, reçu %.2f', $expected, $received));
                $this->em->flush();
                $this->em->commit();

                $this->logger->critical('GeniusPay : écart de montant, encaissement refusé', [
                    'reference' => $reference,
                    'expected' => $expected,
                    'received' => $received,
                ]);

                return false;
            }

            $payment = $this->createPaymentFor($onlinePayment, $expected);

            $onlinePayment
                ->setStatus(OnlinePayment::STATUS_COMPLETED)
                ->setPayment($payment)
                ->setCompletedAt(new \DateTime());

            $this->em->flush();
            $this->em->commit();

            $this->logger->info('GeniusPay : encaissement enregistré', [
                'reference' => $reference,
                'payment' => $payment->getId(),
                'amount' => $expected,
                'source' => $source,
            ]);

            return true;
        } catch (\Throwable $e) {
            if ($this->em->getConnection()->isTransactionActive()) {
                $this->em->rollback();
            }

            $this->logger->error('GeniusPay : échec de confirmation', [
                'reference' => $reference,
                'source' => $source,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Crée le Payment comptable et impute le montant sur le frais de l'élève.
     *
     * Le paiement est rattaché à la caisse « en ligne » de l'établissement : cet
     * argent est chez GeniusPay, pas dans le tiroir d'un caissier. Il ne doit donc
     * jamais gonfler le solde d'une caisse physique (ni pouvoir en être versé).
     */
    private function createPaymentFor(OnlinePayment $onlinePayment, float $amount): Payment
    {
        $student = $onlinePayment->getStudent();
        $studentFee = $onlinePayment->getStudentFee();
        $formatted = number_format($amount, 2, '.', '');

        $payment = new Payment();
        $payment
            ->setPaymentNumber($this->generatePaymentNumber())
            ->setStudent($student)
            ->setFee($studentFee?->getFee())
            ->setStudentFee($studentFee)
            ->setAmount($formatted)
            ->setPaymentDate(new \DateTime())
            ->setPaymentMethod('mobile_money')
            // « payé » déclenche l'écriture comptable via AccountingSubscriber.
            ->setStatus('payé')
            ->setReference($onlinePayment->getReference())
            ->setCashRegister($this->resolveOnlineCashRegister($onlinePayment->getSchool()))
            ->setNotes(sprintf(
                'Paiement en ligne GeniusPay (%s) — référence %s',
                $onlinePayment->getEnvironment(),
                $onlinePayment->getReference()
            ));

        // `recordedBy` reste vide : aucun agent n'a encaissé, c'est la passerelle.

        if ($studentFee !== null) {
            $studentFee->setPaidAmount(number_format(
                (float) $studentFee->getPaidAmount() + $amount,
                2,
                '.',
                ''
            ));
        }

        $this->em->persist($payment);
        $this->em->flush();

        return $payment;
    }

    /**
     * Caisse « en ligne » de l'établissement, créée à la volée si elle n'existe pas.
     */
    private function resolveOnlineCashRegister(School $school): CashRegister
    {
        $register = $this->cashRegisters->findOnlineForSchool($school);

        if ($register !== null) {
            return $register;
        }

        $register = new CashRegister();
        $register
            ->setSchool($school)
            ->setIsOnline(true)
            // Pas de caissier : les encaissements y tombent automatiquement.
            ->setCashier(null)
            ->setOpeningBalance('0.00')
            ->setStatus('open');

        $this->em->persist($register);
        $this->em->flush();

        $this->logger->info('GeniusPay : caisse en ligne créée', ['school' => $school->getId()]);

        return $register;
    }

    private function generatePaymentNumber(): string
    {
        return 'PAYONL-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    private function buildDescription(Student $student, StudentFee $studentFee): string
    {
        $description = sprintf(
            '%s — %s',
            $studentFee->getFee()?->getName() ?? 'Frais de scolarité',
            $student->getFullName()
        );

        // GeniusPay limite la description à 500 caractères.
        return mb_substr($description, 0, 500);
    }
}
