<?php

namespace App\Controller;

use App\Repository\LevelRepository;
use App\Repository\SchoolRepository;
use App\Repository\SchoolYearRepository;
use App\Repository\UserRepository;
use App\Service\SchoolContextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        SchoolContextService $contextService,
        UserRepository $userRepository,
        LevelRepository $levelRepository,
        SchoolRepository $schoolRepository,
        SchoolYearRepository $schoolYearRepository
    ): Response {
        // Rediriger vers login si non connecté
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer l'établissement et l'année courante
        $currentSchool = $contextService->getCurrentSchool();
        $currentSchoolYear = $contextService->getCurrentSchoolYear();
        
        $schoolId = $currentSchool ? $currentSchool->getId() : null;

        // Calculer les statistiques selon l'établissement sélectionné
        $stats = [
            'schools' => $schoolRepository->count(['isActive' => true]),
            'school_years' => $schoolYearRepository->count([]),
            'users' => $schoolId ? $userRepository->countActiveInSchool($schoolId) : $userRepository->countActive(),
            'levels' => $schoolId ? count($levelRepository->findBySchool($schoolId)) : count($levelRepository->findActive()),
            'users_by_type' => $schoolId ? $userRepository->countByTypeInSchool($schoolId) : $userRepository->countByType(),
        ];

        // Compter par type d'utilisateur
        $userTypes = [
            'eleves' => 0,
            'enseignants' => 0,
            'personnel' => 0,
            'parents' => 0,
            'admins' => 0,
        ];

        foreach ($stats['users_by_type'] as $stat) {
            $type = $stat['userType'] ?? 'other';
            $count = $stat['count'];
            
            switch ($type) {
                case 'eleve':
                    $userTypes['eleves'] = $count;
                    break;
                case 'enseignant':
                    $userTypes['enseignants'] = $count;
                    break;
                case 'personnel':
                    $userTypes['personnel'] = $count;
                    break;
                case 'parent':
                    $userTypes['parents'] = $count;
                    break;
                case 'admin':
                case 'directeur':
                    $userTypes['admins'] += $count;
                    break;
            }
        }

        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
            'stats' => $stats,
            'user_types' => $userTypes,
            'current_school' => $currentSchool,
            'current_school_year' => $currentSchoolYear,
        ]);
    }
}

