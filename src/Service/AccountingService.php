<?php

namespace App\Service;

use App\Entity\AccountingAccount;
use App\Entity\AccountingEntry;
use App\Entity\AccountingPeriodClosure;
use App\Entity\CashDeposit;
use App\Entity\Depense;
use App\Entity\Fee;
use App\Entity\Payment;
use App\Entity\School;
use App\Entity\User;
use App\Repository\AccountingAccountRepository;
use App\Repository\AccountingEntryRepository;
use App\Repository\AccountingPeriodClosureRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Cœur du module de comptabilité (livre de caisse enrichi).
 *
 * Alimente le journal comptable automatiquement à partir des mouvements existants
 * (paiements encaissés, dépenses, versements) de façon idempotente : chaque objet
 * source possède au plus une écriture, retrouvée via sourceType + sourceId. Les
 * méthodes ne « flush » pas : l'appelant (souscripteur, commande, contrôleur)
 * décide du moment du flush.
 */
class AccountingService
{
    /** Plan comptable par défaut (code => [libellé, type]). */
    private const DEFAULT_ACCOUNTS = [
        'REC-SCOLARITE' => ['Frais de scolarité', AccountingAccount::TYPE_RECETTE],
        'REC-INSCRIPTION' => ["Frais d'inscription", AccountingAccount::TYPE_RECETTE],
        'REC-AUTRE' => ['Autres recettes', AccountingAccount::TYPE_RECETTE],
        'DEP-SALAIRE' => ['Salaires', AccountingAccount::TYPE_DEPENSE],
        'DEP-LOYER' => ['Loyer', AccountingAccount::TYPE_DEPENSE],
        'DEP-FOURNITURES' => ['Fournitures', AccountingAccount::TYPE_DEPENSE],
        'DEP-SERVICES' => ['Services', AccountingAccount::TYPE_DEPENSE],
        'DEP-MAINTENANCE' => ['Maintenance', AccountingAccount::TYPE_DEPENSE],
        'DEP-AUTRE' => ['Autres dépenses', AccountingAccount::TYPE_DEPENSE],
    ];

    /** @var array<string, int> Dernier numéro utilisé par préfixe (évite les collisions avant flush). */
    private array $referenceCounters = [];

    public function __construct(
        private EntityManagerInterface $em,
        private AccountingAccountRepository $accountRepository,
        private AccountingEntryRepository $entryRepository,
        private AccountingPeriodClosureRepository $closureRepository,
    ) {
    }

    // ───────────────────────────── Clôtures ─────────────────────────────

    public function latestClosure(School $school): ?AccountingPeriodClosure
    {
        return $this->closureRepository->findLatest((int) $school->getId());
    }

    /**
     * Une date est verrouillée si elle est antérieure ou égale à la dernière
     * clôture de l'établissement (période déjà arrêtée).
     */
    public function isDateLocked(School $school, \DateTimeInterface $date): bool
    {
        $latest = $this->latestClosure($school);

        return $latest !== null && $date <= $latest->getEndDate();
    }

    /**
     * Clôture la période allant du lendemain de la dernière clôture (ou de l'origine)
     * jusqu'à $endDate incluse, en figeant un instantané des totaux. Ne flush pas.
     */
    public function closePeriod(School $school, \DateTimeInterface $endDate, User $actor, ?string $notes = null): AccountingPeriodClosure
    {
        $schoolId = (int) $school->getId();
        $previous = $this->closureRepository->findLatest($schoolId);

        $startDate = null;
        if ($previous !== null && $previous->getEndDate() !== null) {
            $startDate = (clone $previous->getEndDate())->modify('+1 day');
        }

        $totals = $this->entryRepository->totalsByType($schoolId, $startDate, $endDate);
        $count = $this->entryRepository->countInRange($schoolId, $startDate, $endDate);

        $closure = (new AccountingPeriodClosure())
            ->setSchool($school)
            ->setStartDate($startDate)
            ->setEndDate($endDate)
            ->setLabel($this->buildClosureLabel($startDate, $endDate))
            ->setTotalRecette($this->money($totals['recette']))
            ->setTotalDepense($this->money($totals['depense']))
            ->setTotalVersement($this->money($totals['versement']))
            ->setNetResult($this->money($totals['recette'] - $totals['depense']))
            ->setCashBalance($this->money($totals['recette'] - $totals['depense'] - $totals['versement']))
            ->setEntryCount($count)
            ->setClosedBy($actor)
            ->setNotes($notes);

        $this->em->persist($closure);

        return $closure;
    }

    private function buildClosureLabel(?\DateTimeInterface $start, \DateTimeInterface $end): string
    {
        return $start !== null
            ? sprintf('Clôture du %s au %s', $start->format('d/m/Y'), $end->format('d/m/Y'))
            : sprintf('Clôture jusqu\'au %s', $end->format('d/m/Y'));
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    // ───────────────────────────── Plan comptable ─────────────────────────────

    /**
     * Crée le plan comptable par défaut d'un établissement s'il n'en a aucun.
     * Ne flush pas.
     *
     * @return int Nombre de comptes créés.
     */
    public function ensureDefaultAccounts(School $school): int
    {
        $created = 0;
        foreach (self::DEFAULT_ACCOUNTS as $code => [$name, $type]) {
            if ($this->accountRepository->findOneByCode($school, $code) === null) {
                $this->createAccount($school, $code, $name, $type, true);
                $created++;
            }
        }

        return $created;
    }

    public function getOrCreateAccount(School $school, string $code, string $name, string $type): AccountingAccount
    {
        $account = $this->accountRepository->findOneByCode($school, $code);
        if ($account === null) {
            $account = $this->createAccount($school, $code, $name, $type, true);
        }

        return $account;
    }

    /**
     * Compte de recette dédié à un frais (ventilation par frais). Chaque frais
     * alimente son propre poste de recette, ce qui détaille le compte de résultat.
     * Les paiements sans frais rattaché retombent sur « Autres recettes ».
     */
    public function resolvePaymentAccount(School $school, ?Fee $fee): AccountingAccount
    {
        if ($fee === null) {
            return $this->getOrCreateAccount($school, 'REC-AUTRE', 'Autres recettes', AccountingAccount::TYPE_RECETTE);
        }

        $suffix = $fee->getCode() ?: ('FRS-' . $fee->getId());
        $code = 'REC-' . $suffix;
        $name = $fee->getName() ?: ('Frais ' . $fee->getId());

        return $this->getOrCreateAccount($school, $code, $name, AccountingAccount::TYPE_RECETTE);
    }

    private function createAccount(School $school, string $code, string $name, string $type, bool $isSystem): AccountingAccount
    {
        $account = (new AccountingAccount())
            ->setSchool($school)
            ->setCode($code)
            ->setName($name)
            ->setType($type)
            ->setIsSystem($isSystem);

        $this->em->persist($account);

        return $account;
    }

    // ─────────────────────────── Synchronisation ───────────────────────────

    /**
     * Écriture de recette pour un paiement encaissé (statut « payé »).
     * Retire l'écriture si le paiement est annulé / non encaissé.
     */
    public function syncPayment(Payment $payment, ?User $actor = null): void
    {
        $school = $payment->getStudent()?->getSchool();
        if ($school === null || $payment->getId() === null) {
            return;
        }

        $qualifies = $payment->getStatus() === 'payé';
        $existing = $this->entryRepository->findOneBySource(AccountingEntry::SOURCE_PAYMENT, $payment->getId());

        if (!$qualifies) {
            if ($existing !== null) {
                $this->em->remove($existing);
            }
            return;
        }

        $entry = $existing ?? $this->newEntry($school, AccountingEntry::SOURCE_PAYMENT, $payment->getId(), $actor);
        $entry
            ->setType(AccountingEntry::TYPE_RECETTE)
            ->setAccount($this->resolvePaymentAccount($school, $payment->getFee()))
            ->setAmount($payment->getAmount())
            ->setEntryDate($payment->getPaymentDate() ?? new \DateTime())
            ->setPaymentMethod($payment->getPaymentMethod())
            ->setLabel(sprintf(
                'Paiement %s — %s — %s',
                $payment->getPaymentNumber() ?? '',
                $payment->getStudent()?->getFullName() ?? '',
                $payment->getFee()?->getName() ?? ''
            ));

        $this->persistIfNew($entry, $existing);
    }

    /**
     * Écriture de dépense pour une dépense confirmée.
     */
    public function syncDepense(Depense $depense, ?User $actor = null): void
    {
        $school = $depense->getSchool() ?? $depense->getCashRegister()?->getSchool();
        if ($school === null || $depense->getId() === null) {
            return;
        }

        $qualifies = $depense->getStatus() === 'confirmée';
        $existing = $this->entryRepository->findOneBySource(AccountingEntry::SOURCE_DEPENSE, $depense->getId());

        if (!$qualifies) {
            if ($existing !== null) {
                $this->em->remove($existing);
            }
            return;
        }

        $category = $depense->getCategory() ?? 'autre';
        $code = 'DEP-' . strtoupper($category);
        $label = self::DEFAULT_ACCOUNTS[$code][0] ?? ($depense->getCategoryLabel() ?: 'Autres dépenses');

        $entry = $existing ?? $this->newEntry($school, AccountingEntry::SOURCE_DEPENSE, $depense->getId(), $actor);
        $entry
            ->setType(AccountingEntry::TYPE_DEPENSE)
            ->setAccount($this->getOrCreateAccount($school, $code, $label, AccountingAccount::TYPE_DEPENSE))
            ->setAmount($depense->getAmount())
            ->setEntryDate($depense->getDepenseDate() ?? new \DateTime())
            ->setPaymentMethod($depense->getPaymentMethod())
            ->setLabel(sprintf('Dépense %s — %s', $depense->getNumero() ?? '', $depense->getLibelle() ?? ''));

        $this->persistIfNew($entry, $existing);
    }

    /**
     * Écriture de versement bancaire (sortie de trésorerie de la caisse) pour un
     * versement non rejeté.
     */
    public function syncDeposit(CashDeposit $deposit, ?User $actor = null): void
    {
        $school = $deposit->getCashRegister()?->getSchool();
        if ($school === null || $deposit->getId() === null) {
            return;
        }

        $qualifies = $deposit->getStatus() !== 'rejeté';
        $existing = $this->entryRepository->findOneBySource(AccountingEntry::SOURCE_DEPOSIT, $deposit->getId());

        if (!$qualifies) {
            if ($existing !== null) {
                $this->em->remove($existing);
            }
            return;
        }

        $entry = $existing ?? $this->newEntry($school, AccountingEntry::SOURCE_DEPOSIT, $deposit->getId(), $actor);
        $entry
            ->setType(AccountingEntry::TYPE_VERSEMENT)
            ->setAccount(null)
            ->setAmount($deposit->getAmount())
            ->setEntryDate($deposit->getDepositDate() ?? new \DateTime())
            ->setPaymentMethod('virement')
            ->setLabel(sprintf('Versement bancaire — bordereau %s', $deposit->getReference() ?? ''));

        $this->persistIfNew($entry, $existing);
    }

    private function newEntry(School $school, string $sourceType, int $sourceId, ?User $actor): AccountingEntry
    {
        return (new AccountingEntry())
            ->setSchool($school)
            ->setSourceType($sourceType)
            ->setSourceId($sourceId)
            ->setReference($this->generateReference($school))
            ->setRecordedBy($actor);
    }

    private function persistIfNew(AccountingEntry $entry, ?AccountingEntry $existing): void
    {
        if ($existing === null) {
            $this->em->persist($entry);
        }
    }

    /**
     * Référence unique d'écriture : AC-AAAAMMJJ-0001 (séquence journalière).
     */
    public function generateReference(School $school): string
    {
        $prefix = 'AC-' . date('Ymd') . '-';

        // Initialise le compteur depuis le maximum en base une seule fois par préfixe,
        // puis incrémente en mémoire pour rester unique au sein d'un même flush.
        if (!isset($this->referenceCounters[$prefix])) {
            $last = $this->em->createQueryBuilder()
                ->select('e.reference')
                ->from(AccountingEntry::class, 'e')
                ->where('e.reference LIKE :prefix')
                ->setParameter('prefix', $prefix . '%')
                ->orderBy('e.reference', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            $this->referenceCounters[$prefix] = ($last && preg_match('/(\d+)$/', $last['reference'], $m))
                ? (int) $m[1]
                : 0;
        }

        $this->referenceCounters[$prefix]++;

        return sprintf('%s%04d', $prefix, $this->referenceCounters[$prefix]);
    }
}
