<?php

namespace App\Controller;

use App\Controller\Concern\HandlesEntityDeletion;
use App\Entity\FinancialTransaction;
use App\Form\FinancialTransactionType;
use App\Repository\FinancialTransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/financial-transaction')]
#[IsGranted('ROLE_CAISSE')]
class FinancialTransactionController extends AbstractController
{
    use HandlesEntityDeletion;

    #[Route('/', name: 'admin_financial_transaction_index', methods: ['GET'])]
    public function index(
        Request $request,
        FinancialTransactionRepository $financialTransactionRepository,
        \App\Repository\TransactionTypeRepository $transactionTypeRepository
    ): Response {
        $typeId = $request->query->get('type', '');
        $type = $typeId ? $transactionTypeRepository->find($typeId) : null;

        $direction = $request->query->get('direction', '');
        if (!isset(\App\Entity\TransactionType::DIRECTIONS[$direction])) {
            $direction = '';
        }

        $criteria = [];
        if ($type) {
            $criteria['transactionType'] = $type;
        }
        if ($direction !== '') {
            $criteria['type'] = $direction;
        }

        $transactions = $financialTransactionRepository->findBy($criteria, ['transactionDate' => 'DESC', 'id' => 'DESC']);

        return $this->render('financial_transaction/index.html.twig', [
            'financial_transactions' => $transactions,
            'types' => $transactionTypeRepository->findActive(),
            'current_type' => $type ? $type->getId() : '',
            'directions' => \App\Entity\TransactionType::DIRECTIONS,
            'current_direction' => $direction,
        ]);
    }

    #[Route('/new', name: 'admin_financial_transaction_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        \App\Service\SchoolContextService $contextService
    ): Response {
        $financialTransaction = new FinancialTransaction();
        $form = $this->createForm(FinancialTransactionType::class, $financialTransaction);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Numéro de transaction généré automatiquement.
            if (!$financialTransaction->getTransactionNumber()) {
                $financialTransaction->setTransactionNumber($this->generateTransactionNumber($entityManager));
            }

            // Rattache la transaction à l'établissement courant si non défini.
            if ($financialTransaction->getSchool() === null) {
                $financialTransaction->setSchool($contextService->getCurrentSchool());
            }

            $entityManager->persist($financialTransaction);
            $entityManager->flush();

            $this->addFlash('success', 'La transaction a été enregistrée avec succès.');

            return $this->redirectToRoute('admin_financial_transaction_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('financial_transaction/new.html.twig', [
            'financial_transaction' => $financialTransaction,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_financial_transaction_show', methods: ['GET'])]
    public function show(FinancialTransaction $financialTransaction): Response
    {
        return $this->render('financial_transaction/show.html.twig', [
            'financial_transaction' => $financialTransaction,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_financial_transaction_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, FinancialTransaction $financialTransaction, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(FinancialTransactionType::class, $financialTransaction);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'La transaction a été modifiée avec succès.');

            return $this->redirectToRoute('admin_financial_transaction_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('financial_transaction/edit.html.twig', [
            'financial_transaction' => $financialTransaction,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_financial_transaction_delete', methods: ['POST'])]
    public function delete(Request $request, FinancialTransaction $financialTransaction, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$financialTransaction->getId(), $request->request->get('_token'))) {
            $this->deleteEntity(
                $entityManager,
                $financialTransaction,
                'La transaction a été supprimée avec succès.',
                'Suppression impossible : cette transaction est encore liée à d\'autres données.'
            );
        }

        return $this->redirectToRoute('admin_financial_transaction_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Génère un numéro de transaction unique (TXN-AAAAMMJJ-NNNN).
     */
    private function generateTransactionNumber(EntityManagerInterface $entityManager): string
    {
        $prefix = 'TXN-' . date('Ymd') . '-';

        $count = (int) $entityManager->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(FinancialTransaction::class, 't')
            ->where('t.transactionNumber LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->getQuery()
            ->getSingleScalarResult();

        return $prefix . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
