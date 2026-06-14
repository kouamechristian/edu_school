<?php

namespace App\Controller;

use App\Controller\Concern\HandlesEntityDeletion;
use App\Entity\Cycle;
use App\Form\CycleType;
use App\Repository\CycleRepository;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/cycles', name: 'admin_cycle_')]
#[IsGranted('ROLE_DIRECTEUR')]
class CycleController extends AbstractController
{
    use HandlesEntityDeletion;

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(CycleRepository $cycleRepository, SchoolContextService $contextService): Response
    {
        $currentSchool = $contextService->getCurrentSchool();

        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour voir les cycles.');

            return $this->render('cycle/index.html.twig', [
                'cycles' => [],
                'current_school' => null,
            ]);
        }

        return $this->render('cycle/index.html.twig', [
            'cycles' => $cycleRepository->findBySchool($currentSchool->getId()),
            'current_school' => $currentSchool,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SchoolContextService $contextService): Response
    {
        $currentSchool = $contextService->getCurrentSchool();

        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour créer un cycle.');

            return $this->redirectToRoute('admin_cycle_index');
        }

        $cycle = new Cycle();
        $cycle->setSchool($currentSchool);

        $form = $this->createForm(CycleType::class, $cycle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Rattacher à l'établissement courant si non défini.
            if (!$cycle->getSchool()) {
                $cycle->setSchool($currentSchool);
            }

            $entityManager->persist($cycle);
            $entityManager->flush();

            $this->addFlash('success', 'Le cycle a été créé avec succès.');

            return $this->redirectToRoute('admin_cycle_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('cycle/new.html.twig', [
            'cycle' => $cycle,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/show', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Cycle $cycle, SchoolContextService $contextService): Response
    {
        $this->denyAccessUnlessSameSchool($cycle, $contextService);

        return $this->render('cycle/show.html.twig', [
            'cycle' => $cycle,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Cycle $cycle, EntityManagerInterface $entityManager, SchoolContextService $contextService): Response
    {
        $this->denyAccessUnlessSameSchool($cycle, $contextService);

        $form = $this->createForm(CycleType::class, $cycle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Le cycle a été modifié avec succès.');

            return $this->redirectToRoute('admin_cycle_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('cycle/edit.html.twig', [
            'cycle' => $cycle,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Cycle $cycle, EntityManagerInterface $entityManager, SchoolContextService $contextService): Response
    {
        $this->denyAccessUnlessSameSchool($cycle, $contextService);

        if ($this->isCsrfTokenValid('delete' . $cycle->getId(), $request->request->get('_token'))) {
            $this->deleteEntity(
                $entityManager,
                $cycle,
                'Le cycle a été supprimé avec succès.',
                'Suppression impossible : ce cycle est encore lié à des niveaux.'
            );
        }

        return $this->redirectToRoute('admin_cycle_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Empêche d'agir sur un cycle qui n'appartient pas à l'établissement courant.
     */
    private function denyAccessUnlessSameSchool(Cycle $cycle, SchoolContextService $contextService): void
    {
        $currentSchool = $contextService->getCurrentSchool();

        if (!$currentSchool || $cycle->getSchool()?->getId() !== $currentSchool->getId()) {
            throw $this->createNotFoundException('Ce cycle n\'appartient pas à l\'établissement courant.');
        }
    }
}
