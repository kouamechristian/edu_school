<?php

namespace App\Controller;

use App\Entity\Bulletin;
use App\Entity\Period;
use App\Entity\Student;
use App\Entity\User;
use App\Form\BulletinFormType;
use App\Repository\BulletinRepository;
use App\Repository\PeriodRepository;
use App\Repository\StudentRepository;
use App\Service\GradeCalculationService;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
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

    /**
     * Liste des bulletins créés (libellé, niveau, période…), avec un bouton « Nouveau ».
     */
    #[Route('/', name: 'admin_bulletin_index', methods: ['GET'])]
    public function index(
        BulletinRepository $bulletinRepository,
        SchoolContextService $contextService
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();
        $currentYear = $contextService->getCurrentSchoolYear();

        return $this->render('bulletin/index.html.twig', [
            'bulletins' => $currentSchool ? $bulletinRepository->findBySchool($currentSchool->getId()) : [],
            'current_school' => $currentSchool,
            'current_year' => $currentYear,
        ]);
    }

    /**
     * Création d'un bulletin : libellé, moyenne sur, niveau, période. À l'enregistrement,
     * on redirige vers la page listant tous les élèves du niveau choisi.
     */
    #[Route('/new', name: 'admin_bulletin_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        SchoolContextService $contextService
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();
        $currentYear = $contextService->getCurrentSchoolYear();

        if (!$currentSchool || !$currentYear) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement et une année scolaire.');

            return $this->redirectToRoute('admin_bulletin_index');
        }

        $bulletin = new Bulletin();
        $form = $this->createForm(BulletinFormType::class, $bulletin);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $bulletin->setSchool($currentSchool);
            if ($this->getUser() instanceof User) {
                $bulletin->setCreatedBy($this->getUser());
            }

            $entityManager->persist($bulletin);
            $entityManager->flush();

            $this->addFlash('success', 'Bulletin créé. Voici les élèves du niveau sélectionné.');

            return $this->redirectToRoute('admin_bulletin_show', ['id' => $bulletin->getId()]);
        }

        return $this->render('bulletin/new.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Page d'un bulletin : tableau de tous les élèves du niveau choisi, avec leur
     * moyenne générale pour la période (ramenée sur la base « moyenne sur »).
     */
    #[Route('/{id}', name: 'admin_bulletin_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(
        Bulletin $bulletin,
        StudentRepository $studentRepository
    ): Response {
        $level = $bulletin->getLevel();
        $period = $bulletin->getPeriod();
        $school = $bulletin->getSchool();
        $base = max(1, (int) $bulletin->getMoyenneSur());

        $students = ($level && $school)
            ? $studentRepository->findActiveBySchoolAndLevel($school->getId(), $level->getId())
            : [];

        $rows = [];
        foreach ($students as $student) {
            $averages = $period ? $this->gradeCalculationService->calculateStudentAveragesForPeriod($student, $period) : ['general_average' => null];
            $avg20 = $averages['general_average'];               // moyenne sur 20
            $scaled = $avg20 !== null ? round($avg20 * $base / 20, 2) : null;

            $rows[] = [
                'student' => $student,
                'classroom' => $student->getClassroom(),
                'average' => $scaled,
                'has_grades' => $avg20 !== null,
            ];
        }

        // Classement par moyenne décroissante (les non notés à la fin).
        usort($rows, fn ($a, $b) => ($b['average'] ?? -1) <=> ($a['average'] ?? -1));

        return $this->render('bulletin/show.html.twig', [
            'bulletin' => $bulletin,
            'rows' => $rows,
            'base' => $base,
        ]);
    }

    /**
     * Suppression d'un bulletin.
     */
    #[Route('/{id}', name: 'admin_bulletin_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(
        Request $request,
        Bulletin $bulletin,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $bulletin->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($bulletin);
            $entityManager->flush();
            $this->addFlash('success', 'Bulletin supprimé.');
        }

        return $this->redirectToRoute('admin_bulletin_index');
    }

    /**
     * Bulletin PDF (modèle officiel) d'un élève pour une période.
     */
    #[Route('/pdf/{studentId}/{periodId}', name: 'admin_bulletin_pdf', requirements: ['studentId' => '\d+', 'periodId' => '\d+'], methods: ['GET'])]
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
    private function bulletinPdfResponse(Student $student, Period $period): Response
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
}
