<?php

namespace App\Service;

use App\Entity\CashRegister;
use App\Entity\Payment;
use App\Entity\School;
use App\Entity\Student;
use App\Entity\User;
use App\Repository\StudentRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Import de paiements depuis un fichier Excel / CSV (rôle Caisse et au-dessus).
 *
 * Une ligne = un versement d'un élève de l'établissement courant. Le montant est imputé
 * sur le frais indiqué (colonne « Frais », facultative) ou, à défaut, réparti comme au
 * guichet : arriérés d'abord, puis par prochaine échéance ({@see OutstandingFees}).
 * Un montant supérieur au reste dû est refusé. Les doublons (même référence, ou même
 * élève + date + montant + méthode) sont ignorés : ré-importer un fichier est sans effet.
 *
 * Les paiements importés sont « payés », rattachés à la caisse ouverte de l'utilisateur,
 * avec une référence « IMP-… » si le fichier n'en fournit pas. L'écriture comptable
 * est créée automatiquement à l'enregistrement (AccountingSubscriber).
 */
class PaymentImporter
{
    public const HEADERS = [
        'Matricule', 'Montant', 'Date de paiement (JJ/MM/AAAA)', 'Méthode de paiement',
        'Référence', 'Frais (facultatif)', 'Notes',
    ];

    /** Méthodes acceptées : forme normalisée => code. */
    private const METHODS = [
        'especes' => 'espèces', 'espece' => 'espèces', 'cash' => 'espèces', 'liquide' => 'espèces',
        'cheque' => 'chèque',
        'virement' => 'virement', 'virement bancaire' => 'virement',
        'carte' => 'carte', 'carte bancaire' => 'carte', 'cb' => 'carte',
        'mobile money' => 'mobile_money', 'mobile_money' => 'mobile_money', 'mm' => 'mobile_money',
        'orange money' => 'mobile_money', 'om' => 'mobile_money', 'mtn money' => 'mobile_money',
        'momo' => 'mobile_money', 'moov money' => 'mobile_money', 'wave' => 'mobile_money',
        'ecobank' => 'ecobank',
        'bank' => 'bank', 'banque' => 'bank',
    ];

    /** Alias d'en-têtes acceptés par champ (forme normalisée, correspondance exacte). */
    private const ALIASES = [
        'matricule' => ['matricule', 'matricule interne', 'matricule national', 'mle'],
        'amount' => ['montant', 'montant verse', 'somme'],
        'date' => ['date de paiement (jj/mm/aaaa)', 'date de paiement', 'date'],
        'method' => ['methode de paiement', 'methode', 'mode de paiement', 'mode'],
        'reference' => ['reference', 'n° de transaction', 'transaction', 'bordereau'],
        'fee' => ['frais (facultatif)', 'frais'],
        'notes' => ['notes', 'observations', 'commentaire'],
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private StudentRepository $studentRepository,
        private OutstandingFees $outstandingFees,
    ) {
    }

    /**
     * Modèle Excel à remplir : une ligne d'exemple, et une feuille « Aide » listant les
     * méthodes acceptées et les frais de l'établissement.
     */
    public function buildTemplate(School $school): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Paiements');
        $sheet->fromArray(self::HEADERS, null, 'A1');
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        // Colonnes obligatoires surlignées.
        $sheet->getStyle('A1:C1')->getFill()->setFillType('solid')->getStartColor()->setRGB('FFE699');
        $sheet->fromArray(['DEMO-1001', 50000, date('d/m/Y'), 'Espèces', '', '', 'Exemple — à supprimer'], null, 'A2', true);
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $help = $spreadsheet->createSheet();
        $help->setTitle('Aide');
        $help->fromArray([
            ['Colonne', 'Contenu'],
            ['Matricule', 'Matricule interne ou national de l\'élève (obligatoire)'],
            ['Montant', 'Montant versé en F CFA (obligatoire)'],
            ['Date de paiement', 'JJ/MM/AAAA (obligatoire, pas dans le futur)'],
            ['Méthode de paiement', 'Espèces (par défaut), Chèque, Virement, Carte bancaire, Mobile Money, ECOBANK, BANK'],
            ['Référence', 'N° de transaction / bordereau (facultatif ; sert aussi à éviter les doublons)'],
            ['Frais', 'Nom du frais à régler (facultatif ; sinon répartition automatique : arriérés puis échéances)'],
            ['Notes', 'Facultatif'],
            ['', ''],
            ['Frais de l\'établissement', ''],
        ], null, 'A1');
        $help->getStyle('A1:B1')->getFont()->setBold(true);
        $help->getStyle('A10')->getFont()->setBold(true);
        $row = 11;
        foreach ($this->entityManager->getRepository(\App\Entity\Fee::class)->findBy(['school' => $school, 'isActive' => true], ['name' => 'ASC']) as $fee) {
            $help->setCellValue('A' . $row++, $fee->getName());
        }
        $help->getColumnDimension('A')->setAutoSize(true);
        $help->getColumnDimension('B')->setAutoSize(true);
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Analyse le fichier et, si $apply, enregistre les paiements valides (en une fois).
     *
     * @return array{
     *     error: ?string, applied: bool, imported: int, skipped: int, total: float,
     *     rows: list<array{line: int, matricule: string, student: ?string, amount: float, date: ?string,
     *         method: ?string, reference: ?string, allocations: list<array{name: string, amount: float}>,
     *         ok: bool, message: string}>
     * }
     */
    public function import(string $path, School $school, ?int $schoolYearId, ?User $recordedBy, bool $apply, ?CashRegister $cashRegister = null): array
    {
        $result = ['error' => null, 'applied' => $apply, 'imported' => 0, 'skipped' => 0, 'total' => 0.0, 'rows' => []];

        try {
            $rows = IOFactory::load($path)->getSheet(0)->toArray(null, true, false, false);
        } catch (\Throwable $e) {
            $result['error'] = 'Impossible de lire le fichier : ' . $e->getMessage();
            return $result;
        }
        if (\count($rows) < 2) {
            $result['error'] = 'Le fichier ne contient aucune ligne de paiement.';
            return $result;
        }

        $col = $this->mapColumns($rows[0]);
        foreach (['matricule' => 'Matricule', 'amount' => 'Montant', 'date' => 'Date de paiement'] as $field => $label) {
            if (!isset($col[$field])) {
                $result['error'] = sprintf('Colonne « %s » introuvable. Téléchargez le modèle pour le bon format.', $label);
                return $result;
            }
        }

        $cell = static fn (array $row, string $field): ?string => isset($col[$field]) && trim((string) ($row[$col[$field]] ?? '')) !== ''
            ? trim((string) $row[$col[$field]]) : null;

        $today = new \DateTimeImmutable('today');
        $importedOn = (new \DateTime())->format('d/m/Y');
        /** @var array<int, list<array>> $feesByStudent frais dus (reste mis à jour au fil du fichier) */
        $feesByStudent = [];
        $seen = [];
        $toSave = [];

        foreach (\array_slice($rows, 1) as $index => $row) {
            $line = $index + 2;
            $matricule = (string) $cell($row, 'matricule');
            $rawAmount = $row[$col['amount']] ?? null;
            if ($matricule === '' && ($rawAmount === null || trim((string) $rawAmount) === '')) {
                continue; // ligne vide
            }

            $entry = ['line' => $line, 'matricule' => $matricule, 'student' => null, 'amount' => $this->parseAmount($rawAmount),
                'date' => null, 'method' => null, 'reference' => $cell($row, 'reference'), 'allocations' => [], 'ok' => false, 'message' => ''];
            $skip = function (string $message) use (&$entry, &$result): void {
                $entry['message'] = $message;
                $result['skipped']++;
                $result['rows'][] = $entry;
            };

            $student = $matricule !== '' ? $this->studentRepository->findOneByMatriculeInSchools($matricule, [$school->getId()]) : null;
            if (!$student) { $skip($matricule === '' ? 'Matricule manquant.' : 'Élève introuvable dans l\'établissement.'); continue; }
            $entry['student'] = $student->getFullName();

            $amount = round($entry['amount'], 2);
            if ($amount <= 0) { $skip('Montant invalide.'); continue; }

            $date = $this->parseDate($row[$col['date']] ?? null);
            if (!$date) { $skip('Date de paiement invalide (format attendu : JJ/MM/AAAA).'); continue; }
            if ($date > $today) { $skip('Date de paiement dans le futur.'); continue; }
            $entry['date'] = $date->format('d/m/Y');

            $methodRaw = $cell($row, 'method');
            $method = $methodRaw === null ? 'espèces' : (self::METHODS[$this->normalize($methodRaw)] ?? null);
            if (!$method) { $skip(sprintf('Méthode de paiement « %s » inconnue.', $methodRaw)); continue; }
            $entry['method'] = (new Payment())->setPaymentMethod($method)->getPaymentMethodLabel();

            // Doublons : dans le fichier, puis en base (paiement non annulé déjà enregistré).
            $key = $entry['reference'] !== null
                ? $student->getId() . '|ref|' . mb_strtolower($entry['reference'])
                : $student->getId() . '|' . $date->format('Y-m-d') . '|' . $amount . '|' . $method;
            if (isset($seen[$key])) { $skip('Doublon d\'une ligne précédente du fichier.'); continue; }
            if ($this->alreadyRecorded($student, $entry['reference'], $date, $amount, $method)) {
                $skip('Déjà enregistré (même référence, ou même date, montant et méthode).');
                continue;
            }

            // Frais dus, avec le reste mis à jour par les lignes précédentes du même élève.
            $fees = $feesByStudent[$student->getId()] ??= $this->outstandingFees->forStudent($student, $schoolYearId);
            $feeName = $cell($row, 'fee');
            if ($feeName !== null) {
                $wanted = $this->normalize($feeName);
                $fees = array_values(array_filter($fees, fn (array $f) => $this->normalize($f['name']) === $wanted));
                if ($fees === []) { $skip(sprintf('Frais « %s » introuvable ou déjà soldé pour cet élève.', $feeName)); continue; }
            }
            $due = array_sum(array_column($fees, 'remaining'));
            if ($due <= 0) { $skip('Aucun frais restant dû pour cet élève.'); continue; }
            if ($amount > $due + 0.009) {
                $skip(sprintf('Le montant dépasse le reste dû (%s F CFA).', number_format($due, 0, ',', ' ')));
                continue;
            }

            // Répartition en cascade (arriérés, puis échéances) sur les frais retenus.
            $left = $amount;
            $allocations = [];
            foreach ($fees as $f) {
                if ($left <= 0.009) {
                    break;
                }
                $part = round(min($left, $f['remaining']), 2);
                if ($part <= 0) {
                    continue;
                }
                $allocations[] = ['fee' => $f, 'amount' => $part];
                $left = round($left - $part, 2);
            }
            foreach ($feesByStudent[$student->getId()] as &$f) {
                foreach ($allocations as $a) {
                    if ($f['id'] === $a['fee']['id']) {
                        $f['remaining'] = round($f['remaining'] - $a['amount'], 2);
                    }
                }
            }
            unset($f);

            $seen[$key] = true;
            $entry['allocations'] = array_map(static fn (array $a) => ['name' => $a['fee']['name'], 'amount' => $a['amount']], $allocations);
            $entry['ok'] = true;
            $entry['message'] = $apply ? 'Importé' : 'Prêt à importer';
            $result['rows'][] = $entry;
            $result['imported']++;
            $result['total'] += $amount;
            $toSave[] = compact('student', 'amount', 'date', 'method', 'allocations') + [
                'reference' => $entry['reference'],
                'notes' => trim(($cell($row, 'notes') ?? '') . ' [Import du ' . $importedOn . ']'),
            ];
        }

        if ($apply && $toSave !== []) {
            foreach ($toSave as $p) {
                $this->record($p, $recordedBy, $cashRegister);
            }
            // Un seul flush : toutes les lignes valides sont enregistrées ensemble, ou aucune.
            $this->entityManager->flush();
        }

        return $result;
    }

    /**
     * Crée les lignes Payment d'un versement (une par frais imputé), sous un même
     * numéro de reçu et une même référence — comme un encaissement au guichet.
     */
    private function record(array $p, ?User $recordedBy, ?CashRegister $cashRegister): void
    {
        $receiptNumber = 'REC-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $reference = $p['reference'] ?? sprintf('IMP-%s-%04d', date('YmdHis'), random_int(1, 9999));

        foreach ($p['allocations'] as $a) {
            $studentFee = $a['fee']['student_fee'];
            $payment = (new Payment())
                ->setPaymentNumber('PAY-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3))))
                ->setReceiptNumber($receiptNumber)
                ->setReference($reference)
                ->setStudent($p['student'])
                ->setFee($studentFee->getFee())
                ->setStudentFee($studentFee)
                ->setAmount(number_format($a['amount'], 2, '.', ''))
                ->setReceiptAmount(number_format($p['amount'], 2, '.', ''))
                ->setPaymentDate(\DateTime::createFromImmutable($p['date']))
                ->setPaymentMethod($p['method'])
                ->setStatus('payé')
                ->setCashRegister($cashRegister)
                ->setRecordedBy($recordedBy)
                ->setNotes($p['notes']);

            $studentFee->setPaidAmount(number_format((float) $studentFee->getPaidAmount() + $a['amount'], 2, '.', ''));
            $this->entityManager->persist($payment);
        }
    }

    private function alreadyRecorded(Student $student, ?string $reference, \DateTimeImmutable $date, float $amount, string $method): bool
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(Payment::class, 'p')
            ->where('p.student = :student')
            ->andWhere('p.status != :cancelled')
            ->setParameter('student', $student)
            ->setParameter('cancelled', 'annulé');

        if ($reference !== null) {
            $qb->andWhere('LOWER(p.reference) = :reference')->setParameter('reference', mb_strtolower($reference));
        } else {
            $qb->andWhere('p.paymentDate = :date AND p.receiptAmount = :amount AND p.paymentMethod = :method')
                ->setParameter('date', $date->format('Y-m-d'))
                ->setParameter('amount', number_format($amount, 2, '.', ''))
                ->setParameter('method', $method);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * @param array<int, mixed> $headerRow
     * @return array<string, int> champ => index de colonne
     */
    private function mapColumns(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $index => $header) {
            $normalized = $this->normalize($header);
            foreach (self::ALIASES as $field => $aliases) {
                if (!isset($map[$field]) && \in_array($normalized, $aliases, true)) {
                    $map[$field] = (int) $index;
                    break;
                }
            }
        }

        return $map;
    }

    /** Minuscules, sans accents ni espaces superflus. */
    private function normalize(mixed $value): string
    {
        $s = mb_strtolower(trim((string) $value));
        $s = strtr($s, ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c']);

        return preg_replace('/\s+/', ' ', $s) ?? $s;
    }

    /** Nombre, ou texte type « 150 000 » / « 150000,50 F ». */
    private function parseAmount(mixed $raw): float
    {
        if (is_numeric($raw)) {
            return (float) $raw;
        }
        $s = str_replace([' ', "\u{00A0}", "\u{202F}"], '', (string) $raw);
        $s = preg_replace('/[^0-9.,]/', '', str_replace(',', '.', $s)) ?? '';

        return is_numeric($s) ? (float) $s : 0.0;
    }

    /** Date Excel (numéro de série) ou texte JJ/MM/AAAA, JJ-MM-AAAA ou AAAA-MM-JJ. */
    private function parseDate(mixed $raw): ?\DateTimeImmutable
    {
        if ($raw === null || trim((string) $raw) === '') {
            return null;
        }
        if (is_numeric($raw)) {
            try {
                return \DateTimeImmutable::createFromMutable(ExcelDate::excelToDateTimeObject((float) $raw))->setTime(0, 0);
            } catch (\Throwable) {
                return null;
            }
        }
        $text = trim((string) $raw);
        foreach (['j/n/Y', 'j-n-Y', 'Y-n-j', 'j/n/y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $text);
            // Rejette les dates « débordantes » (ex. 31/02) que PHP corrigerait en silence.
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date && !($errors && ($errors['warning_count'] || $errors['error_count']))) {
                return $date;
            }
        }

        return null;
    }
}
