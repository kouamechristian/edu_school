<?php

namespace App\Controller;

use App\Entity\Student;
use App\Repository\ClassroomRepository;
use App\Repository\StudentRepository;
use App\Service\RecouvrementService;
use App\Service\SchoolContextService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/recouvrement', name: 'admin_recouvrement_')]
// ROLE_RECOUVREMENT couvre le module ; ROLE_CAISSE en hérite (cf. security.yaml),
// donc les caissiers/admins/fondateurs y ont aussi accès.
#[IsGranted('ROLE_RECOUVREMENT')]
class RecouvrementController extends AbstractController
{
    private const CATEGORIES = ['scolarite', 'article', 'autre_frais'];

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        StudentRepository $studentRepository,
        ClassroomRepository $classroomRepository,
        RecouvrementService $recouvrementService,
        SchoolContextService $contextService,
        \Knp\Component\Pager\PaginatorInterface $paginator
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();
        $currentYear = $contextService->getCurrentSchoolYear();

        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour accéder au recouvrement.');

            return $this->render('recouvrement/index.html.twig', [
                'current_school' => null,
                'rows' => [],
                'totals' => ['due' => 0, 'paid' => 0, 'balance' => 0, 'a_jour' => 0, 'en_retard' => 0, 'count' => 0],
                'classrooms' => [],
                'filters' => ['classroom' => null, 'category' => null],
            ]);
        }

        [$category, $classroomId, $classrooms, $data] = $this->collect(
            $request,
            $studentRepository,
            $classroomRepository,
            $recouvrementService,
            $currentSchool->getId(),
            $currentYear?->getId()
        );

        return $this->render('recouvrement/index.html.twig', [
            'current_school' => $currentSchool,
            'current_school_year' => $currentYear,
            'rows' => $paginator->paginate($data['rows'], $request->query->getInt('page', 1), 50),
            'totals' => $data['totals'],
            'classrooms' => $classrooms,
            'filters' => ['classroom' => $classroomId, 'category' => $category],
        ]);
    }

    /**
     * Accueil du module : synthèse du recouvrement et répartition par classe.
     */
    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function dashboard(
        StudentRepository $studentRepository,
        RecouvrementService $recouvrementService,
        SchoolContextService $contextService
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();
        $currentYear = $contextService->getCurrentSchoolYear();

        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour accéder au recouvrement.');

            return $this->render('recouvrement/dashboard.html.twig', [
                'current_school' => null,
                'totals' => ['due' => 0, 'paid' => 0, 'balance' => 0, 'a_jour' => 0, 'en_retard' => 0, 'count' => 0],
                'by_classroom' => [],
                'recovery_rate' => 0,
            ]);
        }

        $students = $studentRepository->findForRecouvrement($currentSchool->getId(), $currentYear?->getId());
        $data = $recouvrementService->build($students, null, $currentYear?->getId());

        // Répartition par classe.
        $byClassroom = [];
        foreach ($data['rows'] as $row) {
            $classroom = $row['student']->getClassroom();
            $key = $classroom?->getId() ?? 0;
            if (!isset($byClassroom[$key])) {
                $byClassroom[$key] = [
                    'label' => $classroom?->getName() ?? 'Sans classe',
                    'due' => 0.0, 'paid' => 0.0, 'balance' => 0.0, 'a_jour' => 0, 'en_retard' => 0, 'count' => 0,
                ];
            }
            $byClassroom[$key]['due'] += $row['due'];
            $byClassroom[$key]['paid'] += $row['paid'];
            $byClassroom[$key]['balance'] += $row['balance'];
            $byClassroom[$key]['count']++;
            $byClassroom[$key][$row['status']]++;
        }
        usort($byClassroom, static fn ($a, $b) => strcmp((string) $a['label'], (string) $b['label']));

        $recoveryRate = $data['totals']['due'] > 0
            ? round($data['totals']['paid'] / $data['totals']['due'] * 100, 1)
            : 0;

        return $this->render('recouvrement/dashboard.html.twig', [
            'current_school' => $currentSchool,
            'current_school_year' => $currentYear,
            'totals' => $data['totals'],
            'by_classroom' => $byClassroom,
            'recovery_rate' => $recoveryRate,
        ]);
    }

    /**
     * Relance : liste des élèves en retard de paiement avec contact parent.
     */
    #[Route('/relance', name: 'relance', methods: ['GET'])]
    public function relance(
        Request $request,
        StudentRepository $studentRepository,
        ClassroomRepository $classroomRepository,
        RecouvrementService $recouvrementService,
        SchoolContextService $contextService
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();
        $currentYear = $contextService->getCurrentSchoolYear();

        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour accéder aux relances.');

            return $this->render('recouvrement/relance.html.twig', [
                'current_school' => null,
                'rows' => [],
                'totals' => ['balance' => 0, 'en_retard' => 0],
                'classrooms' => [],
                'filters' => ['classroom' => null],
            ]);
        }

        $classroomId = $request->query->getInt('classroom') ?: null;
        $classrooms = $classroomRepository->findBySchoolAndYear($currentSchool->getId(), $currentYear?->getId());
        $students = $studentRepository->findForRecouvrement($currentSchool->getId(), $currentYear?->getId(), $classroomId);
        $data = $recouvrementService->build($students, null, $currentYear?->getId());

        // Seulement les élèves avec un montant échu impayé (selon l'échéancier),
        // triés par ancienneté du retard puis par montant échu décroissant.
        $rows = array_values(array_filter($data['rows'], static fn (array $r) => $r['overdue'] > 0));
        usort($rows, static function (array $a, array $b): int {
            return $b['days_overdue'] <=> $a['days_overdue']
                ?: $b['overdue'] <=> $a['overdue'];
        });

        return $this->render('recouvrement/relance.html.twig', [
            'current_school' => $currentSchool,
            'current_school_year' => $currentYear,
            'rows' => $rows,
            'totals' => $data['totals'],
            'classrooms' => $classrooms,
            'filters' => ['classroom' => $classroomId],
        ]);
    }

    /**
     * Lettre de relance d'un élève au format PDF (consultable et téléchargeable).
     */
    #[Route('/relance/{id}/lettre', name: 'relance_letter', methods: ['GET'])]
    public function relanceLetter(
        Student $student,
        RecouvrementService $recouvrementService,
        SchoolContextService $contextService
    ): Response {
        $school = $student->getSchool();
        $currentYear = $contextService->getCurrentSchoolYear();

        $situation = $recouvrementService->buildForStudent($student, null, $currentYear?->getId());

        $logoData = null;
        if ($school && $school->getLogo()) {
            $logoPath = $this->getParameter('kernel.project_dir') . '/public/' . ltrim($school->getLogo(), '/');
            if (is_file($logoPath)) {
                $mime = mime_content_type($logoPath) ?: 'image/png';
                $logoData = 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($logoPath));
            }
        }

        $html = $this->renderView('recouvrement/relance_letter_pdf.html.twig', [
            'student' => $student,
            'school' => $school,
            'current_school_year' => $currentYear,
            'logo_data' => $logoData,
            'due' => $situation['due'],
            'paid' => $situation['paid'],
            'balance' => $situation['balance'],
            'overdue' => $situation['overdue'],
            'overdue_count' => $situation['overdue_count'],
            'oldest_due_date' => $situation['oldest_due_date'],
            'days_overdue' => $situation['days_overdue'],
            'next_due_date' => $situation['next_due_date'],
            'next_due_amount' => $situation['next_due_amount'],
            'overdue_details' => $situation['overdue_details'],
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

        $filename = sprintf('RELANCE_%s.pdf', $student->getMatriculeInterne() ?: $student->getId());

        return new Response($dompdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
        ]);
    }

    #[Route('/pdf', name: 'pdf', methods: ['GET'])]
    public function pdf(
        Request $request,
        StudentRepository $studentRepository,
        ClassroomRepository $classroomRepository,
        RecouvrementService $recouvrementService,
        SchoolContextService $contextService
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();
        $currentYear = $contextService->getCurrentSchoolYear();

        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour générer le PDF.');

            return $this->redirectToRoute('admin_recouvrement_index');
        }

        [$category, $classroomId, , $data] = $this->collect(
            $request,
            $studentRepository,
            $classroomRepository,
            $recouvrementService,
            $currentSchool->getId(),
            $currentYear?->getId()
        );

        // Filtre de statut (à jour / en retard) appliqué au rendu PDF uniquement.
        $statusFilter = $request->query->get('status');
        $rows = $data['rows'];
        if (in_array($statusFilter, ['a_jour', 'en_retard'], true)) {
            $rows = array_values(array_filter($rows, fn (array $r) => $r['status'] === $statusFilter));
        }

        $logoData = null;
        if ($currentSchool->getLogo()) {
            $logoPath = $this->getParameter('kernel.project_dir') . '/public/' . ltrim($currentSchool->getLogo(), '/');
            if (is_file($logoPath)) {
                $mime = mime_content_type($logoPath) ?: 'image/png';
                $logoData = 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($logoPath));
            }
        }

        $html = $this->renderView('recouvrement/pdf.html.twig', [
            'school' => $currentSchool,
            'current_school_year' => $currentYear,
            'rows' => $rows,
            'totals' => $data['totals'],
            'logo_data' => $logoData,
            'generated_at' => new \DateTime(),
            'category_label' => $this->categoryLabel($category),
            'status_label' => match ($statusFilter) {
                'a_jour' => 'À jour',
                'en_retard' => 'En retard',
                default => 'Tous',
            },
        ]);

        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = sprintf('RECOUVREMENT_%s.pdf', $currentSchool->getId());

        return new Response($dompdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
        ]);
    }

    /**
     * État des arriérés antérieurs (impayés repris d'une année précédente) :
     * un élève par ligne, avec dû / payé / reste, et un total général.
     */
    #[Route('/arrieres', name: 'arrieres', methods: ['GET'])]
    public function arrieres(
        Request $request,
        \App\Repository\StudentFeeRepository $studentFeeRepository,
        SchoolContextService $contextService,
        \Knp\Component\Pager\PaginatorInterface $paginator
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();

        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour accéder aux arriérés.');

            return $this->render('recouvrement/arrieres.html.twig', [
                'current_school' => null,
                'rows' => [],
                'totals' => ['due' => 0, 'paid' => 0, 'balance' => 0, 'count' => 0],
            ]);
        }

        [$rows, $totals] = $this->buildArrieres($studentFeeRepository, $currentSchool->getId());

        return $this->render('recouvrement/arrieres.html.twig', [
            'current_school' => $currentSchool,
            'current_school_year' => $contextService->getCurrentSchoolYear(),
            'rows' => $paginator->paginate($rows, $request->query->getInt('page', 1), 50),
            'totals' => $totals,
        ]);
    }

    /**
     * Export PDF de l'état des arriérés.
     */
    #[Route('/arrieres/pdf', name: 'arrieres_pdf', methods: ['GET'])]
    public function arrieresPdf(
        \App\Repository\StudentFeeRepository $studentFeeRepository,
        SchoolContextService $contextService
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();

        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour générer le PDF.');

            return $this->redirectToRoute('admin_recouvrement_arrieres');
        }

        [$rows, $totals] = $this->buildArrieres($studentFeeRepository, $currentSchool->getId());

        $logoData = null;
        if ($currentSchool->getLogo()) {
            $logoPath = $this->getParameter('kernel.project_dir') . '/public/' . ltrim($currentSchool->getLogo(), '/');
            if (is_file($logoPath)) {
                $mime = mime_content_type($logoPath) ?: 'image/png';
                $logoData = 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($logoPath));
            }
        }

        $html = $this->renderView('recouvrement/arrieres_pdf.html.twig', [
            'school' => $currentSchool,
            'current_school_year' => $contextService->getCurrentSchoolYear(),
            'rows' => $rows,
            'totals' => $totals,
            'logo_data' => $logoData,
            'generated_at' => new \DateTime(),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return new Response($dompdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="ARRIERES_%s.pdf"', $currentSchool->getId()),
        ]);
    }

    /**
     * Modèle Excel (.xlsx) prêt à remplir pour l'import des arriérés.
     */
    #[Route('/arrieres/modele', name: 'arrieres_modele', methods: ['GET'])]
    public function arrieresModele(): Response
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Arriérés');

        // En-têtes attendus par l'import (détectés par leur libellé).
        $sheet->fromArray([['matricule', 'montant_arriere']], null, 'A1');
        $sheet->getStyle('A1:B1')->getFont()->setBold(true);

        // Lignes d'exemple : matricules volontairement fictifs (préfixe « EX- »).
        // S'ils ne sont pas remplacés, ils seront simplement ignorés à l'import.
        $sheet->fromArray([
            ['EX-2026-00001', 150000],
            ['EX-2026-00002', 75000],
        ], null, 'A2');

        $sheet->getColumnDimension('A')->setWidth(24);
        $sheet->getColumnDimension('B')->setWidth(18);

        $response = new \Symfony\Component\HttpFoundation\StreamedResponse(static function () use ($spreadsheet): void {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
        });
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="modele_arrieres.xlsx"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    /**
     * Import web d'arriérés depuis un fichier .xlsx (même logique que la commande CLI).
     * Réservé à l'établissement courant : les élèves d'un autre établissement sont ignorés.
     */
    #[Route('/arrieres/import', name: 'arrieres_import', methods: ['POST'])]
    public function arrieresImport(
        Request $request,
        \App\Service\ArriereImporter $importer,
        SchoolContextService $contextService
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();
        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement.');

            return $this->redirectToRoute('admin_recouvrement_arrieres');
        }

        if (!$this->isCsrfTokenValid('import_arrieres', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('admin_recouvrement_arrieres');
        }

        /** @var \Symfony\Component\HttpFoundation\File\UploadedFile|null $file */
        $file = $request->files->get('file');
        if (!$file || !$file->isValid()) {
            $this->addFlash('error', 'Aucun fichier valide n\'a été téléversé (vérifiez aussi la taille maximale autorisée).');

            return $this->redirectToRoute('admin_recouvrement_arrieres');
        }

        if (!in_array(strtolower((string) $file->getClientOriginalExtension()), ['xlsx', 'xls'], true)) {
            $this->addFlash('error', 'Format non supporté : téléversez un fichier Excel (.xlsx).');

            return $this->redirectToRoute('admin_recouvrement_arrieres');
        }

        $anneeCible = $contextService->getCurrentSchoolYear()?->getName();
        if (!$anneeCible) {
            $this->addFlash('error', 'Aucune année scolaire courante n\'est définie.');

            return $this->redirectToRoute('admin_recouvrement_arrieres');
        }

        $anneeOrigine = trim((string) $request->request->get('annee_origine')) ?: '2024-2025';

        $result = $importer->import($file->getPathname(), $anneeCible, $anneeOrigine, true, $currentSchool);

        if ($result['error'] !== null) {
            $this->addFlash('error', 'Import impossible : ' . $result['error']);

            return $this->redirectToRoute('admin_recouvrement_arrieres');
        }

        if ($result['imported'] > 0) {
            $this->addFlash('success', sprintf(
                '%d arriéré(s) importé(s).%s',
                $result['imported'],
                $result['skipped'] > 0 ? sprintf(' %d ligne(s) ignorée(s).', $result['skipped']) : ''
            ));
        } else {
            $this->addFlash('warning', sprintf(
                'Aucun arriéré importé (%d ligne(s) ignorée(s)). Vérifiez les matricules et les inscriptions %s.',
                $result['skipped'],
                $anneeCible
            ));
        }

        return $this->redirectToRoute('admin_recouvrement_arrieres');
    }

    /**
     * Agrège les lignes d'arriérés par élève (dû / payé / reste) + total général.
     *
     * @return array{0: list<array{student: Student, origine: ?string, due: float, paid: float, balance: float}>, 1: array{due: float, paid: float, balance: float, count: int}}
     */
    private function buildArrieres(\App\Repository\StudentFeeRepository $studentFeeRepository, int $schoolId): array
    {
        $byStudent = [];
        foreach ($studentFeeRepository->findArrieresBySchool($schoolId) as $sf) {
            $student = $sf->getStudent();
            if ($student === null) {
                continue;
            }
            $key = $student->getId();
            if (!isset($byStudent[$key])) {
                $byStudent[$key] = [
                    'student' => $student,
                    'origine' => $sf->getAnneeOrigine(),
                    'due' => 0.0,
                    'paid' => 0.0,
                    'balance' => 0.0,
                ];
            }
            $byStudent[$key]['due'] += (float) $sf->getAmount();
            $byStudent[$key]['paid'] += (float) $sf->getPaidAmount();
            $byStudent[$key]['balance'] += $sf->getRemainingAmount();
        }

        $rows = array_values($byStudent);

        $totals = ['due' => 0.0, 'paid' => 0.0, 'balance' => 0.0, 'count' => \count($rows)];
        foreach ($rows as $row) {
            $totals['due'] += $row['due'];
            $totals['paid'] += $row['paid'];
            $totals['balance'] += $row['balance'];
        }

        return [$rows, $totals];
    }

    /**
     * Factorise la lecture des filtres et le calcul des lignes de recouvrement.
     *
     * @return array{0: ?string, 1: ?int, 2: array, 3: array}
     */
    private function collect(
        Request $request,
        StudentRepository $studentRepository,
        ClassroomRepository $classroomRepository,
        RecouvrementService $recouvrementService,
        int $schoolId,
        ?int $yearId
    ): array {
        $category = $request->query->get('category');
        if (!in_array($category, self::CATEGORIES, true)) {
            $category = null;
        }

        $classroomId = $request->query->getInt('classroom') ?: null;

        $classrooms = $classroomRepository->findBySchoolAndYear($schoolId, $yearId);
        $students = $studentRepository->findForRecouvrement($schoolId, $yearId, $classroomId);
        $data = $recouvrementService->build($students, $category, $yearId);

        return [$category, $classroomId, $classrooms, $data];
    }

    private function categoryLabel(?string $category): string
    {
        return match ($category) {
            'scolarite' => 'Scolarité',
            'article' => 'Article',
            'autre_frais' => 'Autres frais',
            default => 'Tous les frais',
        };
    }
}
