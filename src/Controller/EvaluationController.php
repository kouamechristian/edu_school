<?php

namespace App\Controller;

use App\Controller\Concern\HandlesEntityDeletion;
use App\Entity\Classroom;
use App\Entity\Evaluation;
use App\Entity\Grade;
use App\Entity\User;
use App\Form\EvaluationType;
use App\Repository\ClassroomRepository;
use App\Repository\CourseRepository;
use App\Repository\EvaluationRepository;
use App\Repository\GradeRepository;
use App\Repository\PeriodRepository;
use App\Repository\StudentRepository;
use App\Repository\SubjectRepository;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/evaluations')]
#[IsGranted('ROLE_ENSEIGNANT')]
class EvaluationController extends AbstractController
{
    use HandlesEntityDeletion;

    #[Route('/', name: 'admin_evaluation_index', methods: ['GET'])]
    public function index(
        EvaluationRepository $evaluationRepository,
        ClassroomRepository $classroomRepository,
        PeriodRepository $periodRepository,
        SchoolContextService $contextService,
        Request $request,
        \Knp\Component\Pager\PaginatorInterface $paginator
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();
        $currentYear = $contextService->getCurrentSchoolYear();
        
        if (!$currentSchool || !$currentYear) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement et une année scolaire.');
            return $this->render('evaluation/index.html.twig', [
                'evaluations' => [],
                'classrooms' => [],
                'periods' => [],
            ]);
        }

        $classrooms = $classroomRepository->findBySchool($currentSchool->getId());
        $periods = $periodRepository->findBySchoolAndYear(
            $currentSchool->getId(),
            $currentYear->getId()
        );

        $selectedClassroom = $request->query->getInt('classroom') ?: null;
        $selectedPeriod = $request->query->getInt('period') ?: null;

        // Un enseignant « simple » (sans rôle directeur) ne voit que SES évaluations.
        $user = $this->getUser();
        $restrictToTeacher = $this->isGranted('ROLE_ENSEIGNANT') && !$this->isGranted('ROLE_DIRECTEUR');
        $teacherId = ($restrictToTeacher && $user instanceof User) ? $user->getId() : null;

        // Par défaut (sans filtre), on affiche toutes les évaluations de l'établissement / année.
        $evaluations = $evaluationRepository->findFiltered(
            $currentSchool->getId(),
            $currentYear->getId(),
            $selectedClassroom,
            $selectedPeriod,
            $teacherId
        );
        $evaluations = $paginator->paginate($evaluations, $request->query->getInt('page', 1), 50);

        return $this->render('evaluation/index.html.twig', [
            'evaluations' => $evaluations,
            'classrooms' => $classrooms,
            'periods' => $periods,
            'selected_classroom' => $selectedClassroom,
            'selected_period' => $selectedPeriod,
            'current_school' => $currentSchool,
            'current_year' => $currentYear,
        ]);
    }

    #[Route('/new', name: 'admin_evaluation_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        SchoolContextService $contextService
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();
        
        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement.');
            return $this->redirectToRoute('admin_evaluation_index');
        }

        $evaluation = new Evaluation();
        // Un enseignant « simple » : pré-sélectionner lui-même comme enseignant.
        if ($this->isGranted('ROLE_ENSEIGNANT') && !$this->isGranted('ROLE_DIRECTEUR')) {
            $evaluation->setTeacher($this->getUser());
        }
        $form = $this->createForm(EvaluationType::class, $evaluation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($evaluation);
            $entityManager->flush();

            $this->addFlash('success', 'L\'évaluation a été créée avec succès.');
            return $this->redirectToRoute('admin_evaluation_grades', ['id' => $evaluation->getId()]);
        }

        return $this->render('evaluation/new.html.twig', [
            'evaluation' => $evaluation,
            'form' => $form,
        ]);
    }

    /**
     * Matières liées au niveau d'une classe (JSON) — alimente le sélecteur de matières
     * en cascade sur le formulaire d'évaluation.
     */
    #[Route('/subjects-by-classroom/{id}', name: 'admin_evaluation_subjects_by_classroom', methods: ['GET'])]
    public function subjectsByClassroom(
        Classroom $classroom,
        SubjectRepository $subjectRepository,
        CourseRepository $courseRepository
    ): JsonResponse {
        $schoolId = $classroom->getSchool()?->getId();
        $level = $classroom->getLevel();

        $subjects = $level
            ? $subjectRepository->findBySchoolAndLevel($schoolId, $level->getId())
            : $subjectRepository->findBySchool($schoolId);

        // Enseignant « simple » : restreindre à ses propres matières.
        $allowed = null;
        if ($this->isGranted('ROLE_ENSEIGNANT') && !$this->isGranted('ROLE_DIRECTEUR')) {
            $allowed = [];
            $user = $this->getUser();
            if ($user instanceof User) {
                foreach ($courseRepository->findByTeacher($user->getId()) as $course) {
                    if ($course->getSubject()) {
                        $allowed[$course->getSubject()->getId()] = true;
                    }
                }
                foreach ($user->getTeachingSubjects() as $subject) {
                    $allowed[$subject->getId()] = true;
                }
            }
        }

        $data = [];
        foreach ($subjects as $subject) {
            if ($allowed !== null && !isset($allowed[$subject->getId()])) {
                continue;
            }
            $data[] = ['id' => $subject->getId(), 'name' => $subject->getName()];
        }

        return new JsonResponse(['subjects' => $data, 'level' => $level?->getName()]);
    }

    #[Route('/{id}/show', name: 'admin_evaluation_show', methods: ['GET'])]
    public function show(
        Evaluation $evaluation,
        GradeRepository $gradeRepository
    ): Response {
        $grades = $gradeRepository->findByEvaluation($evaluation->getId());
        $statistics = $gradeRepository->getEvaluationStatistics($evaluation->getId());

        return $this->render('evaluation/show.html.twig', [
            'evaluation' => $evaluation,
            'grades' => $grades,
            'statistics' => $statistics,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_evaluation_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Evaluation $evaluation,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(EvaluationType::class, $evaluation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'L\'évaluation a été modifiée avec succès.');
            return $this->redirectToRoute('admin_evaluation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('evaluation/edit.html.twig', [
            'evaluation' => $evaluation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_evaluation_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(
        Request $request,
        Evaluation $evaluation,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('delete'.$evaluation->getId(), $request->getPayload()->getString('_token'))) {
            $this->deleteEntity(
                $entityManager,
                $evaluation,
                'L\'évaluation a été supprimée avec succès.',
                'Suppression impossible : cette évaluation contient encore des notes. Veuillez d\'abord les supprimer.'
            );
        }

        return $this->redirectToRoute('admin_evaluation_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/grades', name: 'admin_evaluation_grades', methods: ['GET', 'POST'])]
    public function grades(
        Evaluation $evaluation,
        GradeRepository $gradeRepository,
        StudentRepository $studentRepository,
        EntityManagerInterface $entityManager,
        Request $request
    ): Response {
        // Protection : la saisie n'est possible que tant que l'évaluation est
        // éditable (ni validée, ni verrouillée par un bulletin). On bloque ici
        // l'accès direct par URL (GET comme POST) et on renvoie vers le détail.
        if (!$evaluation->isEditable()) {
            $this->addFlash('warning', $evaluation->isLockedByBulletin()
                ? 'Ces notes sont verrouillées : un bulletin a déjà été généré pour cette évaluation.'
                : 'Cette évaluation est validée. Annulez la validation depuis le détail pour modifier les notes.');

            return $this->redirectToRoute('admin_evaluation_show', ['id' => $evaluation->getId()]);
        }

        // Récupérer tous les élèves de la classe (entités Student, liées aux notes).
        $students = $studentRepository->findActiveByClassroom($evaluation->getClassroom()->getId());

        // Récupérer les notes existantes
        $existingGrades = $gradeRepository->findByEvaluation($evaluation->getId());
        $gradesById = [];
        foreach ($existingGrades as $grade) {
            $gradesById[$grade->getStudent()->getId()] = $grade;
        }

        // Traiter la soumission du formulaire (bouton « Valider » : enregistre + valide)
        if ($request->isMethod('POST')) {
            $gradesData = $request->request->all('grades');

            foreach ($students as $student) {
                $value = $gradesData[$student->getId()]['value'] ?? null;
                $value = is_string($value) ? trim($value) : $value;

                $grade = $gradesById[$student->getId()] ?? null;

                // Aucune note saisie : on n'enregistre rien (et on n'écrase pas).
                if ($value === null || $value === '') {
                    continue;
                }

                if (!$grade) {
                    $grade = new Grade();
                    $grade->setEvaluation($evaluation);
                    $grade->setStudent($student);
                    $entityManager->persist($grade);
                }

                $grade->setEnteredBy($this->getUser());
                $grade->setValue($value);
                $grade->setStatus(null);
                $grade->setComment(null);
            }

            // Valider l'évaluation (verrouille les notes jusqu'à « Annuler »).
            $evaluation->setIsValidated(true);
            $entityManager->flush();
            $this->addFlash('success', 'Les notes ont été enregistrées et l\'évaluation a été validée.');

            return $this->redirectToRoute('admin_evaluation_grades', ['id' => $evaluation->getId()]);
        }

        $statistics = $gradeRepository->getEvaluationStatistics($evaluation->getId());

        return $this->render('evaluation/grades.html.twig', [
            'evaluation' => $evaluation,
            'students' => $students,
            'grades' => $gradesById,
            'statistics' => $statistics,
            'editable' => $evaluation->isEditable(),
        ]);
    }

    /**
     * Annule la validation pour permettre de modifier les notes
     * (impossible si un bulletin a déjà été généré).
     */
    #[Route('/{id}/grades/cancel', name: 'admin_evaluation_grades_cancel', methods: ['POST'])]
    public function cancelValidation(
        Request $request,
        Evaluation $evaluation,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('cancel_validation'.$evaluation->getId(), $request->getPayload()->getString('_token'))) {
            if ($evaluation->isLockedByBulletin()) {
                $this->addFlash('warning', 'Modification impossible : un bulletin a déjà été généré pour cette évaluation.');
            } else {
                $evaluation->setIsValidated(false);
                $entityManager->flush();
                $this->addFlash('info', 'Validation annulée. Vous pouvez à nouveau modifier les notes.');
            }
        }

        return $this->redirectToRoute('admin_evaluation_grades', ['id' => $evaluation->getId()]);
    }

    #[Route('/{id}/publish', name: 'admin_evaluation_publish', methods: ['POST'])]
    public function publish(
        Request $request,
        Evaluation $evaluation,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('publish'.$evaluation->getId(), $request->getPayload()->getString('_token'))) {
            $evaluation->setIsPublished(!$evaluation->isPublished());
            $entityManager->flush();

            $status = $evaluation->isPublished() ? 'publiée' : 'dépubliée';
            $this->addFlash('success', "L'évaluation a été {$status} avec succès.");
        }

        return $this->redirectToRoute('admin_evaluation_show', ['id' => $evaluation->getId()]);
    }
}

