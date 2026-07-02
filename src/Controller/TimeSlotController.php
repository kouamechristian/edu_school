<?php

namespace App\Controller;

use App\Controller\Concern\HandlesEntityDeletion;
use App\Entity\TimeSlot;
use App\Form\TimeSlotType;
use App\Repository\TimeSlotRepository;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/time-slots', name: 'admin_time_slot_')]
#[IsGranted('ROLE_DIRECTEUR')]
class TimeSlotController extends AbstractController
{
    use HandlesEntityDeletion;

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(TimeSlotRepository $timeSlotRepository, SchoolContextService $contextService): Response
    {
        $currentSchool = $contextService->getCurrentSchool();
        
        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour voir les plages horaires.');
            return $this->render('time_slot/index.html.twig', [
                'time_slots' => [],
                'current_school' => null,
            ]);
        }

        $timeSlots = $timeSlotRepository->findBySchool($currentSchool->getId());

        return $this->render('time_slot/index.html.twig', [
            'time_slots' => $timeSlots,
            'current_school' => $currentSchool,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SchoolContextService $contextService): Response
    {
        $currentSchool = $contextService->getCurrentSchool();
        
        $timeSlot = new TimeSlot();
        
        // Lier à l'établissement sélectionné
        if ($currentSchool) {
            $timeSlot->setSchool($currentSchool);
        }
        
        $form = $this->createForm(TimeSlotType::class, $timeSlot);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Garantir la liaison à l'établissement
            if (!$timeSlot->getSchool() && $currentSchool) {
                $timeSlot->setSchool($currentSchool);
            }
            
            $entityManager->persist($timeSlot);
            $entityManager->flush();

            $this->addFlash('success', 'La plage horaire a été créée avec succès.');

            return $this->redirectToRoute('admin_time_slot_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('time_slot/new.html.twig', [
            'time_slot' => $timeSlot,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/show', name: 'show', methods: ['GET'])]
    public function show(TimeSlot $timeSlot): Response
    {
        return $this->render('time_slot/show.html.twig', [
            'time_slot' => $timeSlot,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TimeSlot $timeSlot, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TimeSlotType::class, $timeSlot);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'La plage horaire a été modifiée avec succès.');

            return $this->redirectToRoute('admin_time_slot_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('time_slot/edit.html.twig', [
            'time_slot' => $timeSlot,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, TimeSlot $timeSlot, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$timeSlot->getId(), $request->request->get('_token'))) {
            $this->deleteEntity(
                $entityManager,
                $timeSlot,
                'La plage horaire a été supprimée avec succès.',
                'Suppression impossible : cette plage horaire est encore utilisée par des cours ou emplois du temps.'
            );
        }

        return $this->redirectToRoute('admin_time_slot_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(Request $request, TimeSlot $timeSlot, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('toggle'.$timeSlot->getId(), $request->request->get('_token'))) {
            $timeSlot->setIsActive(!$timeSlot->isActive());
            $entityManager->flush();

            $status = $timeSlot->isActive() ? 'activée' : 'désactivée';
            $this->addFlash('success', "La plage horaire a été {$status} avec succès.");
        }

        return $this->redirectToRoute('admin_time_slot_index', [], Response::HTTP_SEE_OTHER);
    }
}

