<?php

namespace App\Service;

use App\Entity\Payment;
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
     * Génère le PDF et l'enregistre sur disque.
     *
     * @return array{relative_path: string, absolute_path: string}
     */
    public function generateAndStore(Payment $payment): array
    {
        $filename = sprintf('recu_%s.pdf', $payment->getPaymentNumber() ?: ('payment_' . (string) $payment->getId()));
        $relativePath = 'uploads/receipts/' . $filename;
        $absolutePath = rtrim($this->projectDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        $dir = \dirname($absolutePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $html = $this->twig->render('payment/receipt.pdf.html.twig', [
            'payment' => $payment,
        ]);

        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A5', 'portrait');
        $dompdf->render();

        file_put_contents($absolutePath, $dompdf->output());

        return [
            'relative_path' => $relativePath,
            'absolute_path' => $absolutePath,
        ];
    }
}

