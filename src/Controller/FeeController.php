<?php

namespace App\Controller;

use App\Controller\Concern\HandlesEntityDeletion;
use App\Entity\Fee;
use App\Entity\FeeSchedule;
use App\Entity\StudentFee;
use App\Form\FeeType;
use App\Repository\FeeRepository;
use App\Repository\FeeScheduleRepository;
use App\Repository\StudentFeeRepository;
use App\Repository\StudentRepository;
use App\Service\FeeAssignmentService;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/fees', name: 'admin_fee_')]
#[IsGranted('ROLE_CAISSE')]
class FeeController extends AbstractController
{
    use HandlesEntityDeletion;

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, FeeRepository $feeRepository, SchoolContextService $contextService): Response
    {
        // Récupérer l'établissement courant
        $currentSchool = $contextService->getCurrentSchool();
        
        // Si pas d'établissement sélectionné, afficher un message
        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour voir les frais.');
            return $this->render('fee/index.html.twig', [
                'fees' => [],
                'stats' => [],
                'current_type' => null,
                'current_category' => null,
                'current_frequency' => null,
                'search_term' => null,
                'current_school' => null,
            ]);
        }

        $schoolId = $currentSchool->getId();

        $type = $request->query->get('type');
        $category = $request->query->get('category');
        $frequency = $request->query->get('frequency');
        $search = $request->query->get('search');

        if ($search) {
            $fees = $feeRepository->searchByNameOrCode($search);
            $fees = array_filter($fees, function($fee) use ($schoolId) {
                return $fee->getSchool()->getId() === $schoolId;
            });
        } elseif ($type) {
            $fees = $feeRepository->findByType($type);
            $fees = array_filter($fees, function($fee) use ($schoolId) {
                return $fee->getSchool()->getId() === $schoolId;
            });
        } elseif ($category) {
            $fees = $feeRepository->findByCategory($category);
            $fees = array_filter($fees, function($fee) use ($schoolId) {
                return $fee->getSchool()->getId() === $schoolId;
            });
        } elseif ($frequency) {
            $fees = $feeRepository->findByFrequency($frequency);
            $fees = array_filter($fees, function($fee) use ($schoolId) {
                return $fee->getSchool()->getId() === $schoolId;
            });
        } else {
            $fees = $feeRepository->findBySchool($currentSchool);
        }

        $stats = [
            'total' => count($fees),
            'by_type' => $feeRepository->countByType(),
            'by_frequency' => $feeRepository->countByFrequency(),
            'total_amount' => $feeRepository->getTotalAmountBySchool($currentSchool)
        ];

        return $this->render('fee/index.html.twig', [
            'fees' => $fees,
            'stats' => $stats,
            'current_type' => $type,
            'current_category' => $category,
            'current_frequency' => $frequency,
            'search_term' => $search,
            'current_school' => $currentSchool,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $entityManager,
        SchoolContextService $contextService,
        FeeAssignmentService $feeAssignmentService,
        FeeRepository $feeRepository
    ): Response {
        $fee = new Fee();
        $currentSchool = $contextService->getCurrentSchool();
        
        if ($currentSchool) {
            $fee->setSchool($currentSchool);
        }

        $form = $this->createForm(FeeType::class, $fee, [
            'current_school' => $currentSchool,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $fee->setSchool($currentSchool);
            $fee->setCode($this->generateFeeCode($fee, $feeRepository));
            $entityManager->persist($fee);
            $entityManager->flush();

            if ($fee->getCategory() === 'scolarite') {
                $count = $feeAssignmentService->assignScolariteFeeToAllStudents($fee);
                $entityManager->flush();

                if ($count > 0) {
                    $this->addFlash('info', sprintf('Frais de scolarité automatiquement affecté à %d élève(s).', $count));
                }
            }

            $this->addFlash('success', 'Le frais a été créé avec succès.');

            return $this->redirectToRoute('admin_fee_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('fee/new.html.twig', [
            'fee' => $fee,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Fee $fee, StudentFeeRepository $studentFeeRepository): Response
    {
        $studentFees = $studentFeeRepository->findByFee($fee->getId());

        return $this->render('fee/show.html.twig', [
            'fee' => $fee,
            'student_fees' => $studentFees,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request, 
        Fee $fee, 
        EntityManagerInterface $entityManager,
        FeeAssignmentService $feeAssignmentService,
        SchoolContextService $contextService
    ): Response {
        $oldCategory = $fee->getCategory();
        $currentSchool = $fee->getSchool() ?? $contextService->getCurrentSchool();
        $form = $this->createForm(FeeType::class, $fee, [
            'current_school' => $currentSchool,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            if ($oldCategory !== 'scolarite' && $fee->getCategory() === 'scolarite') {
                $count = $feeAssignmentService->assignScolariteFeeToAllStudents($fee);
                $entityManager->flush();

                if ($count > 0) {
                    $this->addFlash('info', sprintf('Frais de scolarité automatiquement affecté à %d élève(s).', $count));
                }
            }

            $this->addFlash('success', 'Le frais a été modifié avec succès.');

            return $this->redirectToRoute('admin_fee_edit', ['id' => $fee->getId()]);
        }

        $activeTab = $request->query->get('tab', 'fee');

        return $this->render('fee/edit.html.twig', [
            'fee' => $fee,
            'form' => $form,
            'active_tab' => $activeTab,
        ]);
    }

    #[Route('/{id}/schedule/add', name: 'schedule_add', methods: ['POST'])]
    public function addSchedule(
        Request $request,
        Fee $fee,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('schedule_add' . $fee->getId(), $request->request->get('_token'))) {
            $orderNumber = (int) $request->request->get('order_number');
            $amount = $request->request->get('amount');
            $dueDate = $request->request->get('due_date');

            if ($orderNumber > 0 && $amount > 0 && $dueDate) {
                $schedule = new FeeSchedule();
                $schedule->setFee($fee);
                $schedule->setOrderNumber($orderNumber);
                $schedule->setAmount((string) $amount);
                $schedule->setDueDate(new \DateTime($dueDate));

                $entityManager->persist($schedule);
                $entityManager->flush();

                $this->addFlash('success', sprintf('Échéance #%d ajoutée.', $orderNumber));
            } else {
                $this->addFlash('error', 'Veuillez remplir tous les champs correctement.');
            }
        }

        return $this->redirectToRoute('admin_fee_edit', ['id' => $fee->getId(), 'tab' => 'schedule']);
    }

    #[Route('/{id}/schedule/{scheduleId}/delete', name: 'schedule_delete', methods: ['POST'])]
    public function deleteSchedule(
        Request $request,
        Fee $fee,
        int $scheduleId,
        FeeScheduleRepository $scheduleRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $schedule = $scheduleRepository->find($scheduleId);

        if ($schedule && $schedule->getFee()->getId() === $fee->getId()
            && $this->isCsrfTokenValid('schedule_delete' . $scheduleId, $request->request->get('_token'))) {
            $this->deleteEntity(
                $entityManager,
                $schedule,
                'Échéance supprimée.',
                'Suppression impossible : cette échéance est encore liée à des paiements.'
            );
        }

        return $this->redirectToRoute('admin_fee_edit', ['id' => $fee->getId(), 'tab' => 'schedule']);
    }

    #[Route('/{id}/assign', name: 'assign', methods: ['GET', 'POST'])]
    public function assign(
        Request $request,
        Fee $fee,
        StudentRepository $studentRepository,
        StudentFeeRepository $studentFeeRepository,
        FeeAssignmentService $feeAssignmentService,
        EntityManagerInterface $entityManager
    ): Response {
        $levelId = $fee->getLevel()?->getId();
        $schoolId = $fee->getSchool()->getId();

        // Élèves du niveau (ou de l'établissement si pas de niveau)
        $students = $levelId
            ? $studentRepository->findActiveBySchoolAndLevel($schoolId, $levelId)
            : $studentRepository->findBySchool($schoolId);

        // Identifier les élèves déjà affectés
        $assignedStudentIds = [];
        $existingAssignments = $studentFeeRepository->findByFee($fee->getId());
        foreach ($existingAssignments as $sf) {
            $assignedStudentIds[] = $sf->getStudent()->getId();
        }

        if ($request->isMethod('POST')) {
            $selectedIds = $request->request->all('student_ids');

            // Affecter les élèves sélectionnés
            $count = 0;
            foreach ($selectedIds as $studentId) {
                $student = $studentRepository->find($studentId);
                if ($student && $feeAssignmentService->assignFeeToStudent($fee, $student)) {
                    $count++;
                }
            }

            $entityManager->flush();
            $this->addFlash('success', sprintf('%d élève(s) affecté(s) au frais "%s".', $count, $fee->getName()));

            return $this->redirectToRoute('admin_fee_show', ['id' => $fee->getId()]);
        }

        return $this->render('fee/assign.html.twig', [
            'fee' => $fee,
            'students' => $students,
            'assigned_student_ids' => $assignedStudentIds,
        ]);
    }

    #[Route('/{id}/unassign/{studentFeeId}', name: 'unassign', methods: ['POST'])]
    public function unassign(
        Request $request,
        Fee $fee,
        int $studentFeeId,
        StudentFeeRepository $studentFeeRepository,
        FeeAssignmentService $feeAssignmentService,
        EntityManagerInterface $entityManager
    ): Response {
        $studentFee = $studentFeeRepository->find($studentFeeId);

        if ($studentFee && $this->isCsrfTokenValid('unassign' . $studentFeeId, $request->request->get('_token'))) {
            try {
                $feeAssignmentService->unassignFeeFromStudent($studentFee);
                $entityManager->flush();
                $this->addFlash('success', 'L\'affectation a été retirée.');
            } catch (\LogicException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_fee_show', ['id' => $fee->getId()]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Fee $fee, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$fee->getId(), $request->request->get('_token'))) {
            $this->deleteEntity(
                $entityManager,
                $fee,
                'Le frais a été supprimé avec succès.',
                'Suppression impossible : ce frais est encore attribué à des élèves ou lié à des paiements.'
            );
        }

        return $this->redirectToRoute('admin_fee_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(Request $request, Fee $fee, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('toggle'.$fee->getId(), $request->request->get('_token'))) {
            $fee->setIsActive(!$fee->isActive());
            $entityManager->flush();

            $status = $fee->isActive() ? 'activé' : 'désactivé';
            $this->addFlash('success', "Le frais a été {$status} avec succès.");
        }

        return $this->redirectToRoute('admin_fee_index', [], Response::HTTP_SEE_OTHER);
    }

    private function generateFeeCode(Fee $fee, FeeRepository $feeRepository): string
    {
        $prefix = match($fee->getCategory()) {
            'scolarite' => 'SCOL',
            'article' => 'ART',
            'autre_frais' => 'AUTRE',
            default => 'FRAIS',
        };

        $count = $feeRepository->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.code LIKE :prefix')
            ->setParameter('prefix', $prefix . '-%')
            ->getQuery()
            ->getSingleScalarResult();

        do {
            $count++;
            $code = sprintf('%s-%04d', $prefix, $count);
            $exists = $feeRepository->findOneBy(['code' => $code]);
        } while ($exists);

        return $code;
    }
}
