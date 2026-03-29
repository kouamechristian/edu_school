<?php

namespace App\Controller;

use App\Entity\Period;
use App\Form\PeriodType;
use App\Repository\PeriodRepository;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/periods')]
class PeriodController extends AbstractController
{
    #[Route('/', name: 'admin_period_index', methods: ['GET'])]
    public function index(
        PeriodRepository $periodRepository,
        SchoolContextService $contextService
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();
        $currentYear = $contextService->getCurrentSchoolYear();
        
        if (!$currentSchool || !$currentYear) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement et une année scolaire.');
            return $this->render('period/index.html.twig', [
                'periods' => [],
                'current_school' => $currentSchool,
                'current_year' => $currentYear,
            ]);
        }

        $periods = $periodRepository->findBySchoolAndYear(
            $currentSchool->getId(),
            $currentYear->getId()
        );

        return $this->render('period/index.html.twig', [
            'periods' => $periods,
            'current_school' => $currentSchool,
            'current_year' => $currentYear,
        ]);
    }

    #[Route('/new', name: 'admin_period_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        SchoolContextService $contextService
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();
        $currentYear = $contextService->getCurrentSchoolYear();
        
        if (!$currentSchool || !$currentYear) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement et une année scolaire.');
            return $this->redirectToRoute('admin_period_index');
        }

        $period = new Period();
        $period->setSchool($currentSchool);
        $period->setSchoolYear($currentYear);
        
        $form = $this->createForm(PeriodType::class, $period);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($period);
            $entityManager->flush();

            $this->addFlash('success', 'La période a été créée avec succès.');
            return $this->redirectToRoute('admin_period_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('period/new.html.twig', [
            'period' => $period,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/show', name: 'admin_period_show', methods: ['GET'])]
    public function show(Period $period): Response
    {
        return $this->render('period/show.html.twig', [
            'period' => $period,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_period_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Period $period,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(PeriodType::class, $period);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'La période a été modifiée avec succès.');
            return $this->redirectToRoute('admin_period_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('period/edit.html.twig', [
            'period' => $period,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_period_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Period $period,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('delete'.$period->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($period);
            $entityManager->flush();

            $this->addFlash('success', 'La période a été supprimée avec succès.');
        }

        return $this->redirectToRoute('admin_period_index', [], Response::HTTP_SEE_OTHER);
    }
}

