<?php

namespace App\Controller;

use App\Entity\Fee;
use App\Form\FeeType;
use App\Repository\FeeRepository;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/fees', name: 'admin_fee_')]
#[IsGranted('ROLE_ADMIN')]
class FeeController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, FeeRepository $feeRepository, SchoolContextService $contextService): Response
    {
        // Récupérer l'établissement courant
        $currentSchool = $contextService->getCurrentSchool();
        
        // Si pas d'établissement sélectionné, afficher un message
        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour voir les frais.');
            return $this->render('fee/index.html.twig', [
                'fees' => [],
                'stats' => [],
                'current_type' => null,
                'current_frequency' => null,
                'search_term' => null,
                'current_school' => null,
            ]);
        }

        $schoolId = $currentSchool->getId();

        // Filtres
        $type = $request->query->get('type');
        $frequency = $request->query->get('frequency');
        $search = $request->query->get('search');

        // Filtrer les frais selon l'établissement sélectionné
        if ($search) {
            $fees = $feeRepository->searchByNameOrCode($search);
            $fees = array_filter($fees, function($fee) use ($schoolId) {
                return $fee->getSchool()->getId() === $schoolId;
            });
        } elseif ($type) {
            $fees = $feeRepository->findByType($type);
            $fees = array_filter($fees, function($fee) use ($schoolId) {
                return $fee->getSchool()->getId() === $schoolId;
            });
        } elseif ($frequency) {
            $fees = $feeRepository->findByFrequency($frequency);
            $fees = array_filter($fees, function($fee) use ($schoolId) {
                return $fee->getSchool()->getId() === $schoolId;
            });
        } else {
            $fees = $feeRepository->findBySchool($currentSchool);
        }

        // Statistiques filtrées par établissement
        $stats = [
            'total' => count($fees),
            'by_type' => $feeRepository->countByType(),
            'by_frequency' => $feeRepository->countByFrequency(),
            'total_amount' => $feeRepository->getTotalAmountBySchool($currentSchool)
        ];

        return $this->render('fee/index.html.twig', [
            'fees' => $fees,
            'stats' => $stats,
            'current_type' => $type,
            'current_frequency' => $frequency,
            'search_term' => $search,
            'current_school' => $currentSchool,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $entityManager,
        SchoolContextService $contextService
    ): Response {
        $fee = new Fee();
        $currentSchool = $contextService->getCurrentSchool();
        
        if ($currentSchool) {
            $fee->setSchool($currentSchool);
        }

        $form = $this->createForm(FeeType::class, $fee);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($fee);
            $entityManager->flush();

            $this->addFlash('success', 'Le frais a été créé avec succès.');

            return $this->redirectToRoute('admin_fee_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('fee/new.html.twig', [
            'fee' => $fee,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Fee $fee): Response
    {
        return $this->render('fee/show.html.twig', [
            'fee' => $fee,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request, 
        Fee $fee, 
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(FeeType::class, $fee);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Le frais a été modifié avec succès.');

            return $this->redirectToRoute('admin_fee_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('fee/edit.html.twig', [
            'fee' => $fee,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Fee $fee, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$fee->getId(), $request->request->get('_token'))) {
            $entityManager->remove($fee);
            $entityManager->flush();

            $this->addFlash('success', 'Le frais a été supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_fee_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(Request $request, Fee $fee, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('toggle'.$fee->getId(), $request->request->get('_token'))) {
            $fee->setIsActive(!$fee->isActive());
            $entityManager->flush();

            $status = $fee->isActive() ? 'activé' : 'désactivé';
            $this->addFlash('success', "Le frais a été {$status} avec succès.");
        }

        return $this->redirectToRoute('admin_fee_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/overdue', name: 'overdue', methods: ['GET'])]
    public function overdue(FeeRepository $feeRepository): Response
    {
        $overdueFees = $feeRepository->findOverdue();

        return $this->render('fee/overdue.html.twig', [
            'fees' => $overdueFees,
        ]);
    }

    #[Route('/due-soon', name: 'due_soon', methods: ['GET'])]
    public function dueSoon(FeeRepository $feeRepository): Response
    {
        $dueSoonFees = $feeRepository->findWithDueDateNear(7);

        return $this->render('fee/due_soon.html.twig', [
            'fees' => $dueSoonFees,
        ]);
    }
}
