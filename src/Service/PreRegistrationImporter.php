<?php

namespace App\Service;

use App\Entity\Level;
use App\Entity\PreRegistration;
use App\Entity\School;
use App\Entity\SchoolYear;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Import de préinscriptions depuis un fichier Excel / CSV.
 *
 * Les colonnes sont reconnues par leur EN-TÊTE (insensible à la casse et aux
 * accents), l'ordre importe donc peu. Champs obligatoires par ligne : Niveau
 * demandé, Nom, Prénom, Genre et Contact d'urgence. Les préinscriptions sont
 * créées « En attente » dans l'établissement et l'année scolaire courants.
 */
class PreRegistrationImporter
{
    /** En-têtes du modèle (dans l'ordre), champs obligatoires en premier. */
    public const HEADERS = [
        'Niveau demandé', 'Nom', 'Prénom', 'Genre (M/F)', 'Contact d\'urgence',
        'Téléphone d\'urgence', 'Matricule national', 'Date de naissance (JJ/MM/AAAA)', 'Lieu de naissance',
        'Nationalité', 'Téléphone', 'Email', 'Adresse', 'Dernière école fréquentée', 'Doublant (Oui/Non)',
        'Nom du père', 'Prénom du père', 'Contact du père', 'Nom de la mère', 'Prénom de la mère',
        'Contact de la mère', 'Email de contact', 'Informations médicales', 'Notes',
    ];

    private const REQUIRED = [
        'requestedLevel' => 'Niveau demandé',
        'lastName' => 'Nom',
        'firstName' => 'Prénom',
        'gender' => 'Genre',
        'emergencyContact' => 'Contact d\'urgence',
    ];

    /** Alias d'en-têtes acceptés par champ (forme normalisée, correspondance exacte). */
    private const ALIASES = [
        'requestedLevel' => ['niveau demande', 'niveau'],
        'lastName' => ['nom'],
        'firstName' => ['prenom', 'prenoms'],
        'gender' => ['genre (m/f)', 'genre', 'sexe'],
        'emergencyContact' => ['contact d\'urgence', 'contact urgence'],
        'emergencyPhone' => ['telephone d\'urgence', 'telephone urgence'],
        'matriculeNational' => ['matricule national'],
        'dateOfBirth' => ['date de naissance (jj/mm/aaaa)', 'date de naissance', 'date naissance'],
        'placeOfBirth' => ['lieu de naissance', 'lieu naissance'],
        'nationality' => ['nationalite'],
        'phone' => ['telephone', 'tel'],
        'email' => ['email', 'e-mail', 'mail'],
        'address' => ['adresse'],
        'lastSchoolAttended' => ['derniere ecole frequentee', 'derniere ecole'],
        'isRepeating' => ['doublant (oui/non)', 'doublant', 'redoublant'],
        'fatherLastName' => ['nom du pere'],
        'fatherFirstName' => ['prenom du pere'],
        'fatherPhone' => ['contact du pere', 'telephone du pere'],
        'motherLastName' => ['nom de la mere'],
        'motherFirstName' => ['prenom de la mere'],
        'motherPhone' => ['contact de la mere', 'telephone de la mere'],
        'parentEmail' => ['email de contact', 'email parent'],
        'medicalInfo' => ['informations medicales'],
        'notes' => ['notes', 'observations'],
    ];

    /** Champs texte recopiés tels quels : champ => setter. */
    private const TEXT_FIELDS = [
        'emergencyPhone' => 'setEmergencyPhone',
        'matriculeNational' => 'setMatriculeNational',
        'placeOfBirth' => 'setPlaceOfBirth',
        'nationality' => 'setNationality',
        'phone' => 'setPhone',
        'email' => 'setEmail',
        'address' => 'setAddress',
        'lastSchoolAttended' => 'setLastSchoolAttended',
        'fatherLastName' => 'setFatherLastName',
        'fatherFirstName' => 'setFatherFirstName',
        'fatherPhone' => 'setFatherPhone',
        'motherLastName' => 'setMotherLastName',
        'motherFirstName' => 'setMotherFirstName',
        'motherPhone' => 'setMotherPhone',
        'parentEmail' => 'setParentEmail',
        'medicalInfo' => 'setMedicalInfo',
        'notes' => 'setNotes',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MatriculeGenerator $matriculeGenerator
    ) {
    }

    /**
     * Modèle Excel à remplir, avec une ligne d'exemple.
     */
    public function buildTemplate(School $school): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Préinscriptions');
        $sheet->fromArray(self::HEADERS, null, 'A1');

        $lastCol = $sheet->getHighestColumn();
        $sheet->getStyle('A1:' . $lastCol . '1')->getFont()->setBold(true);
        // Colonnes obligatoires surlignées.
        $sheet->getStyle('A1:E1')->getFill()->setFillType('solid')->getStartColor()->setRGB('FFE699');

        $levels = $this->levelsOf($school);
        $sheet->fromArray([
            $levels ? reset($levels)->getName() : '6ème', 'KOUASSI', 'Jean', 'M', '0700000000',
            '0500000000', '', '15/09/2014', 'Abidjan', 'Ivoirienne', '', '', 'Cocody', 'École Les Oranges', 'Non',
            'KOUASSI', 'Pierre', '0700000001', 'KONAN', 'Awa', '0700000002', '', '', '',
        ], null, 'A2', true);

        foreach ($sheet->getColumnIterator() as $column) {
            $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
        }

        return $spreadsheet;
    }

    /**
     * Export Excel d'une liste de préinscriptions. Les colonnes reprennent celles
     * du modèle d'import (le fichier peut donc être ré-importé), suivies du
     * matricule interne, du type, du statut et de la date de création.
     *
     * @param iterable<PreRegistration> $preRegistrations
     */
    public function buildExport(iterable $preRegistrations): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Préinscriptions');

        $headers = array_merge(self::HEADERS, ['Matricule interne', 'Type', 'Statut', 'Date de préinscription']);
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFont()->setBold(true);

        $rowNum = 2;
        foreach ($preRegistrations as $p) {
            $row = [
                $p->getRequestedLevel()?->getName(),
                $p->getLastName(),
                $p->getFirstName(),
                $p->getGender(),
                $p->getEmergencyContact(),
                $p->getEmergencyPhone(),
                $p->getMatriculeNational(),
                $p->getDateOfBirth()?->format('d/m/Y'),
                $p->getPlaceOfBirth(),
                $p->getNationality(),
                $p->getPhone(),
                $p->getEmail(),
                $p->getAddress(),
                $p->getLastSchoolAttended(),
                $p->isRepeating() ? 'Oui' : 'Non',
                $p->getFatherLastName(),
                $p->getFatherFirstName(),
                $p->getFatherPhone(),
                $p->getMotherLastName(),
                $p->getMotherFirstName(),
                $p->getMotherPhone(),
                $p->getParentEmail(),
                $p->getMedicalInfo(),
                $p->getNotes(),
                $p->getMatriculeInterne(),
                $p->getTypeLabel(),
                $p->getStatusLabel(),
                $p->getCreatedAt()?->format('d/m/Y H:i'),
            ];

            // Écriture en texte explicite : conserve le 0 initial des numéros de téléphone.
            foreach ($row as $col => $value) {
                $sheet->setCellValueExplicit([$col + 1, $rowNum], (string) ($value ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $rowNum++;
        }

        foreach ($sheet->getColumnIterator() as $column) {
            $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
        }
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());

        return $spreadsheet;
    }

    /**
     * Importe le fichier. Une ligne invalide est ignorée et signalée, sans bloquer les autres.
     *
     * @return array{created: int, errors: list<string>, fatal: ?string}
     */
    public function import(string $path, School $school, SchoolYear $schoolYear, ?User $submittedBy): array
    {
        try {
            $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, false, false);
        } catch (\Throwable $e) {
            return ['created' => 0, 'errors' => [], 'fatal' => 'Impossible de lire le fichier : ' . $e->getMessage()];
        }

        if (count($rows) < 2) {
            return ['created' => 0, 'errors' => [], 'fatal' => 'Le fichier ne contient aucune donnée à importer.'];
        }

        $columns = $this->mapColumns($rows[0]);
        $missing = array_diff_key(self::REQUIRED, $columns);
        if ($missing) {
            return ['created' => 0, 'errors' => [], 'fatal' => 'Colonne(s) obligatoire(s) absente(s) : « ' . implode(' », « ', $missing)
                . ' ». Téléchargez le modèle pour le bon format.'];
        }

        // Niveaux de l'établissement, indexés par nom normalisé.
        $levels = [];
        foreach ($this->levelsOf($school) as $level) {
            $levels[$this->normalize($level->getName())] = $level;
        }

        // Doublons : même nom + prénom + niveau déjà préinscrit cette année (ou plus haut dans le fichier).
        $seen = [];
        foreach ($this->entityManager->getRepository(PreRegistration::class)->findBy(['school' => $school, 'schoolYear' => $schoolYear]) as $existing) {
            $seen[$this->duplicateKey($existing->getLastName(), $existing->getFirstName(), $existing->getRequestedLevel())] = true;
        }

        $created = 0;
        $errors = [];

        foreach ($rows as $i => $row) {
            if ($i === 0) {
                continue;
            }

            $line = $i + 1; // Numéro de ligne tel qu'affiché dans Excel.
            $value = fn (string $field): ?string => isset($columns[$field]) ? $this->clean($row[$columns[$field]] ?? null) : null;

            $values = array_map($value, array_combine(array_keys(self::ALIASES), array_keys(self::ALIASES)));
            if (array_filter($values, fn ($v) => $v !== null) === []) {
                continue; // Ligne vide.
            }

            $lineErrors = [];
            foreach (self::REQUIRED as $field => $label) {
                if ($values[$field] === null) {
                    $lineErrors[] = $label . ' manquant';
                }
            }

            $level = null;
            if ($values['requestedLevel'] !== null) {
                $level = $levels[$this->normalize($values['requestedLevel'])] ?? null;
                if (!$level) {
                    $lineErrors[] = 'niveau « ' . $values['requestedLevel'] . ' » inconnu dans cet établissement';
                }
            }

            $gender = $values['gender'] !== null ? $this->parseGender($values['gender']) : null;
            if ($values['gender'] !== null && $gender === null) {
                $lineErrors[] = 'genre « ' . $values['gender'] . ' » invalide (M ou F)';
            }

            $dateOfBirth = null;
            if (isset($columns['dateOfBirth']) && ($raw = $row[$columns['dateOfBirth']] ?? null) !== null && $raw !== '') {
                $dateOfBirth = $this->parseDate($raw);
                if (!$dateOfBirth) {
                    $lineErrors[] = 'date de naissance « ' . $raw . ' » invalide (JJ/MM/AAAA)';
                }
            }

            if ($values['email'] !== null && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
                $lineErrors[] = 'email « ' . $values['email'] . ' » invalide';
            }

            if ($lineErrors) {
                $errors[] = sprintf('Ligne %d : %s.', $line, implode(', ', $lineErrors));
                continue;
            }

            $key = $this->duplicateKey($values['lastName'], $values['firstName'], $level);
            if (isset($seen[$key])) {
                $errors[] = sprintf('Ligne %d : %s %s est déjà préinscrit(e) en %s cette année, ignoré(e).', $line, $values['lastName'], $values['firstName'], $level->getName());
                continue;
            }
            $seen[$key] = true;

            $preRegistration = new PreRegistration();
            $preRegistration->setSchool($school);
            $preRegistration->setSchoolYear($schoolYear);
            $preRegistration->setRequestedLevel($level);
            $preRegistration->setLastName($values['lastName']);
            $preRegistration->setFirstName($values['firstName']);
            $preRegistration->setGender($gender);
            $preRegistration->setEmergencyContact($values['emergencyContact']);
            $preRegistration->setDateOfBirth($dateOfBirth);
            $preRegistration->setIsRepeating($this->parseBoolean($values['isRepeating']));
            $preRegistration->setSubmittedBy($submittedBy);

            foreach (self::TEXT_FIELDS as $field => $setter) {
                $preRegistration->$setter($values[$field]);
            }

            $preRegistration->setMatriculeInterne(
                $this->matriculeGenerator->generate($this->entityManager, PreRegistration::class)
            );

            $this->entityManager->persist($preRegistration);
            $created++;
        }

        if ($created > 0) {
            $this->entityManager->flush();
        }

        return ['created' => $created, 'errors' => $errors, 'fatal' => null];
    }

    /**
     * @return Level[]
     */
    private function levelsOf(School $school): array
    {
        return $this->entityManager->getRepository(Level::class)->findBy(['school' => $school], ['orderNumber' => 'ASC']);
    }

    /**
     * @param array<int, mixed> $headerRow
     *
     * @return array<string, int> champ => index de colonne
     */
    private function mapColumns(array $headerRow): array
    {
        $labelToField = [];
        foreach (self::ALIASES as $field => $labels) {
            foreach ($labels as $label) {
                $labelToField[$label] = $field;
            }
        }

        $map = [];
        foreach ($headerRow as $idx => $header) {
            $field = $labelToField[$this->normalize($header)] ?? null;
            if ($field !== null && !isset($map[$field])) {
                $map[$field] = (int) $idx;
            }
        }

        return $map;
    }

    /** Minuscules, sans accents ni apostrophe typographique, espaces réduits. */
    private function normalize(mixed $value): string
    {
        $s = mb_strtolower(trim((string) ($value ?? '')));
        $s = strtr($s, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ó' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
            'ç' => 'c', '’' => '\'',
        ]);

        return (string) preg_replace('/\s+/', ' ', $s);
    }

    private function clean(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function duplicateKey(?string $lastName, ?string $firstName, ?Level $level): string
    {
        return $this->normalize($lastName) . '|' . $this->normalize($firstName) . '|' . $level?->getId();
    }

    private function parseGender(string $value): ?string
    {
        return match ($this->normalize($value)) {
            'm', 'masculin', 'garcon', 'h', 'homme' => 'M',
            'f', 'feminin', 'fille', 'femme' => 'F',
            default => null,
        };
    }

    private function parseBoolean(?string $value): bool
    {
        return in_array($this->normalize($value), ['oui', 'o', 'yes', '1', 'true', 'vrai', 'x'], true);
    }

    private function parseDate(mixed $value): ?\DateTimeInterface
    {
        // Valeur numérique : date sérielle Excel.
        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value);
            } catch (\Throwable) {
                return null;
            }
        }

        $value = trim((string) $value);
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y'] as $format) {
            // « ! » remet l'heure à zéro ; on refuse les dates « corrigées » (ex. 31/02).
            $date = \DateTime::createFromFormat('!' . $format, $value);
            $problems = \DateTime::getLastErrors();
            if ($date instanceof \DateTime && !($problems && ($problems['warning_count'] || $problems['error_count']))) {
                return $date;
            }
        }

        return null;
    }
}
