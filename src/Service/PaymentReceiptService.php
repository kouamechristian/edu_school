<?php

namespace App\Service;

use App\Entity\Payment;
use App\Entity\School;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

class PaymentReceiptService
{
    public function __construct(
        private Environment $twig,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    /**
     * Génère le reçu PDF à la volée et renvoie son contenu binaire (aucune écriture
     * sur disque : le reçu est simplement affiché dans le navigateur).
     */
    public function render(Payment $payment): string
    {
        $student = $payment->getStudent();
        $school = $student?->getSchool();
        $paidAmount = (float) $payment->getAmount();

        $html = $this->twig->render('payment/receipt.pdf.html.twig', [
            'payment' => $payment,
            'student' => $student,
            'school' => $school,
            'logo_data' => $this->buildLogoData($school),
            'sections' => $this->buildFeeStatement($payment),
            'amount_in_words' => $this->amountInWords($paidAmount),
            'total_due' => $student?->getTotalTuition() ?? 0.0,
            'total_paid' => $student?->getTotalPaid() ?? 0.0,
            'total_balance' => $student?->getRemainingTuition() ?? 0.0,
            'generated_at' => new \DateTime(),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * Construit le relevé détaillé : une section par frais affecté à l'élève, avec
     * une ligne par échéance (ou une ligne unique si le frais n'a pas d'échéancier).
     * L'imputation correspond à la part de CE paiement affectée à chaque ligne.
     *
     * @return list<array{
     *     title: string,
     *     rows: list<array{rubrique: string, echeance: ?\DateTimeInterface, montant: float, imputation: float, regle: float, solde: float}>
     * }>
     */
    private function buildFeeStatement(Payment $payment): array
    {
        $student = $payment->getStudent();
        if (!$student) {
            return [];
        }

        $paidFeeId = $payment->getFee()?->getId();
        $paymentAmount = (float) $payment->getAmount();
        $sections = [];

        foreach ($student->getStudentFees() as $studentFee) {
            $fee = $studentFee->getFee();
            if (!$fee || !$fee->isActive()) {
                continue;
            }

            $isPaidFee = $paidFeeId !== null && $fee->getId() === $paidFeeId;
            $paidAfter = (float) $studentFee->getPaidAmount();
            // Ce qui a été imputé par CE paiement à ce frais.
            $imputed = $isPaidFee ? $paymentAmount : 0.0;
            $paidBefore = max(0.0, $paidAfter - $imputed);

            $schedules = $fee->getSchedules();
            $rows = [];

            if (\count($schedules) > 0) {
                $scheduleAmounts = [];
                foreach ($schedules as $schedule) {
                    $scheduleAmounts[] = (float) $schedule->getAmount();
                }
                $regleAfter = $this->waterfall($paidAfter, $scheduleAmounts);
                $regleBefore = $this->waterfall($paidBefore, $scheduleAmounts);

                $i = 0;
                foreach ($schedules as $schedule) {
                    $montant = (float) $schedule->getAmount();
                    $rows[] = [
                        'rubrique' => $fee->getName(),
                        'echeance' => $schedule->getDueDate(),
                        'montant' => $montant,
                        'imputation' => max(0.0, $regleAfter[$i] - $regleBefore[$i]),
                        'regle' => $regleAfter[$i],
                        'solde' => max(0.0, $montant - $regleAfter[$i]),
                    ];
                    $i++;
                }
            } else {
                $montant = (float) $studentFee->getAmount();
                $rows[] = [
                    'rubrique' => $fee->getName(),
                    'echeance' => null,
                    'montant' => $montant,
                    'imputation' => min($imputed, $montant),
                    'regle' => $paidAfter,
                    'solde' => $studentFee->getRemainingAmount(),
                ];
            }

            $sections[] = [
                'title' => $fee->getName(),
                'rows' => $rows,
            ];
        }

        return $sections;
    }

    /**
     * Répartit un montant payé sur une suite d'échéances, en cascade (les plus
     * anciennes d'abord).
     *
     * @param list<float> $scheduleAmounts
     * @return list<float>
     */
    private function waterfall(float $amount, array $scheduleAmounts): array
    {
        $result = [];
        $remaining = $amount;
        foreach ($scheduleAmounts as $scheduleAmount) {
            $allocated = min($scheduleAmount, max(0.0, $remaining));
            $result[] = $allocated;
            $remaining -= $allocated;
        }

        return $result;
    }

    private function buildLogoData(?School $school): ?string
    {
        if (!$school || !$school->getLogo()) {
            return null;
        }

        $logoPath = rtrim($this->projectDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'public'
            . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $school->getLogo()), DIRECTORY_SEPARATOR);

        if (!is_file($logoPath)) {
            return null;
        }

        $mime = mime_content_type($logoPath) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($logoPath));
    }

    /**
     * Montant en toutes lettres + « francs CFA » (les CFA n'ayant pas de centimes).
     */
    private function amountInWords(float $amount): string
    {
        $integer = (int) round($amount);
        $words = $this->numberToFrenchWords($integer);

        return $words . ' francs CFA';
    }

    private function numberToFrenchWords(int $number): string
    {
        if ($number <= 0) {
            return 'zéro';
        }

        $parts = [];
        $millions = intdiv($number, 1_000_000);
        $thousands = intdiv($number % 1_000_000, 1000);
        $rest = $number % 1000;

        if ($millions > 0) {
            $parts[] = $millions === 1
                ? 'un million'
                : $this->below1000($millions, false) . ' millions';
        }

        if ($thousands > 0) {
            $parts[] = $thousands === 1
                ? 'mille'
                : $this->below1000($thousands, false) . ' mille';
        }

        if ($rest > 0) {
            $parts[] = $this->below1000($rest, true);
        }

        return implode(' ', $parts);
    }

    /**
     * Convertit un entier 1..999 en toutes lettres.
     *
     * @param bool $isFinal Vrai si ce groupe termine le nombre (gère le pluriel de
     *                      « cents » et « quatre-vingts », invariables devant mille/million).
     */
    private function below1000(int $n, bool $isFinal): string
    {
        $units = [
            '', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
            'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize',
            'dix-sept', 'dix-huit', 'dix-neuf',
        ];
        $tens = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante', 'quatre-vingt', 'quatre-vingt'];

        if ($n < 20) {
            return $units[$n];
        }

        if ($n < 100) {
            $t = intdiv($n, 10);
            $u = $n % 10;

            // 70-79 et 90-99 : base soixante / quatre-vingt + (10..19).
            if ($t === 7 || $t === 9) {
                $sep = ($t === 7 && $u === 1) ? '-et-' : '-';
                return $tens[$t] . $sep . $units[10 + $u];
            }

            if ($u === 0) {
                // 80 seul : « quatre-vingts » (pluriel si final), sinon « quatre-vingt ».
                if ($t === 8) {
                    return $isFinal ? 'quatre-vingts' : 'quatre-vingt';
                }
                return $tens[$t];
            }

            if ($u === 1 && $t >= 2 && $t <= 6) {
                return $tens[$t] . '-et-un';
            }

            return $tens[$t] . '-' . $units[$u];
        }

        // 100..999
        $h = intdiv($n, 100);
        $rem = $n % 100;

        if ($rem === 0) {
            if ($h === 1) {
                return 'cent';
            }
            return $units[$h] . ($isFinal ? ' cents' : ' cent');
        }

        $cent = $h === 1 ? 'cent' : $units[$h] . ' cent';

        return $cent . ' ' . $this->below1000($rem, $isFinal);
    }
}
