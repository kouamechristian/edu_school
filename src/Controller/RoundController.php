<?php

namespace App\Controller;

use App\Controller\Concern\HandlesEntityDeletion;
use App\Entity\Round;
use App\Form\RoundType;
use App\Repository\CycleRepository;
use App\Repository\RoundRepository;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/rounds', name: 'admin_round_')]
#[IsGranted('ROLE_DIRECTEUR')]
class RoundController extends AbstractController
{
    use HandlesEntityDeletion;

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(RoundRepository $roundRepository, SchoolContextService $contextService, \Symfony\Component\HttpFoundation\Request $request, \Knp\Component\Pager\PaginatorInterface $paginator): Response
    {
        $currentSchool = $contextService->getCurrentSchool();

        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour voir les séries.');

            return $this->render('round/index.html.twig', [
                'rounds' => [],
                'current_school' => null,
            ]);
        }

        return $this->render('round/index.html.twig', [
            'rounds' => $paginator->paginate($roundRepository->findBySchool($currentSchool->getId()), $request->query->getInt('page', 1), 50),
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
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour créer une série.');

            return $this->redirectToRoute('admin_round_index');
        }

        $cycles = $cycleRepository->findBySchool($currentSchool->getId());
        if ($cycles === []) {
            $this->addFlash('warning', 'Veuillez d\'abord créer au moins un cycle pour cet établissement.');

            return $this->redirectToRoute('admin_cycle_index');
        }

        $round = new Round();
        $round->setSchool($currentSchool);

        $form = $this->createForm(RoundType::class, $round, ['cycles' => $cycles]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$round->getSchool()) {
                $round->setSchool($currentSchool);
            }

            $entityManager->persist($round);
            $entityManager->flush();

            $this->addFlash('success', 'La série a été créée avec succès.');

            return $this->redirectToRoute('admin_round_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('round/new.html.twig', [
            'round' => $round,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/show', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Round $round, SchoolContextService $contextService): Response
    {
        $this->denyAccessUnlessSameSchool($round, $contextService);

        return $this->render('round/show.html.twig', [
            'round' => $round,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        Request $request,
        Round $round,
        EntityManagerInterface $entityManager,
        SchoolContextService $contextService,
        CycleRepository $cycleRepository
    ): Response {
        $this->denyAccessUnlessSameSchool($round, $contextService);

        $form = $this->createForm(RoundType::class, $round, [
            'cycles' => $cycleRepository->findBySchool($contextService->getCurrentSchool()?->getId()),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'La série a été modifiée avec succès.');

            return $this->redirectToRoute('admin_round_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('round/edit.html.twig', [
            'round' => $round,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Round $round, EntityManagerInterface $entityManager, SchoolContextService $contextService): Response
    {
        $this->denyAccessUnlessSameSchool($round, $contextService);

        if ($this->isCsrfTokenValid('delete' . $round->getId(), $request->request->get('_token'))) {
            $this->deleteEntity(
                $entityManager,
                $round,
                'La série a été supprimée avec succès.',
                'Suppression impossible : cette série est encore liée à d\'autres données.'
            );
        }

        return $this->redirectToRoute('admin_round_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Empêche d'agir sur une série qui n'appartient pas à l'établissement courant.
     */
    private function denyAccessUnlessSameSchool(Round $round, SchoolContextService $contextService): void
    {
        $currentSchool = $contextService->getCurrentSchool();

        if (!$currentSchool || $round->getSchool()?->getId() !== $currentSchool->getId()) {
            throw $this->createNotFoundException('Cette série n\'appartient pas à l\'établissement courant.');
        }
    }
}
