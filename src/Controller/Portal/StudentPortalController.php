<?php

namespace App\Controller\Portal;

use App\Entity\Homework;
use App\Entity\Student;
use App\Entity\User;
use App\Repository\CourseRepository;
use App\Repository\HomeworkRepository;
use App\Repository\PeriodRepository;
use App\Repository\StudentRepository;
use App\Repository\TimeSlotRepository;
use App\Service\ParentPortalService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Espace Élève — consultation en lecture seule (infos, notes & moyenne, emploi du
 * temps, absences) et réception des exercices de maison.
 *
 * Sécurité : ROLE_ELEVE exigé au niveau de la classe. L'élève est TOUJOURS résolu
 * depuis le compte connecté (aucun identifiant d'élève dans les URLs) : pas de
 * surface IDOR. Les devoirs consultés sont strictement bornés à la classe de l'élève.
 *
 * Réutilise ParentPortalService (agrégations notes/absences/périodes, typées sur Student).
 */
#[Route('/eleve')]
#[IsGranted('ROLE_ELEVE')]
class StudentPortalController extends AbstractController
{
    public function __construct(
        private readonly ParentPortalService $portal,
        private readonly StudentRepository $studentRepository,
    ) {
    }

    #[Route('', name: 'eleve_dashboard', methods: ['GET'])]
    public function dashboard(HomeworkRepository $homeworkRepository): Response
    {
        $student = $this->getCurrentStudent();
        $period = $this->portal->getCurrentPeriod($student);
        $classroom = $student->getClassroom();

        return $this->render('eleve/dashboard.html.twig', [
            'student' => $student,
            'period' => $period,
            'academic' => $this->portal->getAcademicReport($student, $period),
            'attendance' => $this->portal->getAttendanceReport($student, $period),
            'homework' => $classroom ? $homeworkRepository->findUpcomingByClassroom($classroom->getId()) : [],
        ]);
    }

    #[Route('/profil', name: 'eleve_profile', methods: ['GET'])]
    public function profile(): Response
    {
        return $this->render('eleve/profile.html.twig', [
            'student' => $this->getCurrentStudent(),
        ]);
    }

    #[Route('/notes', name: 'eleve_grades', methods: ['GET'])]
    public function grades(Request $request, PeriodRepository $periodRepository): Response
    {
        $student = $this->getCurrentStudent();
        $period = $this->resolvePeriod($student, $request);

        return $this->render('eleve/grades.html.twig', [
            'student' => $student,
            'periods' => $this->portal->getPeriods($student),
            'period' => $period,
            'academic' => $this->portal->getAcademicReport($student, $period),
        ]);
    }

    #[Route('/absences', name: 'eleve_absences', methods: ['GET'])]
    public function absences(Request $request, PeriodRepository $periodRepository): Response
    {
        $student = $this->getCurrentStudent();
        $period = $this->resolvePeriod($student, $request);

        return $this->render('eleve/absences.html.twig', [
            'student' => $student,
            'periods' => $this->portal->getPeriods($student),
            'period' => $period,
            'attendance' => $this->portal->getAttendanceReport($student, $period),
        ]);
    }

    #[Route('/emploi-du-temps', name: 'eleve_schedule', methods: ['GET'])]
    public function schedule(CourseRepository $courseRepository, TimeSlotRepository $timeSlotRepository): Response
    {
        $student = $this->getCurrentStudent();
        $classroom = $student->getClassroom();

        $schedule = $classroom ? $courseRepository->findScheduleByClassroom($classroom->getId()) : [];
        $timeSlots = $classroom ? $timeSlotRepository->findBySchool($classroom->getSchool()?->getId()) : [];

        return $this->render('eleve/schedule.html.twig', [
            'student' => $student,
            'classroom' => $classroom,
            'schedule' => $schedule,
            'time_slots' => $timeSlots,
        ]);
    }

    #[Route('/devoirs', name: 'eleve_homework', methods: ['GET'])]
    public function homework(HomeworkRepository $homeworkRepository): Response
    {
        $student = $this->getCurrentStudent();
        $classroom = $student->getClassroom();

        return $this->render('eleve/homework/index.html.twig', [
            'student' => $student,
            'classroom' => $classroom,
            'homeworks' => $classroom ? $homeworkRepository->findByClassroom($classroom->getId()) : [],
        ]);
    }

    #[Route('/devoirs/{id}', name: 'eleve_homework_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function homeworkShow(Homework $homework): Response
    {
        $this->assertOwnClassroom($homework);

        return $this->render('eleve/homework/show.html.twig', [
            'student' => $this->getCurrentStudent(),
            'homework' => $homework,
        ]);
    }

    #[Route('/devoirs/{id}/telecharger', name: 'eleve_homework_download', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function homeworkDownload(Homework $homework): Response
    {
        $this->assertOwnClassroom($homework);

        if (!$homework->hasAttachment()) {
            throw $this->createNotFoundException();
        }

        $path = $this->getParameter('kernel.project_dir') . '/public/' . ltrim((string) $homework->getAttachmentPath(), '/');
        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $response->getFile()->getFilename(),
        );

        return $response;
    }

    /**
     * Vérifie que le devoir vise bien la classe de l'élève connecté (anti-IDOR).
     */
    private function assertOwnClassroom(Homework $homework): void
    {
        $classroom = $this->getCurrentStudent()->getClassroom();
        if (!$classroom || $homework->getClassroom()?->getId() !== $classroom->getId()) {
            throw $this->createNotFoundException();
        }
    }

    /**
     * Résout la période demandée (?period=ID) contre les périodes de l'élève ; à
     * défaut, retourne la période courante.
     */
    private function resolvePeriod(Student $student, Request $request): mixed
    {
        $requestedId = $request->query->getInt('period');

        if ($requestedId > 0) {
            foreach ($this->portal->getPeriods($student) as $period) {
                if ($period->getId() === $requestedId) {
                    return $period;
                }
            }
        }

        return $this->portal->getCurrentPeriod($student);
    }

    /**
     * L'élève rattaché au compte connecté. 404 si le compte n'est lié à aucune fiche.
     */
    private function getCurrentStudent(): Student
    {
        /** @var User $user */
        $user = $this->getUser();

        $student = $this->studentRepository->findOneBy(['studentUser' => $user]);
        if (!$student instanceof Student) {
            throw $this->createNotFoundException('Aucune fiche élève rattachée à ce compte.');
        }

        return $student;
    }
}
