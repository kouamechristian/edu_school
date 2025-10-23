<?php

namespace App\Controller;

use App\Entity\Evaluation;
use App\Entity\Grade;
use App\Form\EvaluationType;
use App\Repository\ClassroomRepository;
use App\Repository\EvaluationRepository;
use App\Repository\GradeRepository;
use App\Repository\PeriodRepository;
use App\Repository\UserRepository;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/evaluations')]
class EvaluationController extends AbstractController
{
    #[Route('/', name: 'admin_evaluation_index', methods: ['GET'])]
    public function index(
        EvaluationRepository $evaluationRepository,
        ClassroomRepository $classroomRepository,
        PeriodRepository $periodRepository,
        SchoolContextService $contextService,
        Request $request
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

        $selectedClassroom = $request->query->getint('classroom');
        $selectedPeriod = $request->query->get('period');

        if ($selectedClassroom && $selectedPeriod) {
            $evaluations = $evaluationRepository->findByClassroomAndPeriod($selectedClassroom, $selectedPeriod);
        } elseif ($selectedClassroom) {
            $evaluations = $evaluationRepository->findByClassroom($selectedClassroom);
        } elseif ($selectedPeriod) {
            $evaluations = $evaluationRepository->findByPeriod($selectedPeriod);
        } else {
            $evaluations = [];
        }

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
    public function delete(
        Request $request,
        Evaluation $evaluation,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('delete'.$evaluation->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($evaluation);
            $entityManager->flush();

            $this->addFlash('success', 'L\'évaluation a été supprimée avec succès.');
        }

        return $this->redirectToRoute('admin_evaluation_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/grades', name: 'admin_evaluation_grades', methods: ['GET', 'POST'])]
    public function grades(
        Evaluation $evaluation,
        GradeRepository $gradeRepository,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        Request $request
    ): Response {
        // Récupérer tous les élèves de la classe
        $students = $userRepository->findByClassroom($evaluation->getClassroom()->getId());
        
        // Récupérer les notes existantes
        $existingGrades = $gradeRepository->findByEvaluation($evaluation->getId());
        $gradesById = [];
        foreach ($existingGrades as $grade) {
            $gradesById[$grade->getStudent()->getId()] = $grade;
        }

        // Traiter la soumission du formulaire
        if ($request->isMethod('POST')) {
            $gradesData = $request->request->all('grades');
            
            foreach ($students as $student) {
                $gradeData = $gradesData[$student->getId()] ?? null;
                
                if (!$gradeData) {
                    continue;
                }

                // Récupérer ou créer la note
                $grade = $gradesById[$student->getId()] ?? new Grade();
                $grade->setEvaluation($evaluation);
                $grade->setStudent($student);
                $grade->setEnteredBy($this->getUser());

                // Valeur ou statut
                if (!empty($gradeData['status'])) {
                    $grade->setStatus($gradeData['status']);
                    $grade->setValue(null);
                } else {
                    $grade->setValue($gradeData['value'] ?? null);
                    $grade->setStatus(null);
                }

                $grade->setComment($gradeData['comment'] ?? null);

                if (!$grade->getId()) {
                    $entityManager->persist($grade);
                }
            }

            $entityManager->flush();
            $this->addFlash('success', 'Les notes ont été enregistrées avec succès.');
            
            return $this->redirectToRoute('admin_evaluation_grades', ['id' => $evaluation->getId()]);
        }

        $statistics = $gradeRepository->getEvaluationStatistics($evaluation->getId());

        return $this->render('evaluation/grades.html.twig', [
            'evaluation' => $evaluation,
            'students' => $students,
            'grades' => $gradesById,
            'statistics' => $statistics,
        ]);
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

