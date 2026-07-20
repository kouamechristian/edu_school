<?php

namespace App\Controller\Webhook;

use App\Repository\OnlinePaymentRepository;
use App\Repository\SchoolRepository;
use App\Service\GeniusPay\GeniusPayConfigResolver;
use App\Service\GeniusPay\GeniusPayService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Réception des notifications GeniusPay.
 *
 * Route publique : elle est appelée par la passerelle, pas par un utilisateur
 * connecté. Toute la sécurité repose donc sur la signature HMAC.
 *
 * L'ordre des opérations est délibéré :
 *   1. lire la charge utile BRUTE (la signature porte sur les octets exacts) ;
 *   2. en extraire la référence — sans rien croire d'autre ;
 *   3. retrouver NOTRE ligne, donc l'établissement, donc le bon secret ;
 *   4. vérifier la signature ; ce n'est qu'ensuite que la charge utile devient
 *      digne de confiance ;
 *   5. confirmer, en recoupant avec le montant figé à l'initiation.
 *
 * On répond toujours 200 sur les cas « métier » (référence inconnue, événement
 * ignoré) pour éviter que GeniusPay ne rejoue indéfiniment. Les 4xx sont
 * réservés aux charges utiles illégitimes.
 */
class GeniusPayWebhookController extends AbstractController
{
    /** Au-delà, la notification est considérée comme rejouée. */
    private const MAX_CLOCK_SKEW_SECONDS = 300;

    #[Route('/webhook/geniuspay', name: 'webhook_geniuspay', methods: ['POST'])]
    public function handle(
        Request $request,
        OnlinePaymentRepository $onlinePayments,
        SchoolRepository $schools,
        GeniusPayConfigResolver $configResolver,
        GeniusPayService $geniusPay,
        LoggerInterface $logger,
    ): Response {
        $raw = $request->getContent();

        // Deux jeux d'en-têtes coexistent : le guide d'intégration du tableau de
        // bord GeniusPay annonce « X-Webhook-* », l'API_Documentation.md fournie
        // annonce « X-GeniusPay-* ». On accepte les deux, le premier faisant foi.
        $signature = (string) ($request->headers->get('X-Webhook-Signature') ?? $request->headers->get('X-GeniusPay-Signature') ?? '');
        $timestamp = (string) ($request->headers->get('X-Webhook-Timestamp') ?? $request->headers->get('X-GeniusPay-Timestamp') ?? '');
        $event = (string) ($request->headers->get('X-Webhook-Event') ?? $request->headers->get('X-GeniusPay-Event') ?? '');

        if ($raw === '' || $signature === '') {
            return $this->problem('Signature ou charge utile manquante.', Response::HTTP_BAD_REQUEST);
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return $this->problem('Charge utile illisible.', Response::HTTP_BAD_REQUEST);
        }

        // Rejeu : une notification trop ancienne est refusée. La doc annonce un
        // timestamp Unix ; on accepte aussi une date ISO par prudence. Un
        // horodatage illisible n'est pas bloquant — la signature reste le
        // véritable contrôle.
        if ($timestamp !== '') {
            $sentAt = ctype_digit($timestamp) ? (int) $timestamp : (int) strtotime($timestamp);

            if ($sentAt > 0 && abs(time() - $sentAt) > self::MAX_CLOCK_SKEW_SECONDS) {
                $logger->warning('GeniusPay webhook : horodatage hors tolérance', [
                    'timestamp' => $timestamp,
                    'ecart_secondes' => abs(time() - $sentAt),
                ]);

                return $this->problem('Notification expirée.', Response::HTTP_BAD_REQUEST);
            }
        }

        // Le bouton « tester » du tableau de bord envoie un webhook.test sans
        // transaction : impossible d'en déduire l'établissement, donc le secret.
        // On essaie alors tous les secrets configurés — ce qui transforme ce
        // test en vraie validation de la configuration, et non en simple ping.
        if ($event === 'webhook.test' || ($payload['event'] ?? null) === 'webhook.test') {
            foreach ($schools->findBy(['geniuspayEnabled' => true]) as $candidate) {
                $config = $configResolver->resolve($candidate);
                if ($config?->webhookSecret === null) {
                    continue;
                }

                if ($this->verifySignature($raw, $timestamp, $signature, $config->webhookSecret) !== null) {
                    $logger->info('GeniusPay webhook : test validé', ['ecole' => $candidate->getName()]);

                    return new JsonResponse(['received' => true, 'test' => true, 'school' => $candidate->getName()]);
                }
            }

            $logger->warning('GeniusPay webhook : test reçu mais signature ne correspondant à aucun secret configuré');

            return $this->problem('Signature invalide : aucun secret de webhook configuré ne correspond.', Response::HTTP_UNAUTHORIZED);
        }

        // La forme de la charge utile varie selon les sources : on cherche la
        // référence aux emplacements connus plutôt que d'en supposer un seul.
        $transaction = $this->extractTransaction($payload);
        $reference = (string) ($transaction['reference'] ?? '');

        if ($reference === '') {
            $logger->warning('GeniusPay webhook : référence absente', ['cles' => array_keys($payload)]);

            return $this->problem('Référence de transaction absente.', Response::HTTP_BAD_REQUEST);
        }

        // Localiser la transaction ne présume de rien : tant que la signature
        // n'est pas vérifiée, cette ligne ne sera pas modifiée.
        $onlinePayment = $onlinePayments->findOneByReference($reference);
        if ($onlinePayment === null) {
            $logger->warning('GeniusPay webhook : référence inconnue', ['reference' => $reference, 'event' => $event]);

            // 200 : inutile que la passerelle rejoue, cette référence n'est pas à nous.
            return new JsonResponse(['received' => true, 'known' => false]);
        }

        $config = $configResolver->resolve($onlinePayment->getSchool());
        if ($config === null || $config->webhookSecret === null) {
            $logger->error('GeniusPay webhook : aucun secret configuré', [
                'reference' => $reference,
                'school' => $onlinePayment->getSchool()?->getId(),
            ]);

            return $this->problem('Passerelle non configurée pour cet établissement.', Response::HTTP_FORBIDDEN);
        }

        $scheme = $this->verifySignature($raw, $timestamp, $signature, $config->webhookSecret);

        if ($scheme === null) {
            $logger->critical('GeniusPay webhook : signature invalide — notification rejetée', [
                'reference' => $reference,
                'event' => $event,
            ]);

            return $this->problem('Signature invalide.', Response::HTTP_UNAUTHORIZED);
        }

        // Trace du schéma accepté : permet de retirer la variante héritée une
        // fois confirmé que GeniusPay n'émet plus que « timestamp.payload ».
        $logger->info('GeniusPay webhook : signature validée', ['reference' => $reference, 'schema' => $scheme]);

        // ── À partir d'ici seulement, la charge utile est digne de confiance. ──

        if ($event !== '' && !str_starts_with($event, 'payment.')) {
            return new JsonResponse(['received' => true, 'ignored' => $event]);
        }

        try {
            $credited = $geniusPay->confirm($reference, is_array($transaction) ? $transaction : null, 'webhook');
        } catch (\Throwable $e) {
            $logger->error('GeniusPay webhook : erreur de traitement', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            // 500 : on veut que GeniusPay rejoue, l'échec est de notre côté.
            return $this->problem('Erreur interne de traitement.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['received' => true, 'credited' => $credited]);
    }

    /**
     * Vérifie la signature HMAC-SHA256 et renvoie le schéma qui a permis de la
     * valider, ou null si aucun ne correspond.
     *
     * Schéma confirmé en production le 2026-07-20 sur une notification réelle
     * (référence SANDBOX_WVGJUPIAXU5W9RSJ) : GeniusPay signe
     * « timestamp.corps_brut », comme l'exemple Java du guide.
     *
     * Une variante est conservée en repli : les exemples PHP et Node du même
     * guide signent un JSON RÉ-ENCODÉ depuis le tableau décodé, et json_encode()
     * échappe les slashes et l'unicode — ce qui ne produit pas les mêmes octets
     * que le corps transmis. Si GeniusPay bascule un jour sur cette forme, les
     * paiements continueront d'être crédités plutôt que d'échouer en silence.
     *
     * La variante de l'ancien API_Documentation.md (signature sur la seule
     * charge utile, sans horodatage) a été retirée : elle est démentie par
     * l'observation.
     *
     * Chaque variante exige le secret ; aucune n'affaiblit le contrôle.
     */
    private function verifySignature(string $raw, string $timestamp, string $signature, string $secret): ?string
    {
        $candidates = ['timestamp.brut' => $timestamp . '.' . $raw];

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $candidates['timestamp.reencode'] = $timestamp . '.' . json_encode($decoded);
        }

        foreach ($candidates as $scheme => $signedData) {
            if (hash_equals(hash_hmac('sha256', $signedData, $secret), $signature)) {
                return $scheme;
            }
        }

        return null;
    }

    /**
     * Extrait le bloc transaction de la charge utile, quelle que soit sa forme.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function extractTransaction(array $payload): array
    {
        foreach ([
            $payload['data']['transaction'] ?? null,
            $payload['data'] ?? null,
            $payload['transaction'] ?? null,
            $payload,
        ] as $candidate) {
            if (is_array($candidate) && isset($candidate['reference'])) {
                return $candidate;
            }
        }

        return [];
    }

    private function problem(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['received' => false, 'error' => $message], $status);
    }
}
