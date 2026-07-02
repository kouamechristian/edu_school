<?php

namespace App\Controller\Concern;

use App\Entity\School;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rendu de documents téléchargeables (PDF via Dompdf, Excel via PhpSpreadsheet)
 * pour les contrôleurs qui produisent des rapports. Réservé à un
 * {@see \Symfony\Bundle\FrameworkBundle\Controller\AbstractController}.
 */
trait RendersDocuments
{
    /**
     * @param array<string, mixed> $context
     */
    private function renderPdf(string $template, array $context, string $filename, string $orientation = 'portrait'): Response
    {
        $html = $this->renderView($template, $context);

        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();

        return new Response($dompdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
        ]);
    }

    private function streamSpreadsheet(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $response = new StreamedResponse(static function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        });
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    private function logoData(?School $school): ?string
    {
        if (!$school || !$school->getLogo()) {
            return null;
        }
        $path = $this->getParameter('kernel.project_dir') . '/public/' . ltrim($school->getLogo(), '/');
        if (!is_file($path)) {
            return null;
        }
        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
    }
}
