<?php

namespace App\Controller;

use App\Repository\ClassroomRepository;
use App\Repository\LevelRepository;
use App\Repository\RegistrationRepository;
use App\Repository\SchoolRepository;
use App\Repository\SchoolYearRepository;
use App\Repository\StudentRepository;
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
        SchoolYearRepository $schoolYearRepository,
        StudentRepository $studentRepository,
        ClassroomRepository $classroomRepository,
        RegistrationRepository $registrationRepository
    ): Response {
        // Rediriger vers login si non connecté
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        // Un parent « pur » n'a rien à faire sur le tableau de bord du personnel.
        if ($this->isGranted('ROLE_PARENT') && !$this->isGranted('ROLE_ENSEIGNANT') && !$this->isGranted('ROLE_INSCRIPTION')) {
            return $this->redirectToRoute('parent_dashboard');
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

        // Nombre d'élèves inscrits = inscriptions (Registration) de l'année courante.
        // (Les élèves importés au référentiel mais non inscrits ne sont pas comptés.)
        $studentsCount = $registrationRepository->countBySchoolAndYear($schoolId, $currentSchoolYear?->getId());

        // Classes + répartition des élèves de l'établissement courant (année en cours).
        // Ces graphiques n'ont de sens qu'avec un établissement sélectionné.
        $classesCount = 0;
        $studentsStatus = ['affecte' => 0, 'non_affecte' => 0];
        $studentsGender = ['M' => 0, 'F' => 0];
        if ($schoolId) {
            $yearId = $currentSchoolYear?->getId();
            $classesCount = $classroomRepository->countBySchoolAndYear($schoolId, $yearId);
            $studentsStatus = $studentRepository->countByStatusForSchool($schoolId, $yearId);
            $studentsGender = $studentRepository->countByGenderForSchool($schoolId, $yearId);
        }

        // Compter par type d'utilisateur (hors élèves : ce ne sont pas des comptes utilisateurs)
        $userTypes = [
            'enseignants' => 0,
            'personnel' => 0,
            'parents' => 0,
            'admins' => 0,
        ];

        foreach ($stats['users_by_type'] as $stat) {
            $type = $stat['userType'] ?? 'other';
            $count = $stat['count'];

            switch ($type) {
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
            'students_count' => $studentsCount,
            'classes_count' => $classesCount,
            'students_status' => $studentsStatus,
            'students_gender' => $studentsGender,
            'current_school' => $currentSchool,
            'current_school_year' => $currentSchoolYear,
        ]);
    }
}

