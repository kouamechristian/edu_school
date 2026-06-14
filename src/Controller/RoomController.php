<?php

namespace App\Controller;

use App\Controller\Concern\HandlesEntityDeletion;
use App\Entity\Room;
use App\Form\RoomType;
use App\Repository\RoomRepository;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/rooms')]
#[IsGranted('ROLE_DIRECTEUR')]
class RoomController extends AbstractController
{
    use HandlesEntityDeletion;

    #[Route('/', name: 'admin_room_index', methods: ['GET'])]
    public function index(
        RoomRepository $roomRepository,
        SchoolContextService $contextService,
        Request $request
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();
        
        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour voir les salles.');
            return $this->render('room/index.html.twig', [
                'rooms' => [],
                'current_school' => null,
            ]);
        }

        $schoolId = $currentSchool->getId();
        $search = $request->query->get('search');
        $type = $request->query->get('type');

        if ($search) {
            $rooms = $roomRepository->searchByNameOrCode($search, $schoolId);
        } elseif ($type) {
            $rooms = $roomRepository->findBySchoolAndType($schoolId, $type);
        } else {
            $rooms = $roomRepository->findBySchool($schoolId);
        }

        return $this->render('room/index.html.twig', [
            'rooms' => $rooms,
            'current_school' => $currentSchool,
            'search' => $search,
            'type' => $type,
        ]);
    }

    #[Route('/new', name: 'admin_room_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        SchoolContextService $contextService
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();
        
        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement avant de créer une salle.');
            return $this->redirectToRoute('admin_room_index');
        }

        $room = new Room();
        $room->setSchool($currentSchool);
        
        $form = $this->createForm(RoomType::class, $room);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($room);
            $entityManager->flush();

            $this->addFlash('success', 'La salle a été créée avec succès.');
            return $this->redirectToRoute('admin_room_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('room/new.html.twig', [
            'room' => $room,
            'form' => $form,
            'current_school' => $currentSchool,
        ]);
    }

    #[Route('/{id}/show', name: 'admin_room_show', methods: ['GET'])]
    public function show(Room $room): Response
    {
        return $this->render('room/show.html.twig', [
            'room' => $room,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_room_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Room $room, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(RoomType::class, $room);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'La salle a été modifiée avec succès.');
            return $this->redirectToRoute('admin_room_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('room/edit.html.twig', [
            'room' => $room,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_room_delete', methods: ['POST'])]
    public function delete(Request $request, Room $room, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$room->getId(), $request->getPayload()->getString('_token'))) {
            $this->deleteEntity(
                $entityManager,
                $room,
                'La salle a été supprimée avec succès.',
                'Suppression impossible : cette salle est encore utilisée par des cours ou emplois du temps.'
            );
        }

        return $this->redirectToRoute('admin_room_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/toggle', name: 'admin_room_toggle', methods: ['POST'])]
    public function toggle(Request $request, Room $room, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('toggle'.$room->getId(), $request->getPayload()->getString('_token'))) {
            $room->setIsActive(!$room->isActive());
            $entityManager->flush();

            $status = $room->isActive() ? 'activée' : 'désactivée';
            $this->addFlash('success', "La salle a été {$status} avec succès.");
        }

        return $this->redirectToRoute('admin_room_index', [], Response::HTTP_SEE_OTHER);
    }
}

