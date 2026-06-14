<?php

namespace App\Controller;

use App\Controller\Concern\HandlesEntityDeletion;
use App\Entity\TransactionType;
use App\Form\TransactionTypeType;
use App\Repository\TransactionTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/transaction-types', name: 'admin_transaction_type_')]
#[IsGranted('ROLE_CAISSE')]
class TransactionTypeController extends AbstractController
{
    use HandlesEntityDeletion;

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(TransactionTypeRepository $repository): Response
    {
        return $this->render('transaction_type/index.html.twig', [
            'transaction_types' => $repository->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $transactionType = new TransactionType();
        $form = $this->createForm(TransactionTypeType::class, $transactionType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($transactionType);
            $entityManager->flush();

            $this->addFlash('success', 'Le type de transaction a été créé avec succès.');

            return $this->redirectToRoute('admin_transaction_type_index');
        }

        return $this->render('transaction_type/new.html.twig', [
            'transaction_type' => $transactionType,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TransactionType $transactionType, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TransactionTypeType::class, $transactionType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Le type de transaction a été modifié avec succès.');

            return $this->redirectToRoute('admin_transaction_type_index');
        }

        return $this->render('transaction_type/edit.html.twig', [
            'transaction_type' => $transactionType,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(Request $request, TransactionType $transactionType, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('toggle'.$transactionType->getId(), $request->request->get('_token'))) {
            $transactionType->setIsActive(!$transactionType->isActive());
            $entityManager->flush();

            $status = $transactionType->isActive() ? 'activé' : 'désactivé';
            $this->addFlash('success', "Le type a été {$status} avec succès.");
        }

        return $this->redirectToRoute('admin_transaction_type_index');
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, TransactionType $transactionType, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$transactionType->getId(), $request->request->get('_token'))) {
            $this->deleteEntity(
                $entityManager,
                $transactionType,
                'Le type de transaction a été supprimé avec succès.',
                'Suppression impossible : ce type est encore utilisé par des transactions. Désactivez-le plutôt.'
            );
        }

        return $this->redirectToRoute('admin_transaction_type_index');
    }
}
