<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Repository\OnlinePaymentRepository;
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
        OnlinePaymentRepository $onlinePaymentRepository,
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
            'gateway_details' => $this->buildGatewayDetails($payments, $onlinePaymentRepository),
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

    /**
     * Détails de passerelle par paiement : opérateur, téléphone du payeur,
     * référence de transaction.
     *
     * Ces champs vivaient autrefois sur `Payment` (colonnes `provider`,
     * `payer_phone`, `provider_transaction_id`…), supprimées par la migration
     * du 29/06. Ils sont désormais reconstitués depuis `OnlinePayment` et la
     * charge utile conservée du webhook.
     *
     * @param Payment[] $payments
     *
     * @return array<int, array{reference: ?string, phone: ?string, provider: ?string, environment: ?string}>
     */
    private function buildGatewayDetails(array $payments, OnlinePaymentRepository $onlinePaymentRepository): array
    {
        $ids = [];
        foreach ($payments as $payment) {
            if ($payment->getId() !== null) {
                $ids[] = $payment->getId();
            }
        }

        $details = [];

        foreach ($onlinePaymentRepository->findIndexedByPaymentIds($ids) as $paymentId => $onlinePayment) {
            $payload = json_decode((string) $onlinePayment->getLastPayload(), true);
            $payload = is_array($payload) ? $payload : [];

            $details[$paymentId] = [
                'reference' => $onlinePayment->getReference(),
                'phone' => $payload['customer_phone'] ?? $payload['customer']['phone'] ?? null,
                'provider' => $payload['provider'] ?? $payload['payment_method'] ?? null,
                'environment' => $onlinePayment->getEnvironment(),
            ];
        }

        return $details;
    }
}
