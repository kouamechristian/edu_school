<?php

namespace App\Controller;

use App\Entity\Student;
use App\Entity\PreRegistration;
use App\Form\StudentType;
use App\Repository\FeeRepository;
use App\Repository\StudentRepository;
use App\Repository\StudentFeeRepository;
use App\Repository\PaymentRepository;
use App\Repository\CashRegisterRepository;
use App\Repository\PreRegistrationRepository;
use App\Repository\ClassroomRepository;
use App\Repository\LevelRepository;
use App\Service\FeeAssignmentService;
use App\Service\SchoolContextService;
use App\Entity\Payment;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\PaymentReceiptService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/students', name: 'admin_student_')]
#[IsGranted('ROLE_ADMIN')]
class StudentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SchoolContextService $schoolContextService,
        private FeeAssignmentService $feeAssignmentService
    ) {}

    #[Route('/transfer', name: 'transfer', methods: ['GET', 'POST'])]
    public function transfer(Request $request): Response
    {
        // TODO: Implémenter la logique de transfert d'élèves
        return $this->render('student/transfer.html.twig');
    }

    #[Route('/archive', name: 'archive', methods: ['GET'])]
    public function archive(StudentRepository $studentRepository): Response
    {
        $school = $this->schoolContextService->getCurrentSchool();
        
        $archivedStudents = $studentRepository->createQueryBuilder('s')
            ->leftJoin('s.school', 'school')
            ->where('school.id = :schoolId')
            ->andWhere('s.status IN (:statuses)')
            ->setParameter('schoolId', $school->getId())
            ->setParameter('statuses', ['non_affecte'])
            ->orderBy('s.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('student/archive.html.twig', [
            'students' => $archivedStudents,
        ]);
    }

    #[Route('/enrollment', name: 'enrollment', methods: ['GET', 'POST'])]
    public function enrollment(
        Request $request, 
        PreRegistrationRepository $preRegistrationRepository,
        ClassroomRepository $classroomRepository,
        LevelRepository $levelRepository,
        StudentRepository $studentRepository
    ): Response {
        $school = $this->schoolContextService->getCurrentSchool();
        $schoolYear = $this->schoolContextService->getCurrentSchoolYear();
        
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour voir les inscriptions.');
            return $this->redirectToRoute('admin_student_index');
        }
        
        // Récupérer les préinscriptions validées prêtes pour l'inscription
        $preRegistrations = $preRegistrationRepository->findReadyForEnrollment($school->getId());
        
        // Récupérer les classes et niveaux de l'établissement
        $classrooms = $classroomRepository->findBySchoolAndYear($school->getId(), $schoolYear?->getId());
        $levels = $levelRepository->findBySchool($school->getId());
        $enrollmentsThisYear = [];
        if ($schoolYear) {
            $enrollmentsThisYear = $studentRepository->createQueryBuilder('s')
                ->leftJoin('s.school', 'school')
                ->leftJoin('s.schoolYear', 'sy')
                ->leftJoin('s.classroom', 'c')
                ->leftJoin('s.level', 'l')
                ->where('school.id = :schoolId')
                ->andWhere('sy.id = :schoolYearId')
                ->setParameter('schoolId', $school->getId())
                ->setParameter('schoolYearId', $schoolYear->getId())
                ->orderBy('s.createdAt', 'DESC')
                ->getQuery()
                ->getResult();
        }

        if ($request->isMethod('POST')) {
            $studentIds = $request->request->all('students');
            $classId = $request->request->get('class_id');

            if (empty($studentIds)) {
                $this->addFlash('error', 'Veuillez sélectionner au moins un élève.');
                return $this->render('student/enrollment.html.twig', [
                    'preRegistrations' => $preRegistrations,
                    'classrooms' => $classrooms,
                    'levels' => $levels,
                    'preRegistrationsData' => $preRegistrationsData,
                    'classroomsByLevel' => $classroomsByLevel,
                ]);
            }

            if (!$classId) {
                $this->addFlash('error', 'Veuillez sélectionner une classe.');
                return $this->render('student/enrollment.html.twig', [
                    'preRegistrations' => $preRegistrations,
                    'classrooms' => $classrooms,
                    'levels' => $levels,
                    'preRegistrationsData' => $preRegistrationsData,
                    'classroomsByLevel' => $classroomsByLevel,
                ]);
            }

            $enrolledCount = 0;
            $feeCount = 0;
            foreach ($studentIds as $preRegistrationId) {
                $preRegistration = $preRegistrationRepository->find($preRegistrationId);
                if ($preRegistration && $preRegistration->getStatus() === 'validated') {
                    $student = $this->createStudentFromPreRegistration($preRegistration, $classId, $schoolYear);
                    $this->entityManager->persist($student);
                    
                    $preRegistration->setStatus('enrolled');
                    $preRegistration->setEnrolledAt(new \DateTime());
                    $student->setPreRegistration($preRegistration);
                    // Important: permettre de récupérer l'élève via $preRegistration->getStudent()
                    // (lors de la 2e boucle d'auto-affectation des frais)
                    $preRegistration->setStudent($student);
                    
                    $enrolledCount++;
                }
            }

            $this->entityManager->flush();

            foreach ($studentIds as $preRegistrationId) {
                $preRegistration = $preRegistrationRepository->find($preRegistrationId);
                if ($preRegistration && $preRegistration->getStudent()) {
                    $feeCount += $this->feeAssignmentService->assignScolariteFeesForStudent($preRegistration->getStudent());
                }
            }

            if ($feeCount > 0) {
                $this->entityManager->flush();
            }

            $message = "{$enrolledCount} élève(s) inscrit(s) avec succès.";
            if ($feeCount > 0) {
                $message .= " {$feeCount} frais automatiquement affecté(s).";
            }
            $this->addFlash('success', $message);
            return $this->redirectToRoute('admin_student_index');
        }

        // Préparer les données pour le JavaScript
        $preRegistrationsData = [];
        foreach ($preRegistrations as $preReg) {
            $preRegistrationsData[] = [
                'id' => $preReg->getId(),
                'levelId' => $preReg->getRequestedLevel()?->getId(),
                'levelName' => $preReg->getRequestedLevel()?->getName(),
            ];
        }

        // Récupérer toutes les classes groupées par niveau
        $classroomsByLevel = [];
        foreach ($classrooms as $classroom) {
            $levelId = $classroom->getLevel()?->getId();
            if ($levelId) {
                if (!isset($classroomsByLevel[$levelId])) {
                    $classroomsByLevel[$levelId] = [];
                }
                $classroomsByLevel[$levelId][] = [
                    'id' => $classroom->getId(),
                    'name' => $classroom->getName(),
                ];
            }
        }

        return $this->render('student/enrollment.html.twig', [
            'preRegistrations' => $preRegistrations,
            'classrooms' => $classrooms,
            'levels' => $levels,
            'preRegistrationsData' => $preRegistrationsData,
            'classroomsByLevel' => $classroomsByLevel,
            'enrollments_this_year' => $enrollmentsThisYear,
            'current_school_year' => $schoolYear,
        ]);
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(StudentRepository $studentRepository, StudentFeeRepository $studentFeeRepository, Request $request): Response
    {
        $school = $this->schoolContextService->getCurrentSchool();
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $level = $request->query->get('level', '');

        $queryBuilder = $studentRepository->createQueryBuilder('s')
            ->leftJoin('s.school', 'school')
            ->leftJoin('s.level', 'l')
            ->leftJoin('s.preRegistration', 'pr')
            ->where('school.id = :schoolId')
            ->setParameter('schoolId', $school->getId())
            ->orderBy('s.lastName', 'ASC');

        if ($search) {
            $queryBuilder->andWhere('s.firstName LIKE :search OR s.lastName LIKE :search OR s.studentNumber LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($status) {
            $queryBuilder->andWhere('s.status = :status')
                ->setParameter('status', $status);
        }

        if ($level) {
            $queryBuilder->andWhere('l.id = :levelId')
                ->setParameter('levelId', $level);
        }

        $students = $queryBuilder->getQuery()->getResult();

        $tuitionData = [];
        foreach ($students as $student) {
            $totalAmount = $studentFeeRepository->getTotalByStudent($student->getId());
            $totalPaid = $studentFeeRepository->getTotalPaidByStudent($student->getId());
            $tuitionData[$student->getId()] = [
                'total' => $totalAmount,
                'paid' => $totalPaid,
                'remaining' => max(0, $totalAmount - $totalPaid),
            ];
        }

        $stats = [
            'total' => count($students),
            'affecte' => count(array_filter($students, fn($s) => $s->getStatus() === 'affecte')),
            'non_affecte' => count(array_filter($students, fn($s) => $s->getStatus() === 'non_affecte')),
        ];

        return $this->render('student/index.html.twig', [
            'students' => $students,
            'tuition_data' => $tuitionData,
            'stats' => $stats,
            'search' => $search,
            'status' => $status,
            'level' => $level,
        ]);
    }


    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(
        Student $student,
        FeeRepository $feeRepository,
        PaymentRepository $paymentRepository,
        CashRegisterRepository $cashRegisterRepository
    ): Response
    {
        $availableFees = [];
        if ($student->getSchool()) {
            $availableFees = $feeRepository->findNonScolariteFeesForSchool($student->getSchool());

            $assignedFeeIds = [];
            foreach ($student->getStudentFees() as $sf) {
                $assignedFeeIds[] = $sf->getFee()->getId();
            }
            $availableFees = array_filter($availableFees, function ($fee) use ($assignedFeeIds) {
                return !in_array($fee->getId(), $assignedFeeIds);
            });
        }

        $cashRegisterOpen = false;
        $currentSchool = $this->schoolContextService->getCurrentSchool();
        $cashier = $this->getUser();
        if ($currentSchool && $cashier instanceof \App\Entity\User) {
            $cashRegisterOpen = (bool) $cashRegisterRepository->findOpenForCashier($currentSchool, $cashier);
        }

        return $this->render('student/show.html.twig', [
            'student' => $student,
            'available_fees' => $availableFees,
            'payments' => $paymentRepository->findByStudent($student),
            'cash_register_open' => $cashRegisterOpen,
        ]);
    }

    #[Route('/{id}/pay', name: 'pay', methods: ['POST'])]
    public function pay(
        Request $request,
        Student $student,
        StudentFeeRepository $studentFeeRepository,
        CashRegisterRepository $cashRegisterRepository,
        PaymentReceiptService $paymentReceiptService
    ): Response {
        if (!$this->isCsrfTokenValid('pay' . $student->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_student_show', ['id' => $student->getId()]);
        }

        $studentFeeId = (int) $request->request->get('student_fee_id');
        $amount = (float) $request->request->get('amount', 0);
        $method = (string) $request->request->get('payment_method', 'espèces');

        if ($amount <= 0) {
            $this->addFlash('error', 'Le montant doit être supérieur à 0.');
            return $this->redirectToRoute('admin_student_show', ['id' => $student->getId()]);
        }

        $studentFee = $studentFeeRepository->find($studentFeeId);
        if (!$studentFee || $studentFee->getStudent()->getId() !== $student->getId()) {
            $this->addFlash('error', 'Frais élève invalide.');
            return $this->redirectToRoute('admin_student_show', ['id' => $student->getId()]);
        }

        $remaining = $studentFee->getRemainingAmount();
        if ($remaining <= 0) {
            $this->addFlash('warning', 'Ce frais est déjà totalement payé.');
            return $this->redirectToRoute('admin_student_show', ['id' => $student->getId()]);
        }

        if ($amount > $remaining + 0.009) {
            $this->addFlash('error', sprintf(
                'Le montant ne peut pas dépasser le reste dû pour ce frais (%s F CFA).',
                number_format($remaining, 0, ',', ' ')
            ));
            return $this->redirectToRoute('admin_student_show', ['id' => $student->getId()]);
        }

        $payment = new Payment();
        $payment->setPaymentNumber('PAY-' . date('Ymd') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT));
        $payment->setStudent($student);
        $payment->setFee($studentFee->getFee());
        $payment->setStudentFee($studentFee);
        $payment->setAmount((string) number_format($amount, 2, '.', ''));
        $payment->setPaymentDate(new \DateTime());
        $payment->setPaymentMethod($method);
        $payment->setStatus('payé');
        $payment->setReference($this->generatePaymentReference($method));
        $payment->setRecordedBy($this->getUser());

        // Chaque caissière doit avoir sa caisse (par établissement)
        $currentSchool = $this->schoolContextService->getCurrentSchool();
        $cashier = $this->getUser();
        if (!$currentSchool || !$cashier instanceof \App\Entity\User) {
            $this->addFlash('error', 'Impossible d\'encaisser: établissement ou utilisateur invalide.');
            return $this->redirectToRoute('admin_student_show', ['id' => $student->getId()]);
        }

        $cashRegister = $cashRegisterRepository->findOpenForCashier($currentSchool, $cashier);
        if (!$cashRegister) {
            $this->addFlash('warning', 'Votre caisse n’est pas ouverte. Veuillez l’ouvrir avant d’enregistrer un paiement.');
            return $this->redirectToRoute('admin_cash_register_open');
        }

        $payment->setCashRegister($cashRegister);

        $newPaid = (float) $studentFee->getPaidAmount() + $amount;
        $studentFee->setPaidAmount((string) number_format($newPaid, 2, '.', ''));

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        if ($payment->getStatus() === 'payé' && !$payment->getReceiptPath()) {
            $paths = $paymentReceiptService->generateAndStore($payment);
            $payment->setReceiptPath($paths['relative_path']);
            $this->entityManager->flush();
        }

        $this->addFlash('success', 'Paiement enregistré. Caissière: ' . ($payment->getRecordedBy()?->getFullName() ?? 'Utilisateur'));
        return $this->redirectToRoute('admin_student_show', ['id' => $student->getId()]);
    }

    private function generatePaymentReference(string $method): string
    {
        $prefix = match ($method) {
            'mobile_money' => 'MM',
            'chèque' => 'CHQ',
            'virement' => 'VIR',
            'carte' => 'CB',
            default => 'ESP',
        };

        return sprintf('%s-%s-%04d', $prefix, date('YmdHis'), random_int(1, 9999));
    }

    #[Route('/{id}/add-fee', name: 'add_fee', methods: ['POST'])]
    public function addFee(
        Request $request,
        Student $student,
        FeeRepository $feeRepository,
        FeeAssignmentService $feeAssignmentService
    ): Response {
        if ($this->isCsrfTokenValid('add_fee' . $student->getId(), $request->request->get('_token'))) {
            $feeIds = $request->request->all('fee_ids');

            $count = 0;
            foreach ($feeIds as $feeId) {
                $fee = $feeRepository->find($feeId);
                if ($fee && $feeAssignmentService->assignFeeToStudent($fee, $student)) {
                    $count++;
                }
            }

            $this->entityManager->flush();

            if ($count > 0) {
                $this->addFlash('success', sprintf('%d frais ajouté(s) à l\'élève.', $count));
            } else {
                $this->addFlash('warning', 'Aucun nouveau frais ajouté (déjà affectés ou sélection vide).');
            }
        }

        return $this->redirectToRoute('admin_student_show', ['id' => $student->getId()]);
    }

    #[Route('/{id}/remove-fee/{studentFeeId}', name: 'remove_fee', methods: ['POST'])]
    public function removeFee(
        Request $request,
        Student $student,
        int $studentFeeId,
        StudentFeeRepository $studentFeeRepository,
        FeeAssignmentService $feeAssignmentService
    ): Response {
        $studentFee = $studentFeeRepository->find($studentFeeId);

        if ($studentFee && $studentFee->getStudent()->getId() === $student->getId()
            && $this->isCsrfTokenValid('remove_fee' . $studentFeeId, $request->request->get('_token'))) {
            try {
                $feeAssignmentService->unassignFeeFromStudent($studentFee);
                $this->entityManager->flush();
                $this->addFlash('success', 'Frais retiré de l\'élève.');
            } catch (\LogicException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_student_show', ['id' => $student->getId()]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Student $student): Response
    {
        $form = $this->createForm(StudentType::class, $student);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Élève modifié avec succès.');
            return $this->redirectToRoute('admin_student_show', ['id' => $student->getId()]);
        }

        return $this->render('student/edit.html.twig', [
            'student' => $student,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Student $student): Response
    {
        if ($this->isCsrfTokenValid('delete' . $student->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($student);
            $this->entityManager->flush();

            $this->addFlash('success', 'Élève supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_student_index');
    }

    private function generateStudentNumber(): string
    {
        $year = date('Y');
        $count = $this->entityManager->getRepository(Student::class)
            ->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.studentNumber LIKE :pattern')
            ->setParameter('pattern', $year . '%')
            ->getQuery()
            ->getSingleScalarResult();

        return $year . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }

    private function createStudentFromPreRegistration(PreRegistration $preRegistration, ?int $classId, ?\App\Entity\SchoolYear $currentSchoolYear): Student
    {
        $student = new Student();
        $student->setFirstName($preRegistration->getFirstName());
        $student->setLastName($preRegistration->getLastName());
        $student->setDateOfBirth($preRegistration->getDateOfBirth());
        $student->setGender($preRegistration->getGender());
        $student->setPhone($preRegistration->getPhone());
        $student->setEmail($preRegistration->getEmail());
        $student->setAddress($preRegistration->getAddress());
        $student->setParentName($preRegistration->getParentName());
        $student->setParentPhone($preRegistration->getParentPhone());
        $student->setParentEmail($preRegistration->getParentEmail());
        $student->setEmergencyContact($preRegistration->getEmergencyContact());
        $student->setEmergencyPhone($preRegistration->getEmergencyPhone());
        $student->setMedicalInfo($preRegistration->getMedicalInfo());
        $student->setNotes($preRegistration->getNotes());
        $student->setSchool($preRegistration->getSchool());
        $student->setSchoolYear($preRegistration->getSchoolYear() ?? $currentSchoolYear);
        
        // Assigner la classe si sélectionnée
        if ($classId) {
            $classroom = $this->entityManager->getRepository(\App\Entity\Classroom::class)->find($classId);
            $student->setClassroom($classroom);
            // Le niveau doit correspondre à la classe choisie (sinon les frais du niveau ne s'affectent pas correctement)
            if ($classroom && $classroom->getLevel()) {
                $student->setLevel($classroom->getLevel());
            }
        }

        // Si aucune classe (ou classe sans niveau), utiliser le niveau de la préinscription
        if (!$student->getLevel()) {
            $student->setLevel($preRegistration->getRequestedLevel());
        }
        
        $student->setStatus('affecte');
        $student->setStudentNumber($this->generateStudentNumber());
        // La relation est maintenant gérée par PreRegistration

        return $student;
    }
}
