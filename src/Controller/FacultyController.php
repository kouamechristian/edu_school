<?php

namespace App\Controller;

use App\Controller\Concern\HandlesEntityDeletion;
use App\Entity\Faculty;
use App\Form\FacultyType;
use App\Repository\CycleRepository;
use App\Repository\FacultyRepository;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/faculties', name: 'admin_faculty_')]
#[IsGranted('ROLE_DIRECTEUR')]
class FacultyController extends AbstractController
{
    use HandlesEntityDeletion;

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(FacultyRepository $facultyRepository, SchoolContextService $contextService, \Symfony\Component\HttpFoundation\Request $request, \Knp\Component\Pager\PaginatorInterface $paginator): Response
    {
        $currentSchool = $contextService->getCurrentSchool();

        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour voir les facultés.');

            return $this->render('faculty/index.html.twig', [
                'faculties' => [],
                'current_school' => null,
            ]);
        }

        return $this->render('faculty/index.html.twig', [
            'faculties' => $paginator->paginate($facultyRepository->findBySchool($currentSchool->getId()), $request->query->getInt('page', 1), 50),
            'current_school' => $currentSchool,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        SchoolContextService $contextService,
        CycleRepository $cycleRepository
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();

        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour créer une faculté.');

            return $this->redirectToRoute('admin_faculty_index');
        }

        $cycles = $cycleRepository->findBySchool($currentSchool->getId());
        if ($cycles === []) {
            $this->addFlash('warning', 'Veuillez d\'abord créer au moins un cycle pour cet établissement.');

            return $this->redirectToRoute('admin_cycle_index');
        }

        $faculty = new Faculty();
        $faculty->setSchool($currentSchool);
        $form = $this->createForm(FacultyType::class, $faculty, ['cycles' => $cycles]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$faculty->getSchool()) {
                $faculty->setSchool($currentSchool);
            }

            $entityManager->persist($faculty);
            $entityManager->flush();

            $this->addFlash('success', 'La faculté a été créée avec succès.');

            return $this->redirectToRoute('admin_faculty_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('faculty/new.html.twig', [
            'faculty' => $faculty,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/show', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Faculty $faculty, SchoolContextService $contextService): Response
    {
        $this->denyAccessUnlessSameSchool($faculty, $contextService);

        return $this->render('faculty/show.html.twig', [
            'faculty' => $faculty,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        Request $request,
        Faculty $faculty,
        EntityManagerInterface $entityManager,
        SchoolContextService $contextService,
        CycleRepository $cycleRepository
    ): Response {
        $this->denyAccessUnlessSameSchool($faculty, $contextService);

        $form = $this->createForm(FacultyType::class, $faculty, [
            'cycles' => $cycleRepository->findBySchool($contextService->getCurrentSchool()?->getId()),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'La faculté a été modifiée avec succès.');

            return $this->redirectToRoute('admin_faculty_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('faculty/edit.html.twig', [
            'faculty' => $faculty,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Faculty $faculty, EntityManagerInterface $entityManager, SchoolContextService $contextService): Response
    {
        $this->denyAccessUnlessSameSchool($faculty, $contextService);

        if ($this->isCsrfTokenValid('delete' . $faculty->getId(), $request->request->get('_token'))) {
            $this->deleteEntity(
                $entityManager,
                $faculty,
                'La faculté a été supprimée avec succès.',
                'Suppression impossible : cette faculté est encore liée à d\'autres données.'
            );
        }

        return $this->redirectToRoute('admin_faculty_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Empêche d'agir sur une faculté qui n'appartient pas à l'établissement courant.
     */
    private function denyAccessUnlessSameSchool(Faculty $faculty, SchoolContextService $contextService): void
    {
        $currentSchool = $contextService->getCurrentSchool();

        $facultySchoolId = $faculty->getSchool()?->getId() ?? $faculty->getCycle()?->getSchool()?->getId();

        if (!$currentSchool || $facultySchoolId !== $currentSchool->getId()) {
            throw $this->createNotFoundException('Cette faculté n\'appartient pas à l\'établissement courant.');
        }
    }
}
