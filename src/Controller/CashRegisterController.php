<?php

namespace App\Controller;

use App\Entity\CashRegister;
use App\Repository\CashRegisterRepository;
use App\Repository\PaymentRepository;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/cash-register', name: 'admin_cash_register_')]
#[IsGranted('ROLE_ADMIN')]
class CashRegisterController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(
        SchoolContextService $contextService,
        CashRegisterRepository $cashRegisterRepository,
        PaymentRepository $paymentRepository
    ): Response {
        $school = $contextService->getCurrentSchool();
        $cashier = $this->getUser();

        if (!$school || !$cashier instanceof \App\Entity\User) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement.');
            return $this->redirectToRoute('admin_student_index');
        }

        $cashRegister = $cashRegisterRepository->findOpenForCashier($school, $cashier);
        $paymentsTotal = 0.0;
        if ($cashRegister) {
            $paymentsTotal = $paymentRepository->getTotalAmountByCashRegister($cashRegister->getId());
        }

        $currentBalance = $cashRegister
            ? (float) $cashRegister->getOpeningBalance() + $paymentsTotal
            : 0.0;

        return $this->render('cash_register/index.html.twig', [
            'current_school' => $school,
            'cash_register' => $cashRegister,
            'payments_total' => $paymentsTotal,
            'current_balance' => $currentBalance,
        ]);
    }

    #[Route('/open', name: 'open', methods: ['GET', 'POST'])]
    public function open(
        Request $request,
        EntityManagerInterface $entityManager,
        SchoolContextService $contextService,
        CashRegisterRepository $cashRegisterRepository
    ): Response {
        $school = $contextService->getCurrentSchool();
        $cashier = $this->getUser();

        if (!$school || !$cashier instanceof \App\Entity\User) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement.');
            return $this->redirectToRoute('admin_student_index');
        }

        $existing = $cashRegisterRepository->findOpenForCashier($school, $cashier);
        if ($existing) {
            $this->addFlash('info', 'Votre caisse est déjà ouverte.');
            return $this->redirectToRoute('admin_cash_register_index');
        }

        if ($request->isMethod('POST')) {
            $openingBalance = (float) $request->request->get('opening_balance', 0);
            if ($openingBalance < 0) {
                $this->addFlash('error', 'Le solde d\'ouverture doit être positif ou zéro.');
                return $this->redirectToRoute('admin_cash_register_open');
            }

            $cashRegister = (new CashRegister())
                ->setSchool($school)
                ->setCashier($cashier)
                ->setOpeningBalance((string) number_format($openingBalance, 2, '.', ''));

            $entityManager->persist($cashRegister);
            $entityManager->flush();

            $this->addFlash('success', 'Caisse ouverte avec succès.');
            return $this->redirectToRoute('admin_cash_register_index');
        }

        return $this->render('cash_register/open.html.twig', [
            'current_school' => $school,
        ]);
    }
}

