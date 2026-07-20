<?php

namespace App\Repository;

use App\Entity\AccountingEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AccountingEntry>
 */
class AccountingEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccountingEntry::class);
    }

    public function findOneBySource(string $sourceType, int $sourceId): ?AccountingEntry
    {
        return $this->findOneBy(['sourceType' => $sourceType, 'sourceId' => $sourceId]);
    }

    /**
     * Journal filtré (le plus récent d'abord).
     *
     * @param array{from?:?\DateTimeInterface,to?:?\DateTimeInterface,type?:?string,account?:?int} $filters
     * @return AccountingEntry[]
     */
    public function findJournal(int $schoolId, array $filters = []): array
    {
        return $this->journalQb($schoolId, $filters)
            ->orderBy('e.entryDate', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Écritures pour le grand livre : ordonnées par compte puis par date, de façon
     * à être regroupées par compte avec un solde progressif.
     *
     * @param array{from?:?\DateTimeInterface,to?:?\DateTimeInterface,account?:?int} $filters
     * @return AccountingEntry[]
     */
    public function findForLedger(int $schoolId, array $filters = []): array
    {
        return $this->journalQb($schoolId, $filters)
            ->leftJoin('e.account', 'a')->addSelect('a')
            ->addSelect('CASE WHEN e.account IS NULL THEN 1 ELSE 0 END AS HIDDEN nullAccount')
            ->orderBy('nullAccount', 'ASC')
            ->addOrderBy('a.type', 'ASC')
            ->addOrderBy('a.code', 'ASC')
            ->addOrderBy('e.entryDate', 'ASC')
            ->addOrderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Synthèse caisse / banque pour le rapprochement, à partir du journal.
     *
     * Règles : une recette/dépense en « espèces » touche la caisse, par tout autre
     * moyen (virement, chèque, carte, mobile money) elle touche la banque ; un
     * versement transfère la caisse vers la banque.
     *
     * @return array{cash_in:float,cash_out:float,bank_in:float,bank_out:float,versement:float,cash_theoretical:float,bank_theoretical:float}
     */
    public function reconciliationSummary(int $schoolId, ?\DateTimeInterface $from, ?\DateTimeInterface $to): array
    {
        $rows = $this->journalQb($schoolId, ['from' => $from, 'to' => $to])
            ->select('e.type AS type', 'e.paymentMethod AS method', 'SUM(e.amount) AS total')
            ->groupBy('e.type')
            ->addGroupBy('e.paymentMethod')
            ->getQuery()
            ->getScalarResult();

        $cashIn = $cashOut = $bankIn = $bankOut = $versement = 0.0;
        foreach ($rows as $row) {
            $amount = (float) $row['total'];
            $isCash = $row['method'] === 'espèces';
            if ($row['type'] === AccountingEntry::TYPE_VERSEMENT) {
                $versement += $amount;
            } elseif ($row['type'] === AccountingEntry::TYPE_RECETTE) {
                $isCash ? $cashIn += $amount : $bankIn += $amount;
            } elseif ($row['type'] === AccountingEntry::TYPE_DEPENSE) {
                $isCash ? $cashOut += $amount : $bankOut += $amount;
            }
        }

        return [
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'bank_in' => $bankIn,
            'bank_out' => $bankOut,
            'versement' => $versement,
            'cash_theoretical' => $cashIn - $cashOut - $versement,
            'bank_theoretical' => $versement + $bankIn - $bankOut,
        ];
    }

    public function countInRange(int $schoolId, ?\DateTimeInterface $from, ?\DateTimeInterface $to): int
    {
        return (int) $this->journalQb($schoolId, ['from' => $from, 'to' => $to])
            ->select('COUNT(e.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Totaux par type d'écriture sur une période.
     *
     * @return array{recette:float,depense:float,versement:float}
     */
    public function totalsByType(int $schoolId, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $rows = $this->journalQb($schoolId, ['from' => $from, 'to' => $to])
            ->select('e.type AS type', 'SUM(e.amount) AS total')
            ->groupBy('e.type')
            ->getQuery()
            ->getScalarResult();

        $out = ['recette' => 0.0, 'depense' => 0.0, 'versement' => 0.0];
        foreach ($rows as $row) {
            $out[$row['type']] = (float) $row['total'];
        }

        return $out;
    }

    /**
     * Totaux par compte (poste) pour un type donné, sur une période.
     *
     * @return array<int, array{id:?int,code:?string,name:string,total:float}>
     */
    public function totalsByAccount(int $schoolId, string $type, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $rows = $this->journalQb($schoolId, ['from' => $from, 'to' => $to, 'type' => $type])
            ->leftJoin('e.account', 'a')
            ->select('a.id AS id', 'a.code AS code', 'a.name AS name', 'SUM(e.amount) AS total')
            ->groupBy('a.id')
            ->addSelect('a.code', 'a.name')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getScalarResult();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => $row['id'] !== null ? (int) $row['id'] : null,
                'code' => $row['code'],
                'name' => $row['name'] ?? 'Non ventilé',
                'total' => (float) $row['total'],
            ];
        }

        return $out;
    }

    /**
     * Séries mensuelles (recettes / dépenses) sur une période quelconque [from, to].
     * Renvoie des libellés de mois (MM/AA) et deux listes de valeurs alignées, prêtes
     * pour un graphique — la période pouvant chevaucher deux années civiles.
     *
     * @return array{labels:list<string>,recette:list<float>,depense:list<float>}
     */
    public function monthlySeries(int $schoolId, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        // Un compartiment par mois de la période (clé Y-m), dans l'ordre chronologique.
        $buckets = [];
        $cursor = new \DateTimeImmutable($from->format('Y-m-01'));
        $last = new \DateTimeImmutable($to->format('Y-m-01'));
        while ($cursor <= $last) {
            $buckets[$cursor->format('Y-m')] = ['label' => $cursor->format('m/y'), 'recette' => 0.0, 'depense' => 0.0];
            $cursor = $cursor->modify('+1 month');
        }

        // MONTH() n'est pas une fonction DQL standard : on agrège par mois côté PHP.
        $rows = $this->journalQb($schoolId, ['from' => $from, 'to' => $to])
            ->select('e.entryDate AS entryDate', 'e.type AS type', 'e.amount AS amount')
            ->getQuery()
            ->getScalarResult();

        foreach ($rows as $row) {
            $date = $row['entryDate'] instanceof \DateTimeInterface
                ? $row['entryDate']
                : new \DateTime((string) $row['entryDate']);
            $key = $date->format('Y-m');
            if (!isset($buckets[$key])) {
                continue;
            }
            if ($row['type'] === AccountingEntry::TYPE_RECETTE) {
                $buckets[$key]['recette'] += (float) $row['amount'];
            } elseif ($row['type'] === AccountingEntry::TYPE_DEPENSE) {
                $buckets[$key]['depense'] += (float) $row['amount'];
            }
        }

        return [
            'labels' => array_values(array_column($buckets, 'label')),
            'recette' => array_values(array_column($buckets, 'recette')),
            'depense' => array_values(array_column($buckets, 'depense')),
        ];
    }

    /**
     * Construit la requête de base du journal avec les filtres communs.
     *
     * @param array{from?:?\DateTimeInterface,to?:?\DateTimeInterface,type?:?string,account?:?int} $filters
     */
    private function journalQb(int $schoolId, array $filters): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.school = :school')
            ->setParameter('school', $schoolId);

        if (!empty($filters['from'])) {
            $qb->andWhere('e.entryDate >= :from')->setParameter('from', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $qb->andWhere('e.entryDate <= :to')->setParameter('to', $filters['to']);
        }
        if (!empty($filters['type'])) {
            $qb->andWhere('e.type = :type')->setParameter('type', $filters['type']);
        }
        if (!empty($filters['account'])) {
            $qb->andWhere('e.account = :account')->setParameter('account', $filters['account']);
        }

        return $qb;
    }
}
