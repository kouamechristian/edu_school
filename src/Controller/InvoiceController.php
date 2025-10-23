<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Form\InvoiceType;
use App\Repository\InvoiceRepository;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/invoices', name: 'admin_invoice_')]
#[IsGranted('ROLE_ADMIN')]
class InvoiceController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, InvoiceRepository $invoiceRepository, SchoolContextService $contextService): Response
    {
        // Récupérer l'établissement courant
        $currentSchool = $contextService->getCurrentSchool();
        
        // Si pas d'établissement sélectionné, afficher un message
        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour voir les factures.');
            return $this->render('invoice/index.html.twig', [
                'invoices' => [],
                'stats' => [],
                'current_status' => null,
                'search_term' => null,
                'current_school' => null,
            ]);
        }

        // Filtres
        $status = $request->query->get('status');
        $search = $request->query->get('search');

        // Filtrer les factures
        if ($search) {
            $invoices = $invoiceRepository->searchByNumber($search);
        } elseif ($status) {
            $invoices = $invoiceRepository->findByStatus($status);
        } else {
            $invoices = $invoiceRepository->findRecent(50);
        }

        // Statistiques
        $stats = [
            'total' => count($invoices),
            'by_status' => $invoiceRepository->countByStatus(),
            'total_amount' => $invoiceRepository->getTotalAmountByDateRange(
                new \DateTime('-30 days'),
                new \DateTime()
            )
        ];

        return $this->render('invoice/index.html.twig', [
            'invoices' => $invoices,
            'stats' => $stats,
            'current_status' => $status,
            'search_term' => $search,
            'current_school' => $currentSchool,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $invoice = new Invoice();
        $form = $this->createForm(InvoiceType::class, $invoice);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Générer un numéro de facture unique
            $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $invoice->setInvoiceNumber($invoiceNumber);
            $invoice->setCreatedBy($this->getUser());

            // Calculer le montant restant
            $totalAmount = (float) $invoice->getTotalAmount();
            $paidAmount = (float) $invoice->getPaidAmount();
            $invoice->setRemainingAmount($totalAmount - $paidAmount);

            $entityManager->persist($invoice);
            $entityManager->flush();

            $this->addFlash('success', 'La facture a été créée avec succès.');

            return $this->redirectToRoute('admin_invoice_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('invoice/new.html.twig', [
            'invoice' => $invoice,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Invoice $invoice): Response
    {
        return $this->render('invoice/show.html.twig', [
            'invoice' => $invoice,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Invoice $invoice, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(InvoiceType::class, $invoice);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Recalculer le montant restant
            $totalAmount = (float) $invoice->getTotalAmount();
            $paidAmount = (float) $invoice->getPaidAmount();
            $invoice->setRemainingAmount($totalAmount - $paidAmount);

            $entityManager->flush();

            $this->addFlash('success', 'La facture a été modifiée avec succès.');

            return $this->redirectToRoute('admin_invoice_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('invoice/edit.html.twig', [
            'invoice' => $invoice,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Invoice $invoice, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$invoice->getId(), $request->request->get('_token'))) {
            $entityManager->remove($invoice);
            $entityManager->flush();

            $this->addFlash('success', 'La facture a été supprimée avec succès.');
        }

        return $this->redirectToRoute('admin_invoice_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/send', name: 'send', methods: ['POST'])]
    public function send(Request $request, Invoice $invoice, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('send'.$invoice->getId(), $request->request->get('_token'))) {
            $invoice->setStatus('envoyée');
            $entityManager->flush();

            $this->addFlash('success', 'La facture a été envoyée avec succès.');
        }

        return $this->redirectToRoute('admin_invoice_show', ['id' => $invoice->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/mark-paid', name: 'mark_paid', methods: ['POST'])]
    public function markPaid(Request $request, Invoice $invoice, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('mark-paid'.$invoice->getId(), $request->request->get('_token'))) {
            $invoice->setStatus('payée');
            $invoice->setPaidAmount($invoice->getTotalAmount());
            $invoice->setRemainingAmount('0.00');
            $entityManager->flush();

            $this->addFlash('success', 'La facture a été marquée comme payée avec succès.');
        }

        return $this->redirectToRoute('admin_invoice_show', ['id' => $invoice->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'])]
    public function cancel(Request $request, Invoice $invoice, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('cancel'.$invoice->getId(), $request->request->get('_token'))) {
            $invoice->setStatus('annulée');
            $entityManager->flush();

            $this->addFlash('success', 'La facture a été annulée avec succès.');
        }

        return $this->redirectToRoute('admin_invoice_show', ['id' => $invoice->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/overdue', name: 'overdue', methods: ['GET'])]
    public function overdue(InvoiceRepository $invoiceRepository): Response
    {
        $overdueInvoices = $invoiceRepository->findOverdue();

        return $this->render('invoice/overdue.html.twig', [
            'invoices' => $overdueInvoices,
        ]);
    }

    #[Route('/due-soon', name: 'due_soon', methods: ['GET'])]
    public function dueSoon(InvoiceRepository $invoiceRepository): Response
    {
        $dueSoonInvoices = $invoiceRepository->findWithDueDateNear(7);

        return $this->render('invoice/due_soon.html.twig', [
            'invoices' => $dueSoonInvoices,
        ]);
    }

    #[Route('/{id}/pdf', name: 'pdf', methods: ['GET'])]
    public function generatePdf(Invoice $invoice): Response
    {
        // TODO: Implémenter la génération de PDF
        $this->addFlash('info', 'Génération de PDF en cours de développement.');
        
        return $this->redirectToRoute('admin_invoice_show', ['id' => $invoice->getId()], Response::HTTP_SEE_OTHER);
    }
}
