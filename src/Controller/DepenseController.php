<?php

namespace App\Controller;

use App\Controller\Concern\HandlesEntityDeletion;
use App\Entity\Depense;
use App\Entity\User;
use App\Form\DepenseType;
use App\Repository\CashDepositRepository;
use App\Repository\CashRegisterRepository;
use App\Repository\DepenseRepository;
use App\Repository\PaymentRepository;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Dépenses (sorties d'argent) effectuées depuis la caisse.
 *
 * Une dépense n'est possible que si la caisse du caissier est ouverte ET autorisée aux
 * dépenses par le fondateur (expenseAuthorized). Elle diminue immédiatement le solde.
 */
#[Route('/admin/depenses', name: 'admin_depense_')]
#[IsGranted('ROLE_CAISSE')]
class DepenseController extends AbstractController
{
    use HandlesEntityDeletion;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SchoolContextService $schoolContextService,
    ) {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(DepenseRepository $depenseRepository, PaginatorInterface $paginator, Request $request): Response
    {
        $school = $this->schoolContextService->getCurrentSchool();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour voir les dépenses.');
            return $this->redirectToRoute('admin_student_index');
        }

        $depenses = $paginator->paginate(
            $depenseRepository->findBySchool($school->getId()),
            $request->query->getInt('page', 1),
            50
        );
        $total = $depenseRepository->getConfirmedTotalForSchool($school->getId());

        return $this->render('depense/index.html.twig', [
            'depenses' => $depenses,
            'total_confirme' => $total,
            'current_school' => $school,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        CashRegisterRepository $cashRegisterRepository,
        PaymentRepository $paymentRepository,
        CashDepositRepository $cashDepositRepository,
        DepenseRepository $depenseRepository
    ): Response {
        $school = $this->schoolContextService->getCurrentSchool();
        $cashier = $this->getUser();

        if (!$school || !$cashier instanceof User) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement avant d\'enregistrer une dépense.');
            return $this->redirectToRoute('admin_depense_index');
        }

        $cashRegister = $cashRegisterRepository->findOpenForCashier($school, $cashier);
        if (!$cashRegister) {
            $this->addFlash('warning', 'Vous devez ouvrir votre caisse avant d\'enregistrer une dépense.');
            return $this->redirectToRoute('admin_cash_register_index');
        }

        if (!$cashRegister->isExpenseAuthorized()) {
            $this->addFlash('warning', 'Le fondateur ne vous a pas (encore) autorisé à effectuer des dépenses depuis votre caisse.');
            return $this->redirectToRoute('admin_cash_register_index');
        }

        // Solde officiel de la caisse : versements déduits seulement une fois approuvés.
        $balance = (float) $cashRegister->getOpeningBalance()
            + $paymentRepository->getTotalAmountByCashRegister($cashRegister->getId())
            - $cashDepositRepository->getApprovedTotalByCashRegister($cashRegister->getId())
            - $depenseRepository->getTotalByCashRegister($cashRegister->getId());

        $depense = new Depense();
        // IMPORTANT : la validation du formulaire s'exécute PENDANT handleRequest()
        // (événement POST_SUBMIT), pas à isValid(). Les champs hors-formulaire (caisse,
        // école, caissier) doivent donc être posés AVANT handleRequest, sinon le NotNull
        // sur la caisse échoue (« La caisse est obligatoire »).
        $depense->setCashRegister($cashRegister)
            ->setSchool($school)
            ->setRecordedBy($cashier);

        $form = $this->createForm(DepenseType::class, $depense);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $amount = (float) $depense->getAmount();
            $depense->setAmount((string) number_format($amount, 2, '.', ''))
                ->setNumero($this->generateNumero())
                ->setStatus('confirmée');

            $this->entityManager->persist($depense);
            $this->entityManager->flush();

            $newBalance = $balance - $amount;
            $this->addFlash('success', sprintf('Dépense de %s F enregistrée. Nouveau solde : %s F.', number_format($amount, 0, ',', ' '), number_format($newBalance, 0, ',', ' ')));
            if ($newBalance < 0) {
                $this->addFlash('warning', 'Attention : le solde de la caisse est désormais négatif (la dépense dépasse l\'encaisse disponible).');
            }

            return $this->redirectToRoute('admin_depense_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('depense/new.html.twig', [
            'form' => $form,
            'current_balance' => $balance,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Depense $depense): Response
    {
        $form = $this->createForm(DepenseType::class, $depense);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'La dépense a été modifiée avec succès.');
            return $this->redirectToRoute('admin_depense_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('depense/edit.html.twig', [
            'depense' => $depense,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'])]
    public function cancel(Request $request, Depense $depense): Response
    {
        if ($this->isCsrfTokenValid('cancel' . $depense->getId(), $request->request->get('_token'))) {
            $depense->setStatus($depense->getStatus() === 'annulée' ? 'confirmée' : 'annulée');
            $this->entityManager->flush();
            $this->addFlash('success', 'Le statut de la dépense a été mis à jour (le solde de la caisse est ajusté en conséquence).');
        }

        return $this->redirectToRoute('admin_depense_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Depense $depense): Response
    {
        if ($this->isCsrfTokenValid('delete' . $depense->getId(), $request->request->get('_token'))) {
            $this->deleteEntity($this->entityManager, $depense, 'La dépense a été supprimée avec succès.');
        }

        return $this->redirectToRoute('admin_depense_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Numéro de dépense unique : DEP-AAAAMMJJ-0001.
     */
    private function generateNumero(): string
    {
        $prefix = 'DEP-' . date('Ymd') . '-';
        $count = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(Depense::class, 'd')
            ->where('d.numero LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->getQuery()
            ->getSingleScalarResult();

        return $prefix . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
