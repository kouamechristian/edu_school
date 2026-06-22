<?php

namespace App\Controller;

use App\Entity\GeneratedBulletin;
use App\Entity\Period;
use App\Entity\User;
use App\Repository\ClassroomRepository;
use App\Repository\EvaluationRepository;
use App\Repository\GeneratedBulletinRepository;
use App\Repository\PeriodRepository;
use App\Repository\StudentRepository;
use App\Service\GradeCalculationService;
use Doctrine\ORM\EntityManagerInterface;
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
        StudentRepository $studentRepository,
        GeneratedBulletinRepository $generatedBulletinRepository,
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
                'generated_bulletins' => [],
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
            $students = $this->getStudentsWithAverages($selectedClassroom, $selectedPeriod, $studentRepository, $periodRepository);
        }

        return $this->render('bulletin/index.html.twig', [
            'classrooms' => $classrooms,
            'periods' => $periods,
            'selected_classroom' => $selectedClassroom,
            'selected_period' => $selectedPeriod,
            'students' => $students,
            'current_school' => $currentSchool,
            'current_year' => $currentYear,
            'generated_bulletins' => $generatedBulletinRepository->findBySchoolAndYear(
                $currentSchool->getId(),
                $currentYear->getId()
            ),
        ]);
    }

    #[Route('/generate/{studentId}/{periodId}', name: 'admin_bulletin_generate', methods: ['GET'])]
    public function generate(
        int $studentId,
        int $periodId,
        StudentRepository $studentRepository,
        PeriodRepository $periodRepository
    ): Response {
        $student = $studentRepository->find($studentId);
        $period = $periodRepository->find($periodId);

        if (!$student || !$period) {
            throw $this->createNotFoundException('Élève ou période non trouvé');
        }

        return $this->bulletinPdfResponse($student, $period);
    }

    #[Route('/pdf/{studentId}/{periodId}', name: 'admin_bulletin_pdf', methods: ['GET'])]
    public function generatePdf(
        int $studentId,
        int $periodId,
        StudentRepository $studentRepository,
        PeriodRepository $periodRepository
    ): Response {
        $student = $studentRepository->find($studentId);
        $period = $periodRepository->find($periodId);

        if (!$student || !$period) {
            throw $this->createNotFoundException('Élève ou période non trouvé');
        }

        return $this->bulletinPdfResponse($student, $period);
    }

    /**
     * Construit le bulletin PDF (modèle officiel) d'un élève pour une période.
     */
    private function bulletinPdfResponse(\App\Entity\Student $student, Period $period): Response
    {
        // Classe de l'élève pour l'année de la période (via son inscription),
        // avec repli sur la classe legacy.
        $year = $period->getSchoolYear();
        $registration = $year ? $student->getRegistrationForYear($year) : $student->getLatestRegistration();
        $classroomId = ($registration?->getClassroom() ?? $student->getClassroom())?->getId() ?? 0;
        $sheet = $this->gradeCalculationService->generateBulletinSheet($student, $period, $classroomId);
        $school = $student->getSchool();

        $html = $this->renderView('bulletin/sheet_pdf.html.twig', array_merge($sheet, [
            'photo_data' => $this->imageDataUri($student->getPhoto()),
            'city' => $school && $school->getAddress() ? $school->getAddress() : '',
            'director_role' => 'Directeur des études',
        ]));

        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = sprintf('BULLETIN_%s_%s.pdf', strtoupper((string) $student->getLastName()), $period->getCode());

        return new Response($dompdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
        ]);
    }

    private function imageDataUri(?string $relativePath): ?string
    {
        if (!$relativePath) {
            return null;
        }
        $path = $this->getParameter('kernel.project_dir') . '/public/' . ltrim($relativePath, '/');
        if (!is_file($path)) {
            return null;
        }
        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
    }

    #[Route('/batch-generate', name: 'admin_bulletin_batch_generate', methods: ['POST'])]
    public function batchGenerate(
        Request $request,
        StudentRepository $studentRepository,
        EvaluationRepository $evaluationRepository,
        ClassroomRepository $classroomRepository,
        PeriodRepository $periodRepository,
        GeneratedBulletinRepository $generatedBulletinRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $classroomId = $request->request->getint('classroom');
        $periodId = $request->request->getint('period');

        if (!$classroomId || !$periodId) {
            $this->addFlash('error', 'Veuillez sélectionner une classe et une période.');
            return $this->redirectToRoute('admin_bulletin_index');
        }

        $classroom = $classroomRepository->find($classroomId);
        $period = $periodRepository->find($periodId);
        if (!$classroom || !$period) {
            $this->addFlash('error', 'Classe ou période introuvable.');
            return $this->redirectToRoute('admin_bulletin_index');
        }

        $students = $studentRepository->findActiveByClassroom($classroomId);

        // Générer le bulletin verrouille les notes : les évaluations de cette
        // classe/période ne sont plus modifiables.
        $evaluations = $evaluationRepository->findByClassroomAndPeriod($classroomId, $periodId);
        foreach ($evaluations as $evaluation) {
            $evaluation->setIsValidated(true);
            $evaluation->setLockedByBulletin(true);
        }

        // Enregistrer / mettre à jour la trace de génération.
        $record = $generatedBulletinRepository->findOneByClassroomAndPeriod($classroomId, $periodId) ?? new GeneratedBulletin();
        $record->setClassroom($classroom);
        $record->setPeriod($period);
        $record->setSchoolYear($period->getSchoolYear() ?? $classroom->getSchoolYear());
        $record->setStudentCount(count($students));
        $record->setGeneratedAt(new \DateTime());
        if ($this->getUser() instanceof User) {
            $record->setGeneratedBy($this->getUser());
        }
        if (!$record->getId()) {
            $entityManager->persist($record);
        }

        $entityManager->flush();

        $this->addFlash('success', sprintf(
            'Bulletins générés pour %d élève(s). Les %d évaluation(s) de cette classe/période sont désormais verrouillées.',
            count($students),
            count($evaluations)
        ));

        return $this->redirectToRoute('admin_bulletin_index', [
            'classroom' => $classroomId,
            'period' => $periodId,
        ]);
    }

    private function getStudentsWithAverages(int $classroomId, ?int $periodId, StudentRepository $studentRepository, PeriodRepository $periodRepository): array
    {
        $students = $studentRepository->findActiveByClassroom($classroomId);

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

