<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\User;
use App\Service\ActivityLogger;
use App\Service\MaintenanceMode;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Mode maintenance (module Administration) — réservé au super-administrateur.
 */
#[Route('/admin/maintenance', name: 'admin_maintenance_')]
#[IsGranted('ROLE_SUPER_ADMIN')]
class MaintenanceController extends AbstractController
{
    public function __construct(
        private readonly MaintenanceMode $maintenance,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/maintenance/index.html.twig', [
            'maintenance' => $this->maintenance,
        ]);
    }

    #[Route('/activer', name: 'enable', methods: ['POST'])]
    public function enable(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('maintenance_toggle', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide. Veuillez réessayer.');

            return $this->redirectToRoute('admin_maintenance_index');
        }

        $message = mb_substr(trim((string) $request->request->get('message')), 0, 1000);

        $until = null;
        $untilRaw = trim((string) $request->request->get('until'));
        if ($untilRaw !== '') {
            $until = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $untilRaw) ?: null;
            if ($until === null) {
                $this->addFlash('error', 'La date de fin prévue est invalide.');

                return $this->redirectToRoute('admin_maintenance_index');
            }
        }

        /** @var User $user */
        $user = $this->getUser();
        $this->maintenance->enable($message, $until, $user);
        $this->activityLogger->log(ActivityLog::ACTION_UPDATE, 'Mode maintenance activé');

        $this->addFlash('warning', 'Mode maintenance activé : seuls les super-administrateurs peuvent désormais accéder à l\'application.');

        return $this->redirectToRoute('admin_maintenance_index');
    }

    #[Route('/desactiver', name: 'disable', methods: ['POST'])]
    public function disable(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('maintenance_toggle', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide. Veuillez réessayer.');

            return $this->redirectToRoute('admin_maintenance_index');
        }

        $this->maintenance->disable();
        $this->activityLogger->log(ActivityLog::ACTION_UPDATE, 'Mode maintenance désactivé');

        $this->addFlash('success', 'Mode maintenance désactivé : l\'application est de nouveau accessible à tous.');

        return $this->redirectToRoute('admin_maintenance_index');
    }
}
