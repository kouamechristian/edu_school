<?php

namespace App\Controller\Portal;

use App\Entity\Payment;
use App\Entity\Student;
use App\Repository\PaymentRepository;
use App\Repository\StudentFeeRepository;
use App\Security\Voter\ChildVoter;
use App\Service\ParentPortalService;
use App\Service\Payment\PaymentGatewayException;
use App\Service\Payment\PaymentInitiator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * API JSON de paiement pour l'espace parent.
 *
 * Sécurité : ROLE_PARENT au niveau de la classe + ChildVoter sur chaque ressource
 * (un parent n'accède qu'aux paiements de ses enfants). Le POST exige un jeton CSRF.
 */
#[Route('/parent/api/payments', name: 'parent_api_payment_')]
#[IsGranted('ROLE_PARENT')]
class ParentPaymentApiController extends AbstractController
{
    /**
     * Crée un paiement en ligne et renvoie l'URL de checkout.
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        StudentFeeRepository $studentFeeRepository,
        PaymentInitiator $paymentInitiator,
    ): JsonResponse {
        $payload = json_decode($request->getContent() ?: '[]', true);
        $payload = \is_array($payload) ? $payload : [];

        $studentFee = $studentFeeRepository->find((int) ($payload['student_fee_id'] ?? 0));
        if (!$studentFee || !$studentFee->getStudent()) {
            return $this->json(['error' => 'Frais introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(ChildVoter::VIEW, $studentFee->getStudent());

        $token = $request->headers->get('X-CSRF-Token', (string) ($payload['_token'] ?? ''));
        if (!$this->isCsrfTokenValid('parent_pay' . $studentFee->getStudent()->getId(), $token)) {
            return $this->json(['error' => 'Jeton CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $payment = $paymentInitiator->initiate($studentFee, (float) ($payload['amount'] ?? 0), $this->getUser());
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (PaymentGatewayException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        return $this->json([
            'payment_id' => $payment->getId(),
            'checkout_url' => $payment->getCheckoutUrl(),
            'status' => $payment->getStatus(),
        ], Response::HTTP_CREATED);
    }

    /**
     * Statut d'un paiement (de l'un des enfants du parent).
     */
    #[Route('/{id}/status', name: 'status', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function status(Payment $payment): JsonResponse
    {
        if (!$payment->getStudent()) {
            return $this->json(['error' => 'Paiement introuvable.'], Response::HTTP_NOT_FOUND);
        }
        $this->denyAccessUnlessGranted(ChildVoter::VIEW, $payment->getStudent());

        return $this->json([
            'id' => $payment->getId(),
            'number' => $payment->getPaymentNumber(),
            'status' => $payment->getStatus(),
            'status_label' => $payment->getStatusLabel(),
            'provider_status' => $payment->getProviderStatus(),
            'amount' => (float) $payment->getAmount(),
        ]);
    }

    /**
     * Historique des paiements de tous les enfants du parent.
     */
    #[Route('', name: 'history', methods: ['GET'])]
    public function history(ParentPortalService $portal, PaymentRepository $paymentRepository): JsonResponse
    {
        $studentIds = array_map(
            static fn (Student $c) => $c->getId(),
            $portal->getChildren($this->getUser())
        );

        $items = array_map(static fn (Payment $p) => [
            'id' => $p->getId(),
            'number' => $p->getPaymentNumber(),
            'student' => $p->getStudent()?->getFullName(),
            'fee' => $p->getFee()?->getName(),
            'amount' => (float) $p->getAmount(),
            'date' => $p->getPaymentDate()?->format('Y-m-d'),
            'method' => $p->getPaymentMethodLabel(),
            'status' => $p->getStatus(),
            'status_label' => $p->getStatusLabel(),
        ], $paymentRepository->findByStudentIds($studentIds));

        return $this->json(['payments' => $items]);
    }
}
