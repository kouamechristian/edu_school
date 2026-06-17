<?php

namespace App\Controller;

use App\Controller\Concern\HandlesEntityDeletion;
use App\Controller\Concern\HandlesFileUpload;
use App\Entity\Classroom;
use App\Entity\Student;
use App\Entity\StudentTransfer;
use App\Entity\PreRegistration;
use App\Repository\StudentTransferRepository;
use App\Form\StudentType;
use App\Repository\FeeRepository;
use App\Repository\StudentRepository;
use App\Repository\StudentFeeRepository;
use App\Repository\PaymentRepository;
use App\Repository\PreRegistrationRepository;
use App\Repository\ClassroomRepository;
use App\Repository\LevelRepository;
use App\Service\FeeAssignmentService;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/students', name: 'admin_student_')]
#[IsGranted('ROLE_INSCRIPTION')]
class StudentController extends AbstractController
{
    use HandlesEntityDeletion;
    use HandlesFileUpload;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SchoolContextService $schoolContextService,
        private FeeAssignmentService $feeAssignmentService,
        private \App\Service\MatriculeGenerator $matriculeGenerator
    ) {}

    #[Route('/transfer', name: 'transfer', methods: ['GET', 'POST'])]
    public function transfer(
        Request $request,
        ClassroomRepository $classroomRepository,
        StudentRepository $studentRepository,
        StudentTransferRepository $transferRepository,
        SluggerInterface $slugger
    ): Response {
        $school = $this->schoolContextService->getCurrentSchool();
        $schoolYear = $this->schoolContextService->getCurrentSchoolYear();

        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour transférer des élèves.');
            return $this->redirectToRoute('admin_student_index');
        }

        $classrooms = $classroomRepository->findBySchoolAndYear($school->getId(), $schoolYear?->getId());

        // Élèves actifs de l'établissement (pour le sélecteur).
        $students = $studentRepository->createQueryBuilder('s')
            ->leftJoin('s.classroom', 'c')->addSelect('c')
            ->leftJoin('s.level', 'l')->addSelect('l')
            ->leftJoin('s.school', 'school')
            ->where('school.id = :schoolId')
            ->andWhere('s.isActive = :active')
            ->setParameter('schoolId', $school->getId())
            ->setParameter('active', true)
            ->orderBy('s.lastName', 'ASC')
            ->addOrderBy('s.firstName', 'ASC')
            ->getQuery()
            ->getResult();

        if ($request->isMethod('POST')) {
            $studentId = $request->request->get('student_id');
            $toClassId = $request->request->get('to_class_id');
            $motif = trim((string) $request->request->get('motif'));
            /** @var UploadedFile|null $documentFile */
            $documentFile = $request->files->get('document');

            $student = $studentId ? $studentRepository->find($studentId) : null;
            $toClassroom = $toClassId ? $classroomRepository->find($toClassId) : null;

            if (!$this->isCsrfTokenValid('transfer_student', $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de sécurité invalide.');
            } elseif (!$student || $student->getSchool()?->getId() !== $school->getId()) {
                $this->addFlash('error', 'Veuillez sélectionner un élève valide.');
            } elseif (!$toClassroom || $toClassroom->getSchool()?->getId() !== $school->getId()) {
                $this->addFlash('error', 'Veuillez sélectionner une classe de destination valide.');
            } elseif ($student->getClassroom() && $student->getClassroom()->getId() === $toClassroom->getId()) {
                $this->addFlash('error', 'La classe de destination doit être différente de la classe actuelle de l\'élève.');
            } elseif (
                ($currentLevel = $student->getClassroom()?->getLevel() ?? $student->getLevel())
                && $toClassroom->getLevel()?->getId() !== $currentLevel->getId()
            ) {
                $this->addFlash('error', 'La classe de destination doit être du même niveau que la classe actuelle de l\'élève.');
            } elseif ($motif === '') {
                $this->addFlash('error', 'Le motif du transfert est obligatoire.');
            } else {
                $transfer = new StudentTransfer();
                $transfer->setStudent($student)
                    ->setFromClassroom($student->getClassroom())
                    ->setToClassroom($toClassroom)
                    ->setSchoolYear($schoolYear)
                    ->setMotif($motif)
                    ->setRecordedBy($this->getUser());

                if ($documentFile instanceof UploadedFile) {
                    $transfer->setDocumentPath($this->uploadFile($documentFile, 'transfers', $slugger));
                }

                $this->entityManager->persist($transfer);

                // Application du transfert sur l'élève.
                $student->setClassroom($toClassroom);
                $student->setLevel($toClassroom->getLevel());
                $student->setStatus('affecte');

                $this->entityManager->flush();

                $this->addFlash('success', sprintf(
                    'L\'élève %s a été transféré vers la classe « %s » avec succès.',
                    $student->getFullName(),
                    $toClassroom->getName()
                ));
                return $this->redirectToRoute('admin_student_transfer');
            }
        }

        return $this->render('student/transfer.html.twig', [
            'classrooms' => $classrooms,
            'students' => $students,
            'transfers' => $transferRepository->findBySchool($school->getId()),
            'current_school_year' => $schoolYear,
        ]);
    }

    #[Route('/archive', name: 'archive', methods: ['GET'])]
    public function archive(StudentRepository $studentRepository): Response
    {
        $school = $this->schoolContextService->getCurrentSchool();
        
        $archivedStudents = $studentRepository->createQueryBuilder('s')
            ->leftJoin('s.school', 'school')
            ->where('school.id = :schoolId')
            ->andWhere('s.isActive = :isActive')
            ->setParameter('schoolId', $school->getId())
            ->setParameter('isActive', false)
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
        StudentRepository $studentRepository,
        StudentFeeRepository $studentFeeRepository,
        PaymentRepository $paymentRepository
    ): Response {
        $school = $this->schoolContextService->getCurrentSchool();
        $schoolYear = $this->schoolContextService->getCurrentSchoolYear();
        
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour voir les inscriptions.');
            return $this->redirectToRoute('admin_student_index');
        }
        
        // Récupérer les préinscriptions validées prêtes pour l'inscription.
        // L'inscription est liée à l'année scolaire : on ne propose que les
        // préinscriptions validées de l'année courante.
        $preRegistrations = $preRegistrationRepository->findReadyForEnrollment($school->getId(), $schoolYear?->getId());
        
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

        // Données financières par élève inscrit (payé, annulé, restant).
        $enrollmentFinancials = [];
        foreach ($enrollmentsThisYear as $s) {
            $total = $studentFeeRepository->getTotalByStudent($s->getId());
            $paid = $studentFeeRepository->getTotalPaidByStudent($s->getId());
            $stats = $paymentRepository->getPaymentStatsByStudent($s);
            $remaining = max(0, $total - $paid);
            $enrollmentFinancials[$s->getId()] = [
                'total' => $total,
                'paid' => $paid,
                'cancelled' => (float) ($stats['cancelled_amount'] ?? 0),
                'remaining' => $remaining,
            ];
        }

        // Classes groupées par niveau (pour proposer les classes adaptées à chaque élève).
        $classroomsByLevel = [];
        foreach ($classrooms as $classroom) {
            $levelId = $classroom->getLevel()?->getId();
            if ($levelId) {
                $classroomsByLevel[$levelId][] = ['id' => $classroom->getId(), 'name' => $classroom->getName()];
            }
        }
        $allClassrooms = array_map(fn ($c) => ['id' => $c->getId(), 'name' => $c->getName()], $classrooms);

        // Inscription d'UN seul élève à la fois.
        if ($request->isMethod('POST')) {
            $preRegistrationId = $request->request->get('pre_registration_id');
            $classId = $request->request->get('class_id');

            if (!$this->isCsrfTokenValid('enroll' . $preRegistrationId, $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de sécurité invalide.');
                return $this->redirectToRoute('admin_student_enrollment');
            }

            $preRegistration = $preRegistrationId ? $preRegistrationRepository->find($preRegistrationId) : null;

            if (!$preRegistration
                || $preRegistration->getStatus() !== 'validated'
                || $preRegistration->getSchool()?->getId() !== $school->getId()) {
                $this->addFlash('error', 'Préinscription introuvable ou non valide pour l\'inscription.');
                return $this->redirectToRoute('admin_student_enrollment');
            }

            if (!$classId) {
                $this->addFlash('error', 'Veuillez sélectionner une classe pour inscrire l\'élève.');
                return $this->redirectToRoute('admin_student_enrollment');
            }

            $student = $this->createStudentFromPreRegistration($preRegistration, $classId, $schoolYear);
            $this->entityManager->persist($student);

            $preRegistration->setStatus('enrolled');
            $preRegistration->setEnrolledAt(new \DateTime());
            $student->setPreRegistration($preRegistration);
            $preRegistration->setStudent($student);

            $this->entityManager->flush();

            $feeCount = $this->feeAssignmentService->assignScolariteFeesForStudent($student);
            if ($feeCount > 0) {
                $this->entityManager->flush();
            }

            $message = sprintf('L\'élève %s a été inscrit avec succès.', $student->getFullName());
            if ($feeCount > 0) {
                $message .= sprintf(' %d frais automatiquement affecté(s).', $feeCount);
            }
            $this->addFlash('success', $message);

            return $this->redirectToRoute('admin_student_enrollment');
        }

        // Classes proposées par préinscription (selon le niveau demandé, sinon toutes).
        $enrollChoices = [];
        foreach ($preRegistrations as $preReg) {
            $levelId = $preReg->getRequestedLevel()?->getId();
            $enrollChoices[$preReg->getId()] = ($levelId && !empty($classroomsByLevel[$levelId]))
                ? $classroomsByLevel[$levelId]
                : $allClassrooms;
        }

        return $this->render('student/enrollment.html.twig', [
            'preRegistrations' => $preRegistrations,
            'classrooms' => $classrooms,
            'levels' => $levels,
            'enrollChoices' => $enrollChoices,
            'enrollments_this_year' => $enrollmentsThisYear,
            'enrollment_financials' => $enrollmentFinancials,
            'current_school_year' => $schoolYear,
        ]);
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(StudentRepository $studentRepository, StudentFeeRepository $studentFeeRepository, PreRegistrationRepository $preRegistrationRepository, Request $request): Response
    {
        $school = $this->schoolContextService->getCurrentSchool();
        $schoolYear = $this->schoolContextService->getCurrentSchoolYear();
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $level = $request->query->get('level', '');
        $gender = $request->query->get('gender', '');

        $queryBuilder = $studentRepository->createQueryBuilder('s')
            ->leftJoin('s.school', 'school')
            ->leftJoin('s.level', 'l')
            ->leftJoin('s.preRegistration', 'pr')
            ->where('school.id = :schoolId')
            ->andWhere('s.isActive = :isActive')
            ->setParameter('schoolId', $school->getId())
            ->setParameter('isActive', true)
            ->orderBy('s.lastName', 'ASC');

        // Aligne la liste sur l'année scolaire en cours (cohérence avec les
        // statistiques du tableau de bord). Sans année sélectionnée, on n'applique
        // pas de filtre pour rester rétro-compatible.
        if ($schoolYear) {
            $queryBuilder->andWhere('s.schoolYear = :schoolYearId')
                ->setParameter('schoolYearId', $schoolYear->getId());
        }

        if ($search) {
            $queryBuilder->andWhere('s.firstName LIKE :search OR s.lastName LIKE :search OR s.matriculeInterne LIKE :search OR s.matriculeNational LIKE :search')
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

        if ($gender) {
            $queryBuilder->andWhere('s.gender = :gender')
                ->setParameter('gender', $gender);
        }

        $students = $queryBuilder->getQuery()->getResult();

        // Nombre d'inscriptions et de préinscriptions par élève depuis sa venue dans
        // l'établissement. L'identité d'un même élève au fil des années repose sur le
        // matricule national (stable) ; à défaut, on s'en tient à l'enregistrement courant.
        $nationals = [];
        foreach ($students as $student) {
            $mn = trim((string) $student->getMatriculeNational());
            if ($mn !== '') {
                $nationals[$mn] = true;
            }
        }
        $nationals = array_keys($nationals);

        $enrollmentByNational = $studentRepository->countBySchoolGroupedByNational($school->getId(), $nationals);
        $preRegByNational = $preRegistrationRepository->countBySchoolGroupedByNational($school->getId(), $nationals);

        $tuitionData = [];
        $enrollmentCounts = [];
        $preRegistrationCounts = [];
        foreach ($students as $student) {
            $totalAmount = $studentFeeRepository->getTotalByStudent($student->getId());
            $totalPaid = $studentFeeRepository->getTotalPaidByStudent($student->getId());
            $tuitionData[$student->getId()] = [
                'total' => $totalAmount,
                'paid' => $totalPaid,
                'remaining' => max(0, $totalAmount - $totalPaid),
            ];

            $mn = trim((string) $student->getMatriculeNational());
            if ($mn !== '') {
                $enrollmentCounts[$student->getId()] = $enrollmentByNational[$mn] ?? 1;
                $preRegistrationCounts[$student->getId()] = $preRegByNational[$mn] ?? 0;
            } else {
                // Sans matricule national, impossible de regrouper : on compte l'enregistrement courant.
                $enrollmentCounts[$student->getId()] = 1;
                $preReg = $student->getPreRegistration();
                $preRegistrationCounts[$student->getId()] =
                    ($preReg && in_array($preReg->getStatus(), ['validated', 'enrolled'], true)) ? 1 : 0;
            }
        }

        $stats = [
            'total' => count($students),
            'affecte' => count(array_filter($students, fn($s) => $s->getStatus() === 'affecte')),
            'non_affecte' => count(array_filter($students, fn($s) => $s->getStatus() === 'non_affecte')),
        ];

        return $this->render('student/index.html.twig', [
            'students' => $students,
            'tuition_data' => $tuitionData,
            'enrollment_counts' => $enrollmentCounts,
            'preregistration_counts' => $preRegistrationCounts,
            'stats' => $stats,
            'search' => $search,
            'status' => $status,
            'level' => $level,
            'gender' => $gender,
        ]);
    }

    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function dashboard(ClassroomRepository $classroomRepository, StudentRepository $studentRepository): Response
    {
        $school = $this->schoolContextService->getCurrentSchool();
        $schoolYear = $this->schoolContextService->getCurrentSchoolYear();

        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour voir le tableau de bord.');
            return $this->redirectToRoute('admin_student_index');
        }

        $classrooms = $classroomRepository->findBySchoolAndYear($school->getId(), $schoolYear?->getId());

        // Nombre d'élèves inscrits (actifs) par classe.
        $rows = $studentRepository->createQueryBuilder('s')
            ->select('IDENTITY(s.classroom) AS cid, COUNT(s.id) AS cnt')
            ->leftJoin('s.school', 'sc')
            ->where('sc.id = :schoolId')
            ->andWhere('s.isActive = :active')
            ->andWhere('s.classroom IS NOT NULL')
            ->setParameter('schoolId', $school->getId())
            ->setParameter('active', true)
            ->groupBy('s.classroom')
            ->getQuery()
            ->getScalarResult();
        $countByClassroom = [];
        foreach ($rows as $r) {
            $countByClassroom[(int) $r['cid']] = (int) $r['cnt'];
        }

        // Statistiques.
        $totalStudents = (int) $studentRepository->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->leftJoin('s.school', 'sc')
            ->where('sc.id = :schoolId')
            ->andWhere('s.isActive = :active')
            ->setParameter('schoolId', $school->getId())
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        $withoutBirthCertificate = (int) $studentRepository->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->leftJoin('s.school', 'sc')
            ->where('sc.id = :schoolId')
            ->andWhere('s.isActive = :active')
            ->andWhere('s.birthCertificateNumber IS NULL OR s.birthCertificateNumber = :empty')
            ->setParameter('schoolId', $school->getId())
            ->setParameter('active', true)
            ->setParameter('empty', '')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('student/dashboard.html.twig', [
            'current_school' => $school,
            'current_school_year' => $schoolYear,
            'classrooms' => $classrooms,
            'count_by_classroom' => $countByClassroom,
            'stats' => [
                'classes' => count($classrooms),
                'students' => $totalStudents,
                'without_birth_certificate' => $withoutBirthCertificate,
            ],
        ]);
    }

    #[Route('/dashboard/classe/{id}', name: 'classroom_students', methods: ['GET'])]
    public function classroomStudents(Classroom $classroom, StudentRepository $studentRepository): Response
    {
        return $this->render('student/classroom_students.html.twig', [
            'classroom' => $classroom,
            'students' => $studentRepository->findActiveByClassroom($classroom->getId()),
        ]);
    }

    /**
     * Génère la liste des élèves d'une classe au format PDF (modèle officiel).
     */
    #[Route('/dashboard/classe/{id}/pdf', name: 'classroom_students_pdf', methods: ['GET'])]
    public function classroomStudentsPdf(Classroom $classroom, StudentRepository $studentRepository): Response
    {
        $students = $studentRepository->findActiveByClassroom($classroom->getId());
        $school = $classroom->getSchool();

        // Logo embarqué en base64 (Dompdf lit ainsi l'image sans accès disque/URL).
        $logoData = null;
        if ($school && $school->getLogo()) {
            $logoPath = $this->getParameter('kernel.project_dir') . '/public/' . ltrim($school->getLogo(), '/');
            if (is_file($logoPath)) {
                $mime = mime_content_type($logoPath) ?: 'image/png';
                $logoData = 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($logoPath));
            }
        }

        $html = $this->renderView('student/classroom_students_pdf.html.twig', [
            'classroom' => $classroom,
            'students' => $students,
            'school' => $school,
            'logo_data' => $logoData,
            'generated_at' => new \DateTime(),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = sprintf('LISTE_CLASSE_%d.pdf', $classroom->getId());

        return new Response($dompdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
        ]);
    }

    /**
     * Génère la carte d'accès d'un élève (PDF avec QR code), consultable et téléchargeable.
     */
    #[Route('/{id}/carte', name: 'card', methods: ['GET'])]
    public function accessCard(Student $student): Response
    {
        $school = $student->getSchool();

        // Logo, photo et cachet embarqués en base64 (Dompdf lit ainsi l'image sans accès disque/URL).
        $logoData = $this->imageDataUri($school?->getLogo());
        $photoData = $this->imageDataUri($student->getPhoto());
        $cachetData = $this->imageDataUri($school?->getCachetDirection());

        // QR code : identifiant d'accès de l'élève (lisible par un lecteur au point de contrôle).
        $payload = sprintf(
            "EDU-SCHOOL CARTE D'ACCES\nMatricule: %s\nMat. National: %s\nNom: %s\nClasse: %s\nEtablissement: %s",
            $student->getMatriculeInterne() ?: '-',
            $student->getMatriculeNational() ?: '-',
            $student->getFullName(),
            $student->getClassroom()?->getName() ?: '-',
            $school?->getName() ?: '-'
        );
        $qrResult = (new PngWriter())->write(QrCode::create($payload)->setSize(220)->setMargin(4));

        $html = $this->renderView('student/card_pdf.html.twig', [
            'student' => $student,
            'school' => $school,
            'logo_data' => $logoData,
            'photo_data' => $photoData,
            'cachet_data' => $cachetData,
            'qr_data' => $qrResult->getDataUri(),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        // Dimensions du modèle officiel : 156 × 105 points (≈ 55 × 37 mm).
        $dompdf->setPaper([0, 0, 156, 105]);
        $dompdf->render();

        $filename = sprintf('CARTE_ACCES_%s.pdf', $student->getMatriculeInterne() ?: $student->getId());

        return new Response($dompdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
        ]);
    }

    /**
     * Construit une data-URI base64 à partir d'un chemin d'image relatif à /public.
     */
    private function imageDataUri(?string $relativePath): ?string
    {
        if (!$relativePath) {
            return null;
        }

        $path = $this->getParameter('kernel.project_dir') . '/public/' . ltrim($relativePath, '/');
        if (!is_file($path)) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
    }

    /**
     * En-têtes de colonnes utilisés pour l'export et l'import Excel.
     */
    private const EXCEL_HEADERS = [
        'Matricule interne', 'Matricule national', 'Nom', 'Prénom', 'Genre (M/F)', 'Date de naissance (JJ/MM/AAAA)',
        'Lieu de naissance', 'Nationalité', 'Numéro extrait de naissance', 'Numéro CMU',
        'Téléphone', 'Email', 'Adresse', 'Dernière école fréquentée', 'Doublant (Oui/Non)',
        'Niveau', 'Classe', 'Statut',
        'Nom parent', 'Téléphone parent', 'Email parent', 'Fonction parent', 'Domicile parent', 'Photo',
    ];

    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(Request $request, StudentRepository $studentRepository): Response
    {
        $school = $this->schoolContextService->getCurrentSchool();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour exporter les élèves.');
            return $this->redirectToRoute('admin_student_index');
        }

        // Mêmes filtres que la liste, pour exporter ce que l'utilisateur voit.
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $level = $request->query->get('level', '');

        $qb = $studentRepository->createQueryBuilder('s')
            ->leftJoin('s.school', 'school')
            ->leftJoin('s.level', 'l')
            ->where('school.id = :schoolId')
            ->setParameter('schoolId', $school->getId())
            ->orderBy('s.lastName', 'ASC');

        if ($search) {
            $qb->andWhere('s.firstName LIKE :search OR s.lastName LIKE :search OR s.matriculeInterne LIKE :search OR s.matriculeNational LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }
        if ($status) {
            $qb->andWhere('s.status = :status')->setParameter('status', $status);
        }
        if ($level) {
            $qb->andWhere('l.id = :levelId')->setParameter('levelId', $level);
        }

        $students = $qb->getQuery()->getResult();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Élèves');
        $sheet->fromArray(self::EXCEL_HEADERS, null, 'A1');
        $sheet->getStyle('A1:X1')->getFont()->setBold(true);

        $rowNum = 2;
        foreach ($students as $s) {
            $sheet->fromArray([
                $s->getMatriculeInterne(),
                $s->getMatriculeNational(),
                $s->getLastName(),
                $s->getFirstName(),
                $s->getGender(),
                $s->getDateOfBirth()?->format('d/m/Y'),
                $s->getPlaceOfBirth(),
                $s->getNationality(),
                $s->getBirthCertificateNumber(),
                $s->getCmuNumber(),
                $s->getPhone(),
                $s->getEmail(),
                $s->getAddress(),
                $s->getLastSchoolAttended(),
                $s->isRepeating() ? 'Oui' : 'Non',
                $s->getLevel()?->getName(),
                $s->getClassroom()?->getName(),
                $s->getStatusLabel(),
                $s->getParentName(),
                $s->getParentPhone(),
                $s->getParentEmail(),
                $s->getParentFunction(),
                $s->getParentAddress(),
                $s->getPhoto(),
            ], null, 'A' . $rowNum);
            $rowNum++;
        }

        foreach (range('A', 'X') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'eleves_' . ($school->getCode() ?: 'export') . '_' . date('Ymd_His') . '.xlsx';

        return $this->streamSpreadsheet($spreadsheet, $filename);
    }

    #[Route('/import/template', name: 'import_template', methods: ['GET'])]
    public function importTemplate(): Response
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Modèle élèves');
        $sheet->fromArray(self::EXCEL_HEADERS, null, 'A1');
        $sheet->getStyle('A1:X1')->getFont()->setBold(true);

        // Ligne d'exemple (laisser le matricule interne vide pour génération automatique)
        $sheet->fromArray([
            '', '123456789', 'DUPONT', 'Jean', 'M', '15/09/2012',
            'Abidjan', 'Ivoirienne', 'EN-2012-001', 'CMU123456',
            '0612345678', 'jean.dupont@example.com', '15 rue de la Paix', 'École Les Oranges', 'Non',
            '6ème', '', '',
            'DUPONT Pierre', '0698765432', 'pierre.dupont@example.com', 'Commerçant', 'Cocody, Abidjan', '',
        ], null, 'A2');

        foreach (range('A', 'X') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->streamSpreadsheet($spreadsheet, 'modele_import_eleves.xlsx');
    }

    #[Route('/import', name: 'import', methods: ['POST'])]
    public function import(Request $request, LevelRepository $levelRepository): Response
    {
        $school = $this->schoolContextService->getCurrentSchool();
        $schoolYear = $this->schoolContextService->getCurrentSchoolYear();

        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour importer des élèves.');
            return $this->redirectToRoute('admin_student_index');
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            $this->addFlash('error', 'Veuillez sélectionner un fichier à importer.');
            return $this->redirectToRoute('admin_student_index');
        }

        if (!in_array(strtolower($file->getClientOriginalExtension()), ['xlsx', 'xls', 'csv'], true)) {
            $this->addFlash('error', 'Format de fichier invalide. Formats acceptés : XLSX, XLS, CSV.');
            return $this->redirectToRoute('admin_student_index');
        }

        try {
            $spreadsheet = IOFactory::load($file->getPathname());
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Impossible de lire le fichier : ' . $e->getMessage());
            return $this->redirectToRoute('admin_student_index');
        }

        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);

        // Index des niveaux de l'établissement par nom (pour la correspondance).
        $levelByName = [];
        foreach ($levelRepository->findBy(['school' => $school]) as $lvl) {
            $levelByName[mb_strtolower(trim($lvl->getName()))] = $lvl;
        }

        $created = 0;
        $skipped = 0;

        foreach ($rows as $i => $row) {
            if ($i === 0) {
                continue; // En-tête
            }

            $lastName = trim((string) ($row[2] ?? ''));
            $firstName = trim((string) ($row[3] ?? ''));

            // Ligne entièrement vide : on ignore silencieusement.
            if ($lastName === '' && $firstName === '' && trim((string) ($row[0] ?? '')) === '') {
                continue;
            }

            // Nom et prénom obligatoires.
            if ($lastName === '' || $firstName === '') {
                $skipped++;
                continue;
            }

            $student = new Student();
            $student->setLastName($lastName);
            $student->setFirstName($firstName);

            $matricule = trim((string) ($row[0] ?? ''));
            if ($matricule === '') {
                $matricule = $this->matriculeGenerator->generate($this->entityManager, Student::class);
            }
            $student->setMatriculeInterne($matricule);
            $student->setMatriculeNational($this->cleanCell($row[1] ?? null));

            $gender = strtoupper(trim((string) ($row[4] ?? '')));
            if (in_array($gender, ['M', 'F'], true)) {
                $student->setGender($gender);
            }

            if ($dob = $this->parseImportDate($row[5] ?? null)) {
                $student->setDateOfBirth($dob);
            }

            $student->setPlaceOfBirth($this->cleanCell($row[6] ?? null));
            $student->setNationality($this->cleanCell($row[7] ?? null));
            $student->setBirthCertificateNumber($this->cleanCell($row[8] ?? null));
            $student->setCmuNumber($this->cleanCell($row[9] ?? null));
            $student->setPhone($this->cleanCell($row[10] ?? null));
            $student->setEmail($this->cleanCell($row[11] ?? null));
            $student->setAddress($this->cleanCell($row[12] ?? null));
            $student->setLastSchoolAttended($this->cleanCell($row[13] ?? null));
            $student->setIsRepeating($this->parseBoolean($row[14] ?? null));

            $levelName = mb_strtolower(trim((string) ($row[15] ?? '')));
            if ($levelName !== '' && isset($levelByName[$levelName])) {
                $student->setLevel($levelByName[$levelName]);
            }

            $student->setParentName($this->cleanCell($row[18] ?? null));
            $student->setParentPhone($this->cleanCell($row[19] ?? null));
            $student->setParentEmail($this->cleanCell($row[20] ?? null));
            $student->setParentFunction($this->cleanCell($row[21] ?? null));
            $student->setParentAddress($this->cleanCell($row[22] ?? null));

            $student->setSchool($school);
            if ($schoolYear) {
                $student->setSchoolYear($schoolYear);
            }
            $student->setStatus('non_affecte');

            $this->entityManager->persist($student);
            $created++;
        }

        if ($created > 0) {
            $this->entityManager->flush();
        }

        if ($created > 0) {
            $message = "{$created} élève(s) importé(s) avec succès.";
            if ($skipped > 0) {
                $message .= " {$skipped} ligne(s) ignorée(s) (nom ou prénom manquant).";
            }
            $this->addFlash('success', $message);
        } else {
            $this->addFlash('warning', 'Aucun élève importé.' . ($skipped > 0 ? " {$skipped} ligne(s) ignorée(s)." : ''));
        }

        return $this->redirectToRoute('admin_student_index');
    }

    private function streamSpreadsheet(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        });
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    private function cleanCell(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function parseBoolean(mixed $value): bool
    {
        $value = mb_strtolower(trim((string) ($value ?? '')));

        return in_array($value, ['oui', 'yes', '1', 'true', 'vrai', 'x'], true);
    }

    private function parseImportDate(mixed $value): ?\DateTimeInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Valeur numérique : date sérielle Excel.
        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value);
            } catch (\Throwable) {
                return null;
            }
        }

        $value = trim((string) $value);
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y'] as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date instanceof \DateTime) {
                return $date;
            }
        }

        return null;
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(
        Student $student,
        FeeRepository $feeRepository,
        PaymentRepository $paymentRepository,
        StudentTransferRepository $transferRepository
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

        return $this->render('student/show.html.twig', [
            'student' => $student,
            'available_fees' => $availableFees,
            'payments' => $paymentRepository->findByStudent($student),
            'transfers' => $transferRepository->findByStudent($student->getId()),
        ]);
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
    public function edit(Request $request, Student $student, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(StudentType::class, $student);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($photo = $this->uploadFile($form->get('photoFile')->getData(), 'students', $slugger)) {
                $student->setPhoto($photo);
            }

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
            $this->deleteEntity(
                $this->entityManager,
                $student,
                'Élève supprimé avec succès.',
                'Suppression impossible : cet élève possède encore des données associées (paiements, inscriptions, notes...). Veuillez d\'abord les supprimer.'
            );
        }

        return $this->redirectToRoute('admin_student_index');
    }

    /**
     * Archive un élève (désactivation logique) : il quitte la liste active et
     * rejoint les Archives, sans perte de ses données. Préféré à la suppression.
     */
    #[Route('/{id}/archive', name: 'do_archive', methods: ['POST'])]
    public function archiveStudent(Request $request, Student $student): Response
    {
        if ($this->isCsrfTokenValid('archive' . $student->getId(), $request->request->get('_token'))) {
            $student->setIsActive(false);
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('L\'élève %s a été archivé.', $student->getFullName()));
        }

        return $this->redirectToRoute('admin_student_index');
    }

    /**
     * Restaure un élève archivé : il redevient actif et réapparaît dans la liste.
     */
    #[Route('/{id}/restore', name: 'restore', methods: ['POST'])]
    public function restoreStudent(Request $request, Student $student): Response
    {
        if ($this->isCsrfTokenValid('restore' . $student->getId(), $request->request->get('_token'))) {
            $student->setIsActive(true);
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('L\'élève %s a été restauré.', $student->getFullName()));
        }

        return $this->redirectToRoute('admin_student_archive');
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
        // Champs civils/scolaires supplémentaires reportés depuis la préinscription
        $student->setPlaceOfBirth($preRegistration->getPlaceOfBirth());
        $student->setNationality($preRegistration->getNationality());
        $student->setBirthCertificateNumber($preRegistration->getBirthCertificateNumber());
        $student->setCmuNumber($preRegistration->getCmuNumber());
        $student->setLastSchoolAttended($preRegistration->getLastSchoolAttended());
        $student->setIsRepeating($preRegistration->isRepeating());
        $student->setPhoto($preRegistration->getPhoto());
        $student->setParentFunction($preRegistration->getParentFunction());
        $student->setParentAddress($preRegistration->getParentAddress());
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
        $student->setMatriculeInterne($this->matriculeGenerator->generate($this->entityManager, Student::class));
        $student->setMatriculeNational($preRegistration->getMatriculeNational());
        // La relation est maintenant gérée par PreRegistration

        return $student;
    }
}
