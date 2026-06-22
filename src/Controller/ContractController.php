<?php

namespace App\Controller;

use App\Controller\Concern\HandlesEntityDeletion;
use App\Entity\Contract;
use App\Entity\Employee;
use App\Form\ContractType;
use App\Repository\ContractRepository;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/hr/contracts')]
#[IsGranted('ROLE_RH')]
class ContractController extends AbstractController
{
    use HandlesEntityDeletion;

    #[Route('/', name: 'admin_contract_index', methods: ['GET'])]
    public function index(
        ContractRepository $contractRepository,
        SchoolContextService $contextService,
        Request $request,
        \Knp\Component\Pager\PaginatorInterface $paginator
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();

        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour voir les contrats.');

            return $this->render('contract/index.html.twig', [
                'contracts' => [],
                'current_school' => null,
            ]);
        }

        $status = $request->query->get('status');
        $contracts = $paginator->paginate($contractRepository->findBySchool($currentSchool->getId(), $status), $request->query->getInt('page', 1), 50);

        return $this->render('contract/index.html.twig', [
            'contracts' => $contracts,
            'current_school' => $currentSchool,
            'status' => $status,
        ]);
    }

    #[Route('/new', name: 'admin_contract_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        ContractRepository $contractRepository,
        SchoolContextService $contextService
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();

        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement avant de créer un contrat.');

            return $this->redirectToRoute('admin_contract_index');
        }

        $contract = new Contract();

        // Pré-sélection éventuelle de l'employé (depuis sa fiche).
        if ($employeeId = $request->query->getInt('employee')) {
            if ($employee = $entityManager->getRepository(Employee::class)->find($employeeId)) {
                $contract->setEmployee($employee);
            }
        }

        $form = $this->createForm(ContractType::class, $contract, ['school' => $currentSchool]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contract->setReference($contractRepository->getNextReference());
            $entityManager->persist($contract);
            $entityManager->flush();

            $this->addFlash('success', 'Le contrat a été créé avec succès.');

            return $this->redirectToRoute('admin_contract_show', ['id' => $contract->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('contract/new.html.twig', [
            'contract' => $contract,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/show', name: 'admin_contract_show', methods: ['GET'])]
    public function show(Contract $contract): Response
    {
        return $this->render('contract/show.html.twig', [
            'contract' => $contract,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_contract_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Contract $contract,
        EntityManagerInterface $entityManager,
        SchoolContextService $contextService
    ): Response {
        $form = $this->createForm(ContractType::class, $contract, [
            'school' => $contextService->getCurrentSchool(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Le contrat a été modifié avec succès.');

            return $this->redirectToRoute('admin_contract_show', ['id' => $contract->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('contract/edit.html.twig', [
            'contract' => $contract,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_contract_delete', methods: ['POST'])]
    public function delete(Request $request, Contract $contract, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $contract->getId(), $request->getPayload()->getString('_token'))) {
            $this->deleteEntity(
                $entityManager,
                $contract,
                'Le contrat a été supprimé avec succès.'
            );
        }

        return $this->redirectToRoute('admin_contract_index', [], Response::HTTP_SEE_OTHER);
    }
}
