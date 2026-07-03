<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\User;
use App\Repository\ActivityLogRepository;
use App\Repository\UserRepository;
use App\Service\SchoolContextService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Mouchard — journal d'activité du logiciel (module Administration).
 *
 * Affiche, filtrable et paginée, la trace horodatée de toutes les actions
 * sensibles : créations, modifications, suppressions et évènements de sécurité.
 */
#[Route('/admin/mouchard', name: 'admin_mouchard_')]
#[IsGranted('ROLE_ADMIN')]
class MouchardController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        ActivityLogRepository $activityLogRepository,
        UserRepository $userRepository,
        PaginatorInterface $paginator,
        SchoolContextService $contextService,
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();

        // Un super-administrateur « réel » voit tout ; les autres sont cloisonnés à
        // l'établissement courant (les traces système sans établissement restent visibles).
        $user = $this->getUser();
        $isSuperAdmin = $user instanceof User && in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true);

        $filters = [
            'action' => $request->query->get('action') ?: null,
            'entity_type' => $request->query->get('entity_type') ?: null,
            'user_id' => $request->query->getInt('user_id') ?: null,
            'search' => trim((string) $request->query->get('search')) ?: null,
            'date_from' => $request->query->get('date_from') ?: null,
            'date_to' => $request->query->get('date_to') ?: null,
        ];

        if (!$isSuperAdmin && $currentSchool !== null) {
            $filters['school_id'] = $currentSchool->getId();
            $filters['include_null_school'] = true;
        }

        $pagination = $paginator->paginate(
            $activityLogRepository->buildFilteredQuery($filters),
            $request->query->getInt('page', 1),
            50
        );

        return $this->render('admin/mouchard/index.html.twig', [
            'pagination' => $pagination,
            'current_school' => $currentSchool,
            'is_super_admin' => $isSuperAdmin,
            'filters' => $filters,
            'action_labels' => ActivityLog::ACTION_LABELS,
            'entity_types' => $activityLogRepository->findDistinctEntityTypes($isSuperAdmin ? null : $currentSchool?->getId()),
            'entity_labels' => ActivityLog::ENTITY_LABELS,
            'users' => $userRepository->findBy([], ['username' => 'ASC']),
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(ActivityLog $log): Response
    {
        return $this->render('admin/mouchard/show.html.twig', [
            'log' => $log,
        ]);
    }
}
