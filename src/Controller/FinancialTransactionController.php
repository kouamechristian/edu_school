<?php

namespace App\Controller;

use App\Entity\FinancialTransaction;
use App\Repository\FinancialTransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/financial-transaction')]
class FinancialTransactionController extends AbstractController
{
    #[Route('/', name: 'admin_financial_transaction_index', methods: ['GET'])]
    public function index(FinancialTransactionRepository $financialTransactionRepository): Response
    {
        return $this->render('financial_transaction/index.html.twig', [
            'financial_transactions' => $financialTransactionRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'admin_financial_transaction_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $financialTransaction = new FinancialTransaction();
        $form = $this->createFormBuilder($financialTransaction)
            ->add('type')
            ->add('amount')
            ->add('description')
            ->add('reference')
            ->add('transactionDate')
            ->add('status')
            ->getForm();
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($financialTransaction);
            $entityManager->flush();

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
        $form = $this->createFormBuilder($financialTransaction)
            ->add('type')
            ->add('amount')
            ->add('description')
            ->add('reference')
            ->add('transactionDate')
            ->add('status')
            ->getForm();
            
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

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
            $entityManager->remove($financialTransaction);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_financial_transaction_index', [], Response::HTTP_SEE_OTHER);
    }
}
