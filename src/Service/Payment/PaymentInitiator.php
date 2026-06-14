<?php

namespace App\Service\Payment;

use App\Entity\Payment;
use App\Entity\StudentFee;
use App\Entity\User;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Orchestration de l'initiation d'un paiement en ligne (espace parent → GeniusPay).
 *
 * Responsabilités : validation métier (montant, frais soldé), protection contre les
 * doubles paiements, création du Payment « en attente », appel à la passerelle et
 * récupération de l'URL de checkout. L'imputation au solde se fait au retour du
 * webhook (voir WebhookProcessor), jamais ici.
 */
class PaymentInitiator
{
    public const PROVIDER = 'geniuspay';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PaymentRepository $paymentRepository,
        private readonly GeniusPayClient $client,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $paymentLogger,
        private readonly OnlineCashRegisterProvider $onlineCashRegisterProvider,
        private readonly GeniusPayCredentialsProvider $credentialsProvider,
        private readonly string $webhookUrlOverride = '',
    ) {
    }

    /**
     * @throws PaymentGatewayException si la passerelle est indisponible/refuse
     * @throws \InvalidArgumentException si le montant est invalide
     */
    public function initiate(StudentFee $studentFee, float $amount, User $parent): Payment
    {
        $remaining = $studentFee->getRemainingAmount();

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Le montant doit être supérieur à 0.');
        }
        if ($remaining <= 0) {
            throw new \InvalidArgumentException('Ce frais est déjà entièrement réglé.');
        }
        if ($amount > $remaining + 0.009) {
            throw new \InvalidArgumentException(sprintf(
                'Le montant ne peut pas dépasser le reste dû (%s F CFA).',
                number_format($remaining, 0, ',', ' ')
            ));
        }

        // Anti double-paiement : s'il existe déjà un paiement en ligne en attente
        // avec une URL de checkout, on reprend celui-ci au lieu d'en créer un autre.
        $existing = $this->paymentRepository->findActiveOnlineForStudentFee((int) $studentFee->getId());
        if ($existing && $existing->getCheckoutUrl()) {
            $this->paymentLogger->info('Reprise d\'un paiement en ligne en attente', [
                'payment' => $existing->getId(),
                'studentFee' => $studentFee->getId(),
            ]);

            return $existing;
        }

        $student = $studentFee->getStudent();
        $fee = $studentFee->getFee();

        $payment = new Payment();
        $payment->setPaymentNumber('PP-' . date('Ymd') . '-' . str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT));
        $payment->setStudent($student);
        $payment->setFee($fee);
        $payment->setStudentFee($studentFee);
        $payment->setAmount((string) number_format($amount, 2, '.', ''));
        $payment->setPaymentDate(new \DateTime());
        $payment->setPaymentMethod('mobile_money');
        $payment->setStatus('en_attente');
        $payment->setProvider(self::PROVIDER);
        $payment->setIdempotencyKey(bin2hex(random_bytes(16)));
        $payment->setRecordedBy($parent);
        $payment->setNotes(sprintf('Paiement en ligne initié depuis l\'espace parent (%s).', (string) $parent->getEmail()));

        // Rattachement à la caisse « en ligne » de l'établissement (créée au 1er paiement).
        if ($student?->getSchool()) {
            $payment->setCashRegister($this->onlineCashRegisterProvider->getForSchool($student->getSchool()));
        }

        // Persisté d'abord pour réserver le numéro et obtenir un id (référence stable).
        $this->em->persist($payment);
        $this->em->flush();

        $returnUrl = $this->urlGenerator->generate(
            'parent_payment_return',
            ['id' => $payment->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        // En local, l'URL générée pointerait sur 127.0.0.1 (injoignable par la passerelle) :
        // on privilégie l'URL publique du tunnel si elle est configurée.
        $callbackUrl = $this->webhookUrlOverride !== ''
            ? rtrim($this->webhookUrlOverride, '/')
            : $this->urlGenerator->generate('geniuspay_webhook', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $result = $this->client->createPayment(
            amount: $amount,
            reference: (string) $payment->getPaymentNumber(),
            description: sprintf('%s — %s', $fee?->getName() ?? 'Scolarité', $student?->getFullName() ?? ''),
            returnUrl: $returnUrl,
            callbackUrl: $callbackUrl,
            customer: array_filter([
                'name' => $student?->getParentName() ?: $parent->getFullName(),
                'email' => $parent->getEmail(),
                'phone' => $student?->getParentPhone(),
            ]),
            credentials: $this->credentialsProvider->forSchool($student?->getSchool()),
        );

        $payment->setProviderTransactionId($result->transactionId);
        $payment->setCheckoutUrl($result->checkoutUrl);
        $payment->setProviderStatus($result->status);
        $this->em->flush();

        $this->paymentLogger->info('Paiement en ligne initié', [
            'payment' => $payment->getId(),
            'transactionId' => $result->transactionId,
        ]);

        return $payment;
    }
}
