<?php

namespace App\Controller;

use App\Entity\Classroom;
use App\Entity\Course;
use App\Form\CourseType;
use App\Repository\ClassroomRepository;
use App\Repository\CourseRepository;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/courses', name: 'admin_course_')]
#[IsGranted('ROLE_ADMIN')]
class CourseController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(
        CourseRepository $courseRepository,
        ClassroomRepository $classroomRepository,
        SchoolContextService $contextService,
        Request $request
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();
        $currentSchoolYear = $contextService->getCurrentSchoolYear();
        
        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour voir les cours.');
            return $this->render('course/index.html.twig', [
                'courses' => [],
                'classrooms' => [],
                'current_school' => null,
            ]);
        }

        $schoolId = $currentSchool->getId();
        $yearId = $currentSchoolYear?->getId();

        // Récupérer les classes de l'établissement
        $classrooms = $classroomRepository->findBySchoolAndYear($schoolId, $yearId);
        
        // Filtrer par classe si demandé
        $classroomId = $request->query->get('classroom');
        $courses = [];
        
        if ($classroomId) {
            $courses = $courseRepository->findByClassroom($classroomId);
        }

        return $this->render('course/index.html.twig', [
            'courses' => $courses,
            'classrooms' => $classrooms,
            'selected_classroom' => $classroomId,
            'current_school' => $currentSchool,
            'current_school_year' => $currentSchoolYear,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $course = new Course();
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($course);
            $entityManager->flush();

            $this->addFlash('success', 'Le cours a été créé avec succès.');

            return $this->redirectToRoute('admin_course_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('course/new.html.twig', [
            'course' => $course,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/show', name: 'show', methods: ['GET'])]
    public function show(Course $course): Response
    {
        return $this->render('course/show.html.twig', [
            'course' => $course,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Course $course, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Le cours a été modifié avec succès.');

            return $this->redirectToRoute('admin_course_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('course/edit.html.twig', [
            'course' => $course,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Course $course, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$course->getId(), $request->request->get('_token'))) {
            $entityManager->remove($course);
            $entityManager->flush();

            $this->addFlash('success', 'Le cours a été supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_course_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/schedule/{id}', name: 'schedule', methods: ['GET'])]
    public function schedule(Classroom $classroom, CourseRepository $courseRepository, \App\Repository\TimeSlotRepository $timeSlotRepository): Response
    {
        $schedule = $courseRepository->findScheduleByClassroom($classroom->getId());
        
        // Récupérer les plages horaires de l'établissement
        $timeSlots = $timeSlotRepository->findBySchool($classroom->getSchool()->getId());

        return $this->render('course/schedule.html.twig', [
            'classroom' => $classroom,
            'schedule' => $schedule,
            'time_slots' => $timeSlots,
        ]);
    }
}

