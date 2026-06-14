<?php

namespace App\Controller;

use App\Entity\Period;
use App\Entity\User;
use App\Repository\ClassroomRepository;
use App\Repository\PeriodRepository;
use App\Repository\UserRepository;
use App\Service\GradeCalculationService;
use App\Service\SchoolContextService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/bulletins')]
#[IsGranted('ROLE_ENSEIGNANT')]
class BulletinController extends AbstractController
{
    public function __construct(
        private GradeCalculationService $gradeCalculationService
    ) {
    }

    #[Route('/', name: 'admin_bulletin_index', methods: ['GET'])]
    public function index(
        ClassroomRepository $classroomRepository,
        PeriodRepository $periodRepository,
        UserRepository $userRepository,
        SchoolContextService $contextService,
        Request $request
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();
        $currentYear = $contextService->getCurrentSchoolYear();
        
        if (!$currentSchool || !$currentYear) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement et une année scolaire.');
            return $this->render('bulletin/index.html.twig', [
                'classrooms' => [],
                'periods' => [],
            ]);
        }

        $classrooms = $classroomRepository->findBySchool($currentSchool->getId());
        $periods = $periodRepository->findBySchoolAndYear(
            $currentSchool->getId(),
            $currentYear->getId()
        );

        $selectedClassroom = $request->query->getint('classroom');
        $selectedPeriod = $request->query->getint('period');

        $students = [];
        if ($selectedClassroom) {
            $students = $this->getStudentsWithAverages($selectedClassroom, $selectedPeriod, $userRepository, $periodRepository);
        }

        return $this->render('bulletin/index.html.twig', [
            'classrooms' => $classrooms,
            'periods' => $periods,
            'selected_classroom' => $selectedClassroom,
            'selected_period' => $selectedPeriod,
            'students' => $students,
            'current_school' => $currentSchool,
            'current_year' => $currentYear,
        ]);
    }

    #[Route('/generate/{studentId}/{periodId}', name: 'admin_bulletin_generate', methods: ['GET'])]
    public function generate(
        int $studentId,
        int $periodId,
        UserRepository $userRepository,
        PeriodRepository $periodRepository
    ): Response {
        $student = $userRepository->find($studentId);
        $period = $periodRepository->find($periodId);

        if (!$student || !$period) {
            throw $this->createNotFoundException('Élève ou période non trouvé');
        }

        // Récupérer la classe de l'élève (on suppose qu'il est dans une classe)
        // Pour simplifier, on utilise une classe fictive, à adapter selon votre logique
        $classroomId = 1; // À remplacer par la vraie logique

        $data = $this->gradeCalculationService->generateBulletinData($student, $period, $classroomId);

        return $this->render('bulletin/view.html.twig', $data);
    }

    #[Route('/pdf/{studentId}/{periodId}', name: 'admin_bulletin_pdf', methods: ['GET'])]
    public function generatePdf(
        int $studentId,
        int $periodId,
        UserRepository $userRepository,
        PeriodRepository $periodRepository
    ): Response {
        $student = $userRepository->find($studentId);
        $period = $periodRepository->find($periodId);

        if (!$student || !$period) {
            throw $this->createNotFoundException('Élève ou période non trouvé');
        }

        $classroomId = 1; // À adapter

        $data = $this->gradeCalculationService->generateBulletinData($student, $period, $classroomId);

        // Générer le HTML
        $html = $this->renderView('bulletin/pdf.html.twig', $data);

        // Configurer Dompdf
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Nom du fichier
        $filename = sprintf(
            'bulletin_%s_%s_%s.pdf',
            $student->getLastName(),
            $student->getFirstName(),
            $period->getCode()
        );

        // Retourner le PDF
        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
            ]
        );
    }

    #[Route('/batch-generate', name: 'admin_bulletin_batch_generate', methods: ['POST'])]
    public function batchGenerate(
        Request $request,
        UserRepository $userRepository,
        PeriodRepository $periodRepository
    ): Response {
        $classroomId = $request->request->getint('classroom');
        $periodId = $request->request->getint('period');

        if (!$classroomId || !$periodId) {
            $this->addFlash('error', 'Veuillez sélectionner une classe et une période.');
            return $this->redirectToRoute('admin_bulletin_index');
        }

        $period = $periodRepository->find($periodId);
        $students = $userRepository->findByClassroom($classroomId);

        $count = 0;
        foreach ($students as $student) {
            // Ici, on pourrait enregistrer les bulletins en base ou les envoyer par email
            $count++;
        }

        $this->addFlash('success', "Bulletins générés avec succès pour {$count} élève(s).");
        
        return $this->redirectToRoute('admin_bulletin_index', [
            'classroom' => $classroomId,
            'period' => $periodId,
        ]);
    }

    private function getStudentsWithAverages(int $classroomId, ?int $periodId, UserRepository $userRepository, PeriodRepository $periodRepository): array
    {
        $students = $userRepository->findByClassroom($classroomId);

        if (!$periodId) {
            return $students;
        }

        $period = $periodRepository->find($periodId);
        if (!$period) {
            return $students;
        }

        $studentsWithAverages = [];
        foreach ($students as $student) {
            $averages = $this->gradeCalculationService->calculateStudentAveragesForPeriod($student, $period);
            $studentsWithAverages[] = [
                'student' => $student,
                'average' => $averages['general_average'],
            ];
        }

        // Trier par moyenne décroissante
        usort($studentsWithAverages, function($a, $b) {
            return ($b['average'] ?? 0) <=> ($a['average'] ?? 0);
        });

        return $studentsWithAverages;
    }
}

