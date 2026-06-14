<?php

namespace App\Controller;

use App\Repository\PaymentRepository;
use App\Service\SchoolContextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Journal des paiements par Mobile Money (module Administration).
 */
#[Route('/admin/logs/mobile-money', name: 'admin_mobile_money_log_')]
#[IsGranted('ROLE_ADMIN')]
class MobileMoneyLogController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        PaymentRepository $paymentRepository,
        SchoolContextService $contextService,
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();
        $status = $request->query->get('status') ?: null;

        $payments = $paymentRepository->findMobileMoney($currentSchool?->getId(), $status);

        $totalPaid = 0.0;
        $countPaid = 0;
        $countPending = 0;
        foreach ($payments as $payment) {
            if ($payment->getStatus() === 'payé') {
                $totalPaid += (float) $payment->getAmount();
                $countPaid++;
            } elseif ($payment->getStatus() === 'en_attente') {
                $countPending++;
            }
        }

        return $this->render('admin/mobile_money_log.html.twig', [
            'payments' => $payments,
            'current_school' => $currentSchool,
            'current_status' => $status,
            'stats' => [
                'total' => count($payments),
                'paid_count' => $countPaid,
                'pending_count' => $countPending,
                'total_paid' => $totalPaid,
            ],
        ]);
    }
}
