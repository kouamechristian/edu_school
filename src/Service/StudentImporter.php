<?php

namespace App\Service;

use App\Entity\School;
use App\Entity\Student;
use App\Repository\StudentRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Import Excel / CSV du référentiel élève, en mode « ajout ou mise à jour ».
 *
 * Chaque ligne est rapprochée d'un élève de l'établissement par son MATRICULE INTERNE :
 *  - matricule interne trouvé       → seuls les champs dont la valeur change sont mis à jour ;
 *  - matricule interne vide         → un nouvel élève est créé (matricule généré) ;
 *  - matricule interne inconnu      → un nouvel élève est créé avec ce matricule ;
 *  - cellule vide dans le fichier   → la valeur actuelle est conservée (rien n'est effacé).
 *
 * L'analyse ($apply = false) ne modifie rien : elle sert à l'aperçu. La confirmation
 * ($apply = true) rejoue la même analyse puis enregistre les lignes valides dans une
 * transaction unique. Une ligne invalide est ignorée et signalée, sans bloquer les autres.
 */
class StudentImporter
{
    /** En-têtes du modèle d'import / export (dans l'ordre). */
    public const HEADERS = [
        'Matricule interne', 'Matricule national', 'Nom', 'Prénom', 'Genre (M/F)', 'Date de naissance (JJ/MM/AAAA)',
        'Lieu de naissance', 'Nationalité', 'Numéro extrait de naissance', 'Numéro CMU',
        'Téléphone', 'Email', 'Adresse', 'Dernière école fréquentée', 'Statut (Affecté/Non affecté)',
        'Nom parent', 'Téléphone parent', 'Email parent', 'Fonction parent', 'Domicile parent', 'Photo',
    ];

    /** Alias d'en-têtes acceptés par champ (forme normalisée, correspondance exacte). */
    private const ALIASES = [
        'matriculeInterne' => ['matricule interne', 'matricule'],
        'matriculeNational' => ['matricule national'],
        'lastName' => ['nom'],
        'firstName' => ['prenom', 'prenoms'],
        'gender' => ['genre (m/f)', 'genre', 'sexe'],
        'dateOfBirth' => ['date de naissance (jj/mm/aaaa)', 'date de naissance', 'date naissance'],
        'placeOfBirth' => ['lieu de naissance', 'lieu naissance'],
        'nationality' => ['nationalite'],
        'birthCertificateNumber' => ['numero extrait de naissance', 'extrait de naissance', 'numero extrait'],
        'cmuNumber' => ['numero cmu', 'cmu'],
        'phone' => ['telephone', 'tel', 'telephone eleve'],
        'email' => ['email', 'e-mail', 'mail'],
        'address' => ['adresse'],
        'lastSchoolAttended' => ['derniere ecole frequentee', 'derniere ecole'],
        'status' => ['statut (affecte/non affecte)', 'statut', 'affectation'],
        'parentName' => ['nom parent', 'nom du parent', 'nom du parent/tuteur'],
        'parentPhone' => ['telephone parent', 'tel parent', 'telephone du parent'],
        'parentEmail' => ['email parent', 'email du parent', 'mail parent'],
        'parentFunction' => ['fonction parent', 'fonction du parent'],
        'parentAddress' => ['domicile parent', 'adresse parent', 'domicile du parent'],
    ];

    /**
     * Champs modifiables par l'import : champ => [libellé, getter, setter, type].
     * Types : text | phone | email | gender | date | status.
     */
    private const FIELDS = [
        'matriculeNational' => ['Matricule national', 'getMatriculeNational', 'setMatriculeNational', 'text'],
        'lastName' => ['Nom', 'getLastName', 'setLastName', 'text'],
        'firstName' => ['Prénom', 'getFirstName', 'setFirstName', 'text'],
        'gender' => ['Genre', 'getGender', 'setGender', 'gender'],
        'dateOfBirth' => ['Date de naissance', 'getDateOfBirth', 'setDateOfBirth', 'date'],
        'placeOfBirth' => ['Lieu de naissance', 'getPlaceOfBirth', 'setPlaceOfBirth', 'text'],
        'nationality' => ['Nationalité', 'getNationality', 'setNationality', 'text'],
        'birthCertificateNumber' => ['N° extrait de naissance', 'getBirthCertificateNumber', 'setBirthCertificateNumber', 'text'],
        'cmuNumber' => ['N° CMU', 'getCmuNumber', 'setCmuNumber', 'text'],
        'phone' => ['Téléphone', 'getPhone', 'setPhone', 'phone'],
        'email' => ['Email', 'getEmail', 'setEmail', 'email'],
        'address' => ['Adresse', 'getAddress', 'setAddress', 'text'],
        'lastSchoolAttended' => ['Dernière école', 'getLastSchoolAttended', 'setLastSchoolAttended', 'text'],
        'status' => ['Statut', 'getStatus', 'setStatus', 'status'],
        'parentName' => ['Nom parent', 'getParentName', 'setParentName', 'text'],
        'parentPhone' => ['Téléphone parent', 'getParentPhone', 'setParentPhone', 'phone'],
        'parentEmail' => ['Email parent', 'getParentEmail', 'setParentEmail', 'email'],
        'parentFunction' => ['Fonction parent', 'getParentFunction', 'setParentFunction', 'text'],
        'parentAddress' => ['Domicile parent', 'getParentAddress', 'setParentAddress', 'text'],
    ];

    /** Longueurs maximales des colonnes (cf. entité Student). */
    private const MAX_LENGTH = [
        'matriculeNational' => 50, 'lastName' => 100, 'firstName' => 100, 'placeOfBirth' => 255,
        'nationality' => 100, 'birthCertificateNumber' => 100, 'cmuNumber' => 50, 'email' => 255,
        'lastSchoolAttended' => 255, 'parentName' => 255, 'parentEmail' => 255, 'parentFunction' => 255,
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private StudentRepository $studentRepository,
        private MatriculeGenerator $matriculeGenerator,
    ) {
    }

    /**
     * Analyse (et, si $apply, enregistre) le fichier.
     *
     * @return array{
     *     error: ?string,
     *     created: int, updated: int, unchanged: int, skipped: int,
     *     rows: list<array{line: int, action: string, matricule: ?string, student: string, changes: list<array{label: string, old: string, new: string}>, message: ?string}>
     * }
     */
    public function import(string $path, School $school, bool $apply): array
    {
        $result = ['error' => null, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'rows' => []];

        try {
            $rows = IOFactory::load($path)->getSheet(0)->toArray(null, true, false, false);
        } catch (\Throwable $e) {
            return ['error' => 'Impossible de lire le fichier : ' . $e->getMessage()] + $result;
        }

        if (count($rows) < 2) {
            return ['error' => 'Le fichier ne contient aucune donnée à importer.'] + $result;
        }

        $columns = $this->mapColumns($rows[0]);
        if (!isset($columns['matriculeInterne']) && !isset($columns['lastName'], $columns['firstName'])) {
            return ['error' => 'En-têtes non reconnus : la première ligne doit contenir la colonne « Matricule interne » '
                . '(mise à jour) ou les colonnes « Nom » et « Prénom » (création). Téléchargez le modèle pour le bon format.'] + $result;
        }

        // Élèves de l'établissement (actifs et archivés), indexés par matricule interne normalisé.
        $existing = [];
        $students = $this->studentRepository->createQueryBuilder('s')
            ->andWhere('s.school = :school')
            ->setParameter('school', $school)
            ->getQuery()->getResult();
        foreach ($students as $student) {
            if ($student->getMatriculeInterne()) {
                $existing[$this->key($student->getMatriculeInterne())] = $student;
            }
        }

        $seenMatricules = [];
        $seenNationals = [];
        $toPersist = [];

        foreach ($rows as $i => $row) {
            if ($i === 0) {
                continue;
            }
            $line = $i + 1; // Numéro de ligne tel qu'affiché dans Excel.

            $raw = [];
            foreach (array_keys(self::ALIASES) as $field) {
                $raw[$field] = isset($columns[$field]) ? ($row[$columns[$field]] ?? null) : null;
            }
            $text = fn (string $field): ?string => $this->clean($raw[$field]);

            if (array_filter($raw, fn ($v) => $this->clean($v) !== null) === []) {
                continue; // Ligne vide.
            }

            $matricule = $text('matriculeInterne');
            $label = trim(($text('lastName') ?? '') . ' ' . ($text('firstName') ?? ''));
            $skip = function (string $message) use (&$result, $line, $matricule, $label): void {
                $result['skipped']++;
                $result['rows'][] = ['line' => $line, 'action' => 'error', 'matricule' => $matricule, 'student' => $label, 'changes' => [], 'message' => $message];
            };

            // ── Rapprochement par matricule interne ──
            $student = null;
            if ($matricule !== null) {
                $key = $this->key($matricule);
                if (isset($seenMatricules[$key])) {
                    $skip(sprintf('matricule interne %s déjà présent ligne %d du fichier', $matricule, $seenMatricules[$key]));
                    continue;
                }
                $seenMatricules[$key] = $line;

                $student = $existing[$key] ?? null;
                if (!$student && $this->matriculeUsedElsewhere($matricule)) {
                    $skip(sprintf('le matricule interne %s appartient à un élève d\'un autre établissement', $matricule));
                    continue;
                }
            }
            $isNew = $student === null;

            if ($isNew && ($text('lastName') === null || $text('firstName') === null)) {
                $skip($matricule !== null
                    ? sprintf('matricule interne %s introuvable dans cet établissement, et nom / prénom manquants pour créer l\'élève', $matricule)
                    : 'nom et prénom obligatoires pour créer un élève');
                continue;
            }

            // ── Conversion des valeurs et calcul des changements ──
            $errors = [];
            $changes = [];   // champ => nouvelle valeur
            $display = [];   // liste [libellé, avant, après]

            foreach (self::FIELDS as $field => [$fieldLabel, $getter, , $type]) {
                if (!isset($columns[$field]) || $this->clean($raw[$field]) === null) {
                    continue; // Colonne absente ou cellule vide : valeur actuelle conservée.
                }

                $value = $this->convert($raw[$field], $type, $fieldLabel, $errors);
                if ($value === null) {
                    continue; // Valeur invalide (erreur déjà enregistrée).
                }
                if (isset(self::MAX_LENGTH[$field]) && is_string($value) && mb_strlen($value) > self::MAX_LENGTH[$field]) {
                    $errors[] = sprintf('%s trop long (%d caractères max.)', $fieldLabel, self::MAX_LENGTH[$field]);
                    continue;
                }

                $old = $isNew ? null : $student->$getter();
                if (!$this->same($old, $value, $type)) {
                    $changes[$field] = $value;
                    $display[] = ['label' => $fieldLabel, 'old' => $this->show($old, $type), 'new' => $this->show($value, $type)];
                }
            }

            // Matricule national : unique tous établissements confondus, et dans le fichier.
            if (isset($changes['matriculeNational'])) {
                $national = $changes['matriculeNational'];
                $nKey = $this->key($national);
                if (isset($seenNationals[$nKey])) {
                    $errors[] = sprintf('matricule national %s déjà présent ligne %d du fichier', $national, $seenNationals[$nKey]);
                } elseif ($this->studentRepository->nationalMatriculeExists($national, $isNew ? null : $student->getId())) {
                    $errors[] = sprintf('matricule national %s déjà attribué à un autre élève', $national);
                }
            }
            $currentNational = $changes['matriculeNational'] ?? ($isNew ? null : $student->getMatriculeNational());
            if ($currentNational !== null) {
                $seenNationals[$this->key($currentNational)] ??= $line;
            }

            if ($errors) {
                $skip(implode(' ; ', $errors));
                continue;
            }

            if (!$isNew) {
                $label = $student->getLastName() . ' ' . $student->getFirstName();
            }

            if (!$isNew && $changes === []) {
                $result['unchanged']++;
                $result['rows'][] = ['line' => $line, 'action' => 'unchanged', 'matricule' => $student->getMatriculeInterne(), 'student' => $label, 'changes' => [], 'message' => null];
                continue;
            }

            // ── Application ──
            if ($apply) {
                if ($isNew) {
                    $student = new Student();
                    $student->setSchool($school);
                    $student->setMatriculeInterne($matricule ?? $this->matriculeGenerator->generate($this->entityManager, Student::class));
                    $toPersist[] = $student;
                }
                foreach ($changes as $field => $value) {
                    $setter = self::FIELDS[$field][2];
                    $student->$setter($value);
                }
                $matricule = $student->getMatriculeInterne();
            }

            $result[$isNew ? 'created' : 'updated']++;
            $result['rows'][] = [
                'line' => $line,
                'action' => $isNew ? 'create' : 'update',
                'matricule' => $isNew ? ($matricule ?? null) : $student->getMatriculeInterne(),
                'student' => $label,
                'changes' => $display,
                'message' => null,
            ];
        }

        if ($apply && ($result['created'] + $result['updated']) > 0) {
            $connection = $this->entityManager->getConnection();
            $connection->beginTransaction();
            try {
                foreach ($toPersist as $student) {
                    $this->entityManager->persist($student);
                }
                $this->entityManager->flush();
                $connection->commit();
            } catch (\Throwable $e) {
                $connection->rollBack();

                return ['error' => 'Import annulé, aucune modification enregistrée : ' . $e->getMessage()] + $result;
            }
        }

        return $result;
    }

    /**
     * @param list<string> $errors
     */
    private function convert(mixed $raw, string $type, string $label, array &$errors): mixed
    {
        $value = $this->clean($raw);

        switch ($type) {
            case 'gender':
                $gender = match ($this->normalize($value)) {
                    'm', 'masculin', 'garcon', 'h', 'homme' => 'M',
                    'f', 'feminin', 'fille', 'femme' => 'F',
                    default => null,
                };
                if ($gender === null) {
                    $errors[] = sprintf('genre « %s » invalide (M ou F)', $value);
                }

                return $gender;

            case 'status':
                $status = match ($this->normalize($value)) {
                    'affecte', 'affectee', 'aff', 'oui' => 'affecte',
                    'non affecte', 'non affectee', 'non-affecte', 'naff', 'non' => 'non_affecte',
                    default => null,
                };
                if ($status === null) {
                    $errors[] = sprintf('statut « %s » invalide (Affecté ou Non affecté)', $value);
                }

                return $status;

            case 'date':
                $date = $this->parseDate($raw);
                if ($date === null) {
                    $errors[] = sprintf('%s « %s » invalide (JJ/MM/AAAA)', mb_strtolower($label), $value);
                }

                return $date;

            case 'phone':
                $digits = (string) preg_replace('/\D+/', '', $value);
                // Excel supprime le 0 initial d'un numéro saisi comme nombre.
                if (strlen($digits) === 9) {
                    $digits = '0' . $digits;
                }
                if (strlen($digits) === 13 && str_starts_with($digits, '225')) {
                    $digits = substr($digits, 3);
                }
                if (!preg_match('/^\d{10}$/', $digits)) {
                    $errors[] = sprintf('%s « %s » invalide (10 chiffres)', mb_strtolower($label), $value);

                    return null;
                }

                return $digits;

            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = sprintf('%s « %s » invalide', mb_strtolower($label), $value);

                    return null;
                }

                return mb_strtolower($value);

            default:
                return $value;
        }
    }

    private function same(mixed $old, mixed $new, string $type): bool
    {
        if ($type === 'date') {
            return $old instanceof \DateTimeInterface && $new instanceof \DateTimeInterface
                && $old->format('Y-m-d') === $new->format('Y-m-d');
        }

        return (string) ($old ?? '') === (string) ($new ?? '');
    }

    private function show(mixed $value, string $type): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y');
        }
        if ($type === 'status' && $value !== null) {
            return $value === 'affecte' ? 'Affecté' : 'Non affecté';
        }

        return (string) ($value ?? '');
    }

    /** Le matricule interne est-il porté par un élève d'un AUTRE établissement ? (SQL direct : hors filtre Doctrine) */
    private function matriculeUsedElsewhere(string $matricule): bool
    {
        return (bool) $this->entityManager->getConnection()->fetchOne(
            'SELECT 1 FROM student WHERE LOWER(TRIM(matricule_interne)) = :m LIMIT 1',
            ['m' => $this->key($matricule)]
        );
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

    private function key(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    /** Minuscules, sans accents, espaces réduits. */
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

    /** Texte nettoyé (espaces de début/fin retirés, espaces multiples réduits) ; vide → null. */
    private function clean(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y');
        }
        $value = trim((string) preg_replace('/\s+/u', ' ', (string) ($value ?? '')));

        return $value === '' ? null : $value;
    }

    private function parseDate(mixed $value): ?\DateTimeInterface
    {
        if ($value instanceof \DateTimeInterface) {
            return \DateTime::createFromInterface($value)->setTime(0, 0);
        }
        // Valeur numérique : date sérielle Excel.
        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->setTime(0, 0);
            } catch (\Throwable) {
                return null;
            }
        }

        $value = trim((string) $value);
        // « 2015-04-19 00:00:00 » : date Excel convertie en texte.
        $value = (string) preg_replace('/^(\d{4}-\d{2}-\d{2})[ T]00:00(:00)?$/', '$1', $value);
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
