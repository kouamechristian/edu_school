<?php

namespace App\Service;

use App\Entity\Fee;
use App\Entity\School;
use App\Entity\StudentFee;
use App\Repository\SchoolYearRepository;
use App\Repository\StudentFeeRepository;
use App\Repository\StudentRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Psr\Log\LoggerInterface;

/**
 * Importe des arriérés (impayés d'une année antérieure) depuis un fichier .xlsx.
 *
 * Logique partagée entre la commande CLI {@see \App\Command\ImportArrieresCommand}
 * et l'import web (bouton de la page « État des arriérés »). Le fichier doit contenir
 * les colonnes « matricule » et « montant_arriere » (entête détectée ; à défaut, les
 * deux premières colonnes). Pour chaque ligne : élève retrouvé par matricule interne,
 * inscription active de l'année cible requise, puis création d'une ligne de frais
 * « Arriéré {annee} » marquée isArriereAnterieur=true.
 */
class ArriereImporter
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StudentRepository $studentRepository,
        private readonly StudentFeeRepository $studentFeeRepository,
        private readonly SchoolYearRepository $schoolYearRepository,
        private readonly ArriereManager $arriereManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param string      $filePath        Chemin du fichier .xlsx
     * @param string      $anneeCible      Année scolaire d'inscription cible (ex. "2025-2026")
     * @param string      $anneeOrigine    Année d'origine des arriérés (ex. "2024-2025")
     * @param bool        $apply           true = écrit en base ; false = simple aperçu
     * @param School|null $restrictToSchool Si fourni, ignore les élèves d'un autre établissement
     *
     * @return array{
     *     error: ?string,
     *     applied: bool,
     *     imported: int,
     *     skipped: int,
     *     rows: list<array{line:int, matricule:string, montant:string, status:string}>
     * }
     */
    public function import(string $filePath, string $anneeCible, string $anneeOrigine, bool $apply, ?School $restrictToSchool = null): array
    {
        $result = ['error' => null, 'applied' => $apply, 'imported' => 0, 'skipped' => 0, 'rows' => []];

        if (!is_file($filePath) || !is_readable($filePath)) {
            $result['error'] = sprintf('Fichier introuvable ou illisible : %s', $filePath);

            return $result;
        }

        // Année scolaire cible (celle des inscriptions actives à charger).
        $schoolYear = $this->schoolYearRepository->findOneBy(['name' => $anneeCible]);
        if ($schoolYear === null) {
            $result['error'] = sprintf('Année scolaire "%s" introuvable.', $anneeCible);

            return $result;
        }

        try {
            $sheet = IOFactory::load($filePath)->getActiveSheet();
        } catch (\Throwable $e) {
            $result['error'] = sprintf('Impossible de lire le fichier .xlsx : %s', $e->getMessage());

            return $result;
        }

        /** @var array<int, array<int, mixed>> $rows */
        $rows = $sheet->toArray(null, true, false, false);
        if ($rows === []) {
            $result['error'] = 'Le fichier est vide.';

            return $result;
        }

        // Repérage des colonnes depuis l'entête (sinon 0 = matricule, 1 = montant).
        [$colMatricule, $colMontant, $hasHeader] = $this->resolveColumns($rows[0]);
        $dataRows = $hasHeader ? \array_slice($rows, 1) : $rows;

        /** @var array<int, Fee> $feeBySchool cache des frais « Arriéré » par établissement */
        $feeBySchool = [];
        /** @var array<string, true> $seen élèves déjà traités ce run (dédoublonnage du fichier) */
        $seen = [];

        foreach ($dataRows as $index => $row) {
            $lineNo = $index + ($hasHeader ? 2 : 1);
            $matricule = trim((string) ($row[$colMatricule] ?? ''));
            $montantRaw = $row[$colMontant] ?? null;

            if ($matricule === '' && ($montantRaw === null || $montantRaw === '')) {
                continue; // ligne vide
            }

            $montant = $this->parseAmount($montantRaw);
            if ($montant <= 0) {
                $this->skip($result, $lineNo, $matricule, (string) $montantRaw, 'IGNORÉ (montant invalide)');
                continue;
            }

            $student = $this->studentRepository->findByMatriculeInterne($matricule);
            if ($student === null) {
                $this->skip($result, $lineNo, $matricule, $this->fmt($montant), 'IGNORÉ (matricule introuvable)');
                continue;
            }

            if ($restrictToSchool !== null && $student->getSchool()?->getId() !== $restrictToSchool->getId()) {
                $this->skip($result, $lineNo, $matricule, $this->fmt($montant), 'IGNORÉ (autre établissement)');
                continue;
            }

            $registration = $student->getRegistrationForYear($schoolYear);
            if ($registration === null || !$registration->isActive()) {
                $this->skip($result, $lineNo, $matricule, $this->fmt($montant), sprintf('IGNORÉ (pas d\'inscription %s)', $anneeCible));
                continue;
            }

            $school = $student->getSchool();
            if ($school === null) {
                $this->skip($result, $lineNo, $matricule, $this->fmt($montant), 'IGNORÉ (élève sans établissement)');
                continue;
            }

            // Frais « conteneur » partagé par établissement (le montant réel est porté par StudentFee).
            $fee = $feeBySchool[$school->getId()]
                ??= $this->arriereManager->resolveArriereFee($school, $anneeOrigine, $apply);

            // Idempotence : une seule ligne d'arriéré par élève.
            //  - même run : dédoublonnage en mémoire (le frais n'a pas encore d'id) ;
            //  - runs précédents : contrôle en base, seulement si le frais existe déjà.
            $dedupKey = $student->getId() . ':' . $fee->getCode();
            $alreadyImported = isset($seen[$dedupKey])
                || ($fee->getId() !== null
                    && $this->studentFeeRepository->findOneForStudentAndFee($student->getId(), $fee->getId()) !== null);

            if ($alreadyImported) {
                $this->skip($result, $lineNo, $matricule, $this->fmt($montant), 'IGNORÉ (arriéré déjà importé)');
                continue;
            }
            $seen[$dedupKey] = true;

            if ($apply) {
                $studentFee = new StudentFee();
                $studentFee->setStudent($student);
                $studentFee->setFee($fee);
                $studentFee->setRegistration($registration);
                $studentFee->setAmount($this->fmt($montant));
                $studentFee->setIsArriereAnterieur(true);
                $studentFee->setAnneeOrigine($anneeOrigine);

                $registration->addStudentFee($studentFee);
                $student->addStudentFee($studentFee);
                $fee->addStudentFee($studentFee);
                $this->entityManager->persist($studentFee);
            }

            $result['imported']++;
            $result['rows'][] = ['line' => $lineNo, 'matricule' => $matricule, 'montant' => $this->fmt($montant), 'status' => $apply ? 'IMPORTÉ' : 'À IMPORTER'];
        }

        if ($apply) {
            $this->entityManager->flush();
        }

        return $result;
    }

    /**
     * @param array{error: ?string, applied: bool, imported: int, skipped: int, rows: list<array{line:int, matricule:string, montant:string, status:string}>} $result
     */
    private function skip(array &$result, int $lineNo, string $matricule, string $montant, string $status): void
    {
        $result['skipped']++;
        $result['rows'][] = ['line' => $lineNo, 'matricule' => $matricule, 'montant' => $montant, 'status' => $status];
        $this->logger->info('Import arriéré ignoré', ['ligne' => $lineNo, 'matricule' => $matricule, 'raison' => $status]);
    }

    /**
     * Détermine les index de colonnes matricule / montant à partir de l'entête.
     *
     * @param array<int, mixed> $firstRow
     *
     * @return array{0:int, 1:int, 2:bool} [colMatricule, colMontant, hasHeader]
     */
    private function resolveColumns(array $firstRow): array
    {
        $colMatricule = 0;
        $colMontant = 1;
        $hasHeader = false;

        foreach ($firstRow as $i => $value) {
            $header = mb_strtolower(trim((string) $value));
            if (str_contains($header, 'matricule')) {
                $colMatricule = (int) $i;
                $hasHeader = true;
            } elseif (str_contains($header, 'montant') || str_contains($header, 'arriere') || str_contains($header, 'arriéré')) {
                $colMontant = (int) $i;
                $hasHeader = true;
            }
        }

        return [$colMatricule, $colMontant, $hasHeader];
    }

    /**
     * Convertit une valeur de cellule (nombre, ou texte type « 150 000 » / « 150000,50 ») en float.
     */
    private function parseAmount(mixed $raw): float
    {
        if (is_numeric($raw)) {
            return (float) $raw;
        }

        $s = str_replace([' ', ' '], '', (string) $raw); // espaces (dont insécables)
        $s = str_replace(',', '.', $s);
        $s = preg_replace('/[^0-9.]/', '', $s) ?? '';

        return $s === '' ? 0.0 : (float) $s;
    }

    private function fmt(float $montant): string
    {
        return number_format($montant, 2, '.', '');
    }
}
