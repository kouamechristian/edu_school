<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\TeacherSubjectsType;
use App\Repository\SubjectRepository;
use App\Repository\UserRepository;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Espace Enseignants (Académique) : attribution des matières enseignées.
 */
#[Route('/admin/teachers', name: 'admin_teacher_')]
#[IsGranted('ROLE_DIRECTEUR')]
class TeacherController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(UserRepository $userRepository, SchoolContextService $contextService, \Symfony\Component\HttpFoundation\Request $request, \Knp\Component\Pager\PaginatorInterface $paginator): Response
    {
        $school = $contextService->getCurrentSchool();

        $teachers = $school
            ? $userRepository->findByTypeInSchool('enseignant', $school->getId())
            : $userRepository->findByType('enseignant');
        $teachers = $paginator->paginate($teachers, $request->query->getInt('page', 1), 50);

        return $this->render('teacher/index.html.twig', [
            'teachers' => $teachers,
            'current_school' => $school,
        ]);
    }

    /**
     * Génère la liste des enseignants de l'établissement au format PDF.
     */
    #[Route('/pdf', name: 'pdf', methods: ['GET'])]
    public function pdf(UserRepository $userRepository, SchoolContextService $contextService): Response
    {
        $school = $contextService->getCurrentSchool();

        $teachers = $school
            ? $userRepository->findByTypeInSchool('enseignant', $school->getId())
            : $userRepository->findByType('enseignant');

        // Logo embarqué en base64 (Dompdf lit ainsi l'image sans accès disque/URL).
        $logoData = null;
        if ($school && $school->getLogo()) {
            $logoPath = $this->getParameter('kernel.project_dir') . '/public/' . ltrim($school->getLogo(), '/');
            if (is_file($logoPath)) {
                $mime = mime_content_type($logoPath) ?: 'image/png';
                $logoData = 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($logoPath));
            }
        }

        $html = $this->renderView('teacher/index_pdf.html.twig', [
            'teachers' => $teachers,
            'school' => $school,
            'logo_data' => $logoData,
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

        $filename = sprintf('LISTE_ENSEIGNANTS_%s.pdf', $school ? $school->getId() : 'tous');

        return new Response($dompdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
        ]);
    }

    #[Route('/{id}/subjects', name: 'subjects', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function subjects(
        User $teacher,
        Request $request,
        SubjectRepository $subjectRepository,
        SchoolContextService $contextService,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($teacher->getUserType() !== 'enseignant') {
            throw $this->createNotFoundException('Cet utilisateur n\'est pas un enseignant.');
        }

        $school = $contextService->getCurrentSchool();
        // Matières de l'établissement ; repli sur toutes les matières actives (matières globales).
        $subjects = $school ? $subjectRepository->findBySchool($school->getId()) : [];
        if ($subjects === []) {
            $subjects = $subjectRepository->findActive();
        }

        $form = $this->createForm(TeacherSubjectsType::class, $teacher, ['subjects' => $subjects]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', sprintf('Matières de %s mises à jour.', $teacher->getFullName()));

            return $this->redirectToRoute('admin_teacher_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('teacher/subjects.html.twig', [
            'teacher' => $teacher,
            'form' => $form,
        ]);
    }
}
