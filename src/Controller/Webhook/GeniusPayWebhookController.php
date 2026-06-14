<?php

namespace App\Controller\Webhook;

use App\Service\Payment\WebhookProcessor;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Point d'entrée des webhooks GeniusPay (appelé par le serveur de la passerelle).
 *
 * Accès public (cf. access_control ^/webhook) mais sécurisé par vérification de
 * signature et idempotence dans WebhookProcessor. Sans session ni CSRF (server-to-server).
 */
#[Route('/webhook/geniuspay', name: 'geniuspay_webhook', methods: ['POST'])]
class GeniusPayWebhookController extends AbstractController
{
    public function __invoke(Request $request, WebhookProcessor $processor, LoggerInterface $paymentLogger): Response
    {
        // ── Diagnostic temporaire : identifier l'en-tête de signature et le format ──
        $paymentLogger->info('Webhook GeniusPay reçu (diagnostic)', [
            'headers' => $request->headers->all(),
            'body' => $request->getContent(),
        ]);

        $signature = $request->headers->get('X-Webhook-Signature');
        $timestamp = $request->headers->get('X-Webhook-Timestamp');

        $result = $processor->handle($request->getContent(), $signature, $timestamp);

        $httpStatus = match ($result) {
            WebhookProcessor::RESULT_INVALID_SIGNATURE => Response::HTTP_UNAUTHORIZED,
            WebhookProcessor::RESULT_INVALID_PAYLOAD,
            WebhookProcessor::RESULT_AMOUNT_MISMATCH => Response::HTTP_BAD_REQUEST,
            // Doublon / paiement inconnu : 200 pour éviter les retentatives en boucle.
            default => Response::HTTP_OK,
        };

        return new Response($result, $httpStatus);
    }
}
