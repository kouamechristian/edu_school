<?php

namespace App\Controller;

use App\Entity\Absence;
use App\Entity\AbsenceType;
use App\Entity\Student;
use App\Entity\Period;
use App\Form\AbsenceType as AbsenceFormType;
use App\Form\AbsenceTypeType;
use App\Repository\AbsenceRepository;
use App\Repository\AbsenceTypeRepository;
use App\Repository\StudentRepository;
use App\Repository\PeriodRepository;
use App\Repository\ClassroomRepository;
use App\Service\AttendanceService;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/absences')]
#[IsGranted('ROLE_EDUCATEUR')]
class AbsenceController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/', name: 'admin_absence_index', methods: ['GET'])]
    public function index(
        AbsenceRepository $absenceRepository,
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
            return $this->render('absence/index.html.twig', [
                'absences' => [],
                'classrooms' => [],
                'periods' => [],
            ]);
        }

        $classrooms = $classroomRepository->findBySchool($currentSchool->getId());
        $periods = $periodRepository->findBySchoolAndYear(
            $currentSchool->getId(),
            $currentYear->getId()
        );

        // Filtres
        $classroomId = $request->query->getInt('classroom');
        $periodId = $request->query->getInt('period');
        $status = $request->query->get('status');
        $date = $request->query->get('date');

        $absences = $absenceRepository->findBySchool($currentSchool->getId());

        // Appliquer les filtres
        if ($classroomId) {
            $absences = $absenceRepository->findByClassroom($classroomId);
        }
        
        if ($periodId) {
            $absences = array_filter($absences, function($absence) use ($periodId) {
                return $absence->getPeriod() && $absence->getPeriod()->getId() === $periodId;
            });
        }
        
        if ($status) {
            $absences = array_filter($absences, function($absence) use ($status) {
                return $absence->getJustificationStatus() === $status;
            });
        }
        
        if ($date) {
            $filterDate = new \DateTime($date);
            $absences = array_filter($absences, function($absence) use ($filterDate) {
                return $absence->getDate()->format('Y-m-d') === $filterDate->format('Y-m-d');
            });
        }

        $absences = $paginator->paginate($absences, $request->query->getInt('page', 1), 50);

        return $this->render('absence/index.html.twig', [
            'absences' => $absences,
            'classrooms' => $classrooms,
            'periods' => $periods,
            'selected_classroom' => $classroomId,
            'selected_period' => $periodId,
            'selected_status' => $status,
            'selected_date' => $date,
            'current_school' => $currentSchool,
            'current_year' => $currentYear,
        ]);
    }

    #[Route('/new', name: 'admin_absence_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        SchoolContextService $contextService
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();
        
        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement.');
            return $this->redirectToRoute('admin_absence_index');
        }

        $absence = new Absence();
        $absence->setSchool($currentSchool);
        $absence->setRecordedBy($this->getUser());

        $form = $this->createForm(AbsenceFormType::class, $absence);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($absence);
            $this->entityManager->flush();

            $this->addFlash('success', 'Absence enregistrée avec succès.');
            return $this->redirectToRoute('admin_absence_index');
        }

        return $this->render('absence/new.html.twig', [
            'absence' => $absence,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_absence_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Absence $absence): Response
    {
        return $this->render('absence/show.html.twig', [
            'absence' => $absence,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_absence_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        Request $request,
        Absence $absence
    ): Response {
        $form = $this->createForm(AbsenceFormType::class, $absence);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Absence modifiée avec succès.');
            return $this->redirectToRoute('admin_absence_index');
        }

        return $this->render('absence/edit.html.twig', [
            'absence' => $absence,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/justify', name: 'admin_absence_justify', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function justify(
        Request $request,
        Absence $absence
    ): Response {
        if ($request->isMethod('POST')) {
            $justificationStatus = $request->request->get('justification_status');
            $justification = $request->request->get('justification');
            $notes = $request->request->get('notes');

            $absence->setJustificationStatus($justificationStatus);
            $absence->setJustification($justification);
            $absence->setNotes($notes);
            $absence->setJustifiedBy($this->getUser());
            $absence->setJustificationDate(new \DateTime());

            $this->entityManager->flush();

            $statusLabel = match($justificationStatus) {
                'justified' => 'justifiée',
                'unjustified' => 'non justifiée',
                default => 'en attente'
            };

            $this->addFlash('success', "Absence marquée comme {$statusLabel}.");
            return $this->redirectToRoute('admin_absence_show', ['id' => $absence->getId()]);
        }

        return $this->render('absence/justify.html.twig', [
            'absence' => $absence,
        ]);
    }

    #[Route('/{id}/set-status', name: 'admin_absence_set_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function setStatus(
        Request $request,
        Absence $absence
    ): Response {
        $status = $request->request->get('justification_status');

        if (!$this->isCsrfTokenValid('set_status' . $absence->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('admin_absence_index');
        }

        if (!in_array($status, ['justified', 'unjustified', 'pending'], true)) {
            $this->addFlash('danger', 'Statut invalide.');
            return $this->redirectToRoute('admin_absence_index');
        }

        $absence->setJustificationStatus($status);

        if ($status === 'pending') {
            $absence->setJustifiedBy(null);
            $absence->setJustificationDate(null);
        } else {
            $absence->setJustifiedBy($this->getUser());
            $absence->setJustificationDate(new \DateTime());
        }

        $this->entityManager->flush();

        $statusLabel = match($status) {
            'justified' => 'justifiée',
            'unjustified' => 'non justifiée',
            default => 'en attente'
        };
        $this->addFlash('success', "Absence marquée comme {$statusLabel}.");

        if ($request->request->get('redirect') === 'show') {
            return $this->redirectToRoute('admin_absence_show', ['id' => $absence->getId()]);
        }

        return $this->redirectToRoute('admin_absence_index');
    }

    #[Route('/{id}/delete', name: 'admin_absence_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(
        Request $request,
        Absence $absence
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $absence->getId(), $request->request->get('_token'))) {
            $absence->setIsActive(false);
            $this->entityManager->flush();

            $this->addFlash('success', 'Absence supprimée avec succès.');
        }

        return $this->redirectToRoute('admin_absence_index');
    }

    #[Route('/types', name: 'admin_absence_type_index', methods: ['GET'])]
    public function indexTypes(
        AbsenceTypeRepository $absenceTypeRepository,
        SchoolContextService $contextService,
        Request $request,
        \Knp\Component\Pager\PaginatorInterface $paginator
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();

        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement.');
            return $this->redirectToRoute('admin_absence_index');
        }

        $absenceTypes = $paginator->paginate($absenceTypeRepository->findActiveBySchool($currentSchool->getId()), $request->query->getInt('page', 1), 50);

        return $this->render('absence_type/index.html.twig', [
            'absence_types' => $absenceTypes,
            'current_school' => $currentSchool,
        ]);
    }

    #[Route('/types/new', name: 'admin_absence_type_new', methods: ['GET', 'POST'])]
    public function newType(
        Request $request,
        SchoolContextService $contextService
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();
        
        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement.');
            return $this->redirectToRoute('admin_absence_type_index');
        }

        $absenceType = new AbsenceType();
        $absenceType->setSchool($currentSchool);

        $form = $this->createForm(AbsenceTypeType::class, $absenceType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($absenceType);
            $this->entityManager->flush();

            $this->addFlash('success', 'Type d\'absence créé avec succès.');
            return $this->redirectToRoute('admin_absence_type_index');
        }

        return $this->render('absence_type/new.html.twig', [
            'absence_type' => $absenceType,
            'form' => $form,
        ]);
    }

    #[Route('/types/{id}/edit', name: 'admin_absence_type_edit', methods: ['GET', 'POST'])]
    public function editType(
        Request $request,
        AbsenceType $absenceType
    ): Response {
        $form = $this->createForm(AbsenceTypeType::class, $absenceType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Type d\'absence modifié avec succès.');
            return $this->redirectToRoute('admin_absence_type_index');
        }

        return $this->render('absence_type/edit.html.twig', [
            'absence_type' => $absenceType,
            'form' => $form,
        ]);
    }

    #[Route('/reports/attendance', name: 'admin_absence_attendance_report', methods: ['GET'])]
    public function attendanceReport(
        AttendanceService $attendanceService,
        ClassroomRepository $classroomRepository,
        PeriodRepository $periodRepository,
        SchoolContextService $contextService,
        Request $request
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();
        $currentYear = $contextService->getCurrentSchoolYear();
        
        if (!$currentSchool || !$currentYear) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement et une année scolaire.');
            return $this->redirectToRoute('admin_absence_index');
        }

        $classrooms = $classroomRepository->findBySchool($currentSchool->getId());
        $periods = $periodRepository->findBySchoolAndYear(
            $currentSchool->getId(),
            $currentYear->getId()
        );

        $classroomId = $request->query->getInt('classroom');
        $periodId = $request->query->getInt('period');

        $report = null;
        if ($classroomId && $periodId) {
            $period = $periodRepository->find($periodId);
            if ($period) {
                $report = $attendanceService->calculateClassroomAttendanceStats($classroomId, $period);
            }
        }

        return $this->render('absence/reports/attendance.html.twig', [
            'report' => $report,
            'classrooms' => $classrooms,
            'periods' => $periods,
            'selected_classroom' => $classroomId,
            'selected_period' => $periodId,
            'current_school' => $currentSchool,
            'current_year' => $currentYear,
        ]);
    }

    #[Route('/reports/critical', name: 'admin_absence_critical_report', methods: ['GET'])]
    public function criticalAttendanceReport(
        AttendanceService $attendanceService,
        SchoolContextService $contextService,
        Request $request
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();
        
        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement.');
            return $this->redirectToRoute('admin_absence_index');
        }

        $threshold = (float) $request->query->get('threshold', 75.0);
        $criticalStudents = $attendanceService->findStudentsWithCriticalAttendance($currentSchool->getId(), $threshold);

        return $this->render('absence/reports/critical.html.twig', [
            'critical_students' => $criticalStudents,
            'threshold' => $threshold,
            'current_school' => $currentSchool,
        ]);
    }
}
