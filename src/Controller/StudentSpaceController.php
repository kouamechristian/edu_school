<?php

namespace App\Controller;

use App\Repository\ClassroomRepository;
use App\Repository\CourseRepository;
use App\Repository\EvaluationRepository;
use App\Repository\GradeRepository;
use App\Repository\PeriodRepository;
use App\Repository\AbsenceRepository;
use App\Service\GradeCalculationService;
use App\Service\SchoolContextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/student')]
class StudentSpaceController extends AbstractController
{
    public function __construct(
        private GradeCalculationService $gradeCalculationService
    ) {
    }

    #[Route('/dashboard', name: 'student_dashboard', methods: ['GET'])]
    public function dashboard(
        SchoolContextService $contextService,
        PeriodRepository $periodRepository,
        GradeRepository $gradeRepository,
        EvaluationRepository $evaluationRepository
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        
        if (!$user || $user->getUserType() !== 'eleve') {
            $this->addFlash('error', 'Accès réservé aux élèves.');
            return $this->redirectToRoute('app_login');
        }

        $currentSchool = $contextService->getCurrentSchool();
        $currentYear = $contextService->getCurrentSchoolYear();

        $data = [
            'user' => $user,
            'current_school' => $currentSchool,
            'current_year' => $currentYear,
            'stats' => [
                'total_evaluations' => 0,
                'average' => null,
                'rank' => null,
            ],
        ];

        if ($currentSchool && $currentYear) {
            // Récupérer la période courante
            $currentPeriod = $periodRepository->findCurrentPeriod(
                $currentSchool->getId(),
                $currentYear->getId()
            );

            if ($currentPeriod) {
                // Calculer les statistiques
                $averages = $this->gradeCalculationService->calculateStudentAveragesForPeriod($user, $currentPeriod);
                
                $data['current_period'] = $currentPeriod;
                $data['stats']['average'] = $averages['general_average'];
                $data['stats']['total_evaluations'] = count($averages['subjects']);
                
                // Récupérer les dernières notes
                $data['recent_grades'] = array_slice(
                    $gradeRepository->findByStudent($user->getId()),
                    0,
                    5
                );
            }
        }

        return $this->render('student_space/dashboard.html.twig', $data);
    }

    #[Route('/grades', name: 'student_grades', methods: ['GET'])]
    public function grades(
        Request $request,
        SchoolContextService $contextService,
        PeriodRepository $periodRepository,
        GradeRepository $gradeRepository
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        
        if (!$user || $user->getUserType() !== 'eleve') {
            $this->addFlash('error', 'Accès réservé aux élèves.');
            return $this->redirectToRoute('app_login');
        }

        $currentSchool = $contextService->getCurrentSchool();
        $currentYear = $contextService->getCurrentSchoolYear();

        if (!$currentSchool || !$currentYear) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement et une année scolaire.');
            return $this->render('student_space/grades.html.twig', [
                'periods' => [],
                'grades' => [],
            ]);
        }

        $periods = $periodRepository->findBySchoolAndYear(
            $currentSchool->getId(),
            $currentYear->getId()
        );

        $selectedPeriod = $request->query->getInt('period');
        
        // Si aucune période sélectionnée, prendre la période courante
        if (!$selectedPeriod && !empty($periods)) {
            $currentPeriod = $periodRepository->findCurrentPeriod(
                $currentSchool->getId(),
                $currentYear->getId()
            );
            $selectedPeriod = $currentPeriod ? $currentPeriod->getId() : $periods[0]->getId();
        }

        $grades = [];
        $averages = [];
        $period = null;

        if ($selectedPeriod) {
            $period = $periodRepository->find($selectedPeriod);
            if ($period) {
                $grades = $gradeRepository->findByStudentAndPeriod($user->getId(), $selectedPeriod);
                $averages = $this->gradeCalculationService->calculateStudentAveragesForPeriod($user, $period);
                
                // Organiser les notes par matière
                $gradesBySubject = [];
                foreach ($grades as $grade) {
                    if ($grade->getEvaluation()->isPublished()) {
                        $subjectId = $grade->getEvaluation()->getSubject()->getId();
                        $subjectName = $grade->getEvaluation()->getSubject()->getName();
                        
                        if (!isset($gradesBySubject[$subjectId])) {
                            $gradesBySubject[$subjectId] = [
                                'subject' => $grade->getEvaluation()->getSubject(),
                                'grades' => [],
                            ];
                        }
                        
                        $gradesBySubject[$subjectId]['grades'][] = $grade;
                    }
                }
                
                $grades = $gradesBySubject;
            }
        }

        return $this->render('student_space/grades.html.twig', [
            'periods' => $periods,
            'selected_period' => $selectedPeriod,
            'period' => $period,
            'grades' => $grades,
            'averages' => $averages,
            'user' => $user,
        ]);
    }

    #[Route('/bulletin/{periodId}', name: 'student_bulletin', methods: ['GET'])]
    public function bulletin(
        int $periodId,
        PeriodRepository $periodRepository
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        
        if (!$user || $user->getUserType() !== 'eleve') {
            $this->addFlash('error', 'Accès réservé aux élèves.');
            return $this->redirectToRoute('app_login');
        }

        $period = $periodRepository->find($periodId);
        
        if (!$period) {
            throw $this->createNotFoundException('Période non trouvée');
        }

        // Utiliser la même logique que pour les bulletins admin
        $classroomId = 1; // À adapter selon la classe de l'élève
        
        $data = $this->gradeCalculationService->generateBulletinData($user, $period, $classroomId);
        $data['getAppreciation'] = fn($avg) => $this->gradeCalculationService->getAppreciation($avg);
        $data['getMention'] = fn($avg) => $this->gradeCalculationService->getMention($avg);

        return $this->render('student_space/bulletin.html.twig', $data);
    }

    #[Route('/schedule', name: 'student_schedule', methods: ['GET'])]
    public function schedule(
        SchoolContextService $contextService,
        ClassroomRepository $classroomRepository,
        CourseRepository $courseRepository
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        
        if (!$user || $user->getUserType() !== 'eleve') {
            $this->addFlash('error', 'Accès réservé aux élèves.');
            return $this->redirectToRoute('app_login');
        }

        $currentSchool = $contextService->getCurrentSchool();
        $currentYear = $contextService->getCurrentSchoolYear();

        // Récupérer la classe de l'élève (à adapter selon votre logique)
        $classroom = null;
        $schedule = [];

        if ($currentSchool && $currentYear) {
            // Trouver la classe de l'élève pour l'année en cours
            $classrooms = $classroomRepository->findBySchool($currentSchool->getId());
            
            if (!empty($classrooms)) {
                // Prendre la première classe (à adapter)
                $classroom = $classrooms[0];
                $schedule = $courseRepository->findScheduleByClassroom($classroom->getId());
            }
        }

        return $this->render('student_space/schedule.html.twig', [
            'user' => $user,
            'classroom' => $classroom,
            'schedule' => $schedule,
        ]);
    }

    #[Route('/student/absences', name: 'student_absences', methods: ['GET'])]
    public function absences(
        AbsenceRepository $absenceRepository,
        SchoolContextService $contextService
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour accéder à cette page.');
        }

        $currentSchool = $contextService->getCurrentSchool();
        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement.');
            return $this->redirectToRoute('student_dashboard');
        }

        // Pour l'instant, on suppose que l'utilisateur est un élève
        // Dans la vraie implémentation, il faudrait récupérer l'entité Student liée à l'User
        $absences = [];
        
        // TODO: Implémenter la récupération des absences de l'élève
        // $student = $studentRepository->findByUser($user);
        // if ($student) {
        //     $absences = $absenceRepository->findByStudent($student->getId());
        // }

        return $this->render('student_space/absences.html.twig', [
            'user' => $user,
            'absences' => $absences,
            'current_school' => $currentSchool,
        ]);
    }
}

