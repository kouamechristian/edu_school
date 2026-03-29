<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Form\PaymentType;
use App\Repository\PaymentRepository;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/payments', name: 'admin_payment_')]
#[IsGranted('ROLE_ADMIN')]
class PaymentController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, PaymentRepository $paymentRepository, SchoolContextService $contextService): Response
    {
        // Récupérer l'établissement courant
        $currentSchool = $contextService->getCurrentSchool();
        
        // Si pas d'établissement sélectionné, afficher un message
        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour voir les paiements.');
            return $this->render('payment/index.html.twig', [
                'payments' => [],
                'stats' => [],
                'current_status' => null,
                'current_method' => null,
                'search_term' => null,
                'current_school' => null,
            ]);
        }

        // Filtres
        $status = $request->query->get('status');
        $method = $request->query->get('method');
        $search = $request->query->get('search');

        // Filtrer les paiements
        if ($search) {
            $payments = $paymentRepository->searchByNumberOrReference($search);
        } elseif ($status) {
            $payments = $paymentRepository->findByStatus($status);
        } elseif ($method) {
            $payments = $paymentRepository->findByPaymentMethod($method);
        } else {
            $payments = $paymentRepository->findRecent(50);
        }

        // Statistiques
        $stats = [
            'total' => count($payments),
            'by_status' => $paymentRepository->countByStatus(),
            'by_method' => $paymentRepository->countByPaymentMethod(),
            'total_amount' => $paymentRepository->getTotalAmountByDateRange(
                new \DateTime('-30 days'),
                new \DateTime()
            )
        ];

        return $this->render('payment/index.html.twig', [
            'payments' => $payments,
            'stats' => $stats,
            'current_status' => $status,
            'current_method' => $method,
            'search_term' => $search,
            'current_school' => $currentSchool,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $payment = new Payment();
        $form = $this->createForm(PaymentType::class, $payment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Générer un numéro de paiement unique
            $paymentNumber = 'PAY-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $payment->setPaymentNumber($paymentNumber);
            $payment->setRecordedBy($this->getUser());

            $entityManager->persist($payment);
            $entityManager->flush();

            $this->addFlash('success', 'Le paiement a été enregistré avec succès.');

            return $this->redirectToRoute('admin_payment_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('payment/new.html.twig', [
            'payment' => $payment,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Payment $payment): Response
    {
        return $this->render('payment/show.html.twig', [
            'payment' => $payment,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Payment $payment, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PaymentType::class, $payment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Le paiement a été modifié avec succès.');

            return $this->redirectToRoute('admin_payment_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('payment/edit.html.twig', [
            'payment' => $payment,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Payment $payment, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$payment->getId(), $request->request->get('_token'))) {
            $entityManager->remove($payment);
            $entityManager->flush();

            $this->addFlash('success', 'Le paiement a été supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_payment_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/confirm', name: 'confirm', methods: ['POST'])]
    public function confirm(Request $request, Payment $payment, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('confirm'.$payment->getId(), $request->request->get('_token'))) {
            $payment->setStatus('payé');
            $entityManager->flush();

            $this->addFlash('success', 'Le paiement a été confirmé avec succès.');
        }

        return $this->redirectToRoute('admin_payment_show', ['id' => $payment->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'])]
    public function cancel(Request $request, Payment $payment, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('cancel'.$payment->getId(), $request->request->get('_token'))) {
            $payment->setStatus('annulé');
            $entityManager->flush();

            $this->addFlash('success', 'Le paiement a été annulé avec succès.');
        }

        return $this->redirectToRoute('admin_payment_show', ['id' => $payment->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/pending', name: 'pending', methods: ['GET'])]
    public function pending(PaymentRepository $paymentRepository): Response
    {
        $pendingPayments = $paymentRepository->findPending();

        return $this->render('payment/pending.html.twig', [
            'payments' => $pendingPayments,
        ]);
    }

    #[Route('/recent', name: 'recent', methods: ['GET'])]
    public function recent(PaymentRepository $paymentRepository): Response
    {
        $recentPayments = $paymentRepository->findRecent(20);

        return $this->render('payment/recent.html.twig', [
            'payments' => $recentPayments,
        ]);
    }
}
