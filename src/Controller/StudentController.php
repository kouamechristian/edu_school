<?php

namespace App\Controller;

use App\Controller\Concern\HandlesEntityDeletion;
use App\Controller\Concern\HandlesFileUpload;
use App\Entity\Classroom;
use App\Entity\Registration;
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
use App\Service\EnrollmentService;
use App\Service\FeeAssignmentService;
use App\Service\RegistrationManager;
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
        private \App\Service\MatriculeGenerator $matriculeGenerator,
        private RegistrationManager $registrationManager,
        private EnrollmentService $enrollmentService
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

        // Élèves actifs de l'établissement (pour le sélecteur). La classe/niveau
        // sont portés par l'inscription (chargés à la demande via les getters).
        $students = $studentRepository->createQueryBuilder('s')
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

                // Application du transfert sur l'inscription de l'année (+ legacy).
                $existing = $schoolYear ? $student->getRegistrationForYear($schoolYear) : null;
                $this->registrationManager->syncRegistration(
                    $student,
                    $schoolYear,
                    $toClassroom,
                    $toClassroom->getLevel(),
                    $student->isRepeating(),
                    $existing?->isBoursier() ?? false
                );

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

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(StudentRepository $studentRepository, StudentFeeRepository $studentFeeRepository, PreRegistrationRepository $preRegistrationRepository, Request $request, \Knp\Component\Pager\PaginatorInterface $paginator): Response
    {
        $school = $this->schoolContextService->getCurrentSchool();
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $level = $request->query->get('level', '');
        $gender = $request->query->get('gender', '');

        // La liste élèves est le référentiel de l'établissement (toutes années) :
        // on n'exige pas d'inscription pour l'année courante (les élèves importés
        // mais pas encore inscrits doivent rester visibles).
        $queryBuilder = $studentRepository->createQueryBuilder('s')
            ->leftJoin('s.school', 'school')
            // Pré-chargement de l'inscription issue de la préinscription d'origine
            // (classe / niveau / année) pour l'affichage, sans requêtes N+1. Le parent
            // « pr » doit être sélectionné pour que « reg » puisse y être rattaché.
            ->leftJoin('s.preRegistration', 'pr')->addSelect('pr')
            ->leftJoin('pr.registration', 'reg')->addSelect('reg')
            ->leftJoin('reg.classroom', 'regc')->addSelect('regc')
            ->leftJoin('regc.level', 'regl')->addSelect('regl')
            ->leftJoin('reg.schoolYear', 'regy')->addSelect('regy')
            ->where('school.id = :schoolId')
            ->andWhere('s.isActive = :isActive')
            ->setParameter('schoolId', $school->getId())
            ->setParameter('isActive', true)
            ->orderBy('s.lastName', 'ASC');

        // Statut : attribut de l'élève (référentiel).
        if ($status) {
            $queryBuilder->andWhere('s.status = :status')->setParameter('status', $status);
        }

        // Niveau : porté par l'inscription → jointure registration (seulement si filtré).
        if ($level) {
            $queryBuilder->innerJoin(PreRegistration::class, 'r_pre', 'WITH', 's.preRegistration = r_pre OR r_pre.existingStudent = s')
                ->innerJoin(Registration::class, 'r', 'WITH', 'r.preRegistration = r_pre')
                ->leftJoin('r.classroom', 'rc')
                ->distinct()
                ->andWhere('rc.level = :levelId')->setParameter('levelId', $level);
        }

        if ($search) {
            $queryBuilder->andWhere('s.firstName LIKE :search OR s.lastName LIKE :search OR s.matriculeInterne LIKE :search OR s.matriculeNational LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($gender) {
            $queryBuilder->andWhere('s.gender = :gender')
                ->setParameter('gender', $gender);
        }

        // Liste complète (filtrée) pour les statistiques globales, puis pagination 50/page :
        // les données dérivées (frais, etc.) ne sont calculées que pour la page courante.
        $allStudents = $queryBuilder->getQuery()->getResult();
        $students = $paginator->paginate($allStudents, $request->query->getInt('page', 1), 50);

        // Nombre de préinscriptions par élève depuis sa venue dans l'établissement
        // (regroupé par matricule national, stable d'une année à l'autre). Le nombre
        // d'inscriptions est, lui, lu directement via les Registration de l'élève (template).
        $nationals = [];
        foreach ($students as $student) {
            $mn = trim((string) $student->getMatriculeNational());
            if ($mn !== '') {
                $nationals[$mn] = true;
            }
        }
        $nationals = array_keys($nationals);

        $preRegByNational = $preRegistrationRepository->countBySchoolGroupedByNational($school->getId(), $nationals);

        $tuitionData = [];
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
                $preRegistrationCounts[$student->getId()] = $preRegByNational[$mn] ?? 0;
            } else {
                $preReg = $student->getPreRegistration();
                $preRegistrationCounts[$student->getId()] =
                    ($preReg && in_array($preReg->getStatus(), ['validated', 'enrolled'], true)) ? 1 : 0;
            }
        }

        $stats = [
            'total' => count($allStudents),
            'affecte' => count(array_filter($allStudents, fn($s) => $s->getStatus() === 'affecte')),
            'non_affecte' => count(array_filter($allStudents, fn($s) => $s->getStatus() === 'non_affecte')),
        ];

        return $this->render('student/index.html.twig', [
            'students' => $students,
            'tuition_data' => $tuitionData,
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

        // Toutes les statistiques sont calées sur les inscriptions (Registration) de
        // l'année scolaire courante : un « élève inscrit » est un élève possédant une
        // inscription active pour cette école et cette année. On ne s'appuie plus sur
        // Student.isActive seul (qui inclut d'anciens élèves jamais réinscrits cette année).
        $applyEnrolmentScope = function ($qb) use ($school, $schoolYear) {
            $qb->innerJoin(PreRegistration::class, 'i_pre', 'WITH', 's.preRegistration = i_pre OR i_pre.existingStudent = s')
                ->innerJoin(Registration::class, 'i', 'WITH', 'i.preRegistration = i_pre')
                ->andWhere('i.school = :schoolId')
                ->andWhere('i.isActive = :active')
                ->setParameter('schoolId', $school->getId())
                ->setParameter('active', true);
            if ($schoolYear) {
                $qb->andWhere('i.schoolYear = :yearId')
                    ->setParameter('yearId', $schoolYear->getId());
            }
            return $qb;
        };

        // Nombre d'élèves inscrits par classe pour l'année courante.
        $rows = $applyEnrolmentScope($studentRepository->createQueryBuilder('s'))
            ->select('IDENTITY(i.classroom) AS cid, COUNT(DISTINCT s.id) AS cnt')
            ->andWhere('i.classroom IS NOT NULL')
            ->groupBy('i.classroom')
            ->getQuery()
            ->getScalarResult();
        $countByClassroom = [];
        foreach ($rows as $r) {
            $countByClassroom[(int) $r['cid']] = (int) $r['cnt'];
        }

        // Statistiques globales (élèves distincts inscrits cette année).
        $totalStudents = (int) $applyEnrolmentScope($studentRepository->createQueryBuilder('s'))
            ->select('COUNT(DISTINCT s.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $withoutBirthCertificate = (int) $applyEnrolmentScope($studentRepository->createQueryBuilder('s'))
            ->select('COUNT(DISTINCT s.id)')
            ->andWhere('s.birthCertificateNumber IS NULL OR s.birthCertificateNumber = :empty')
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
    /**
     * En-têtes de l'export/import : uniquement les données du référentiel Student.
     * Le niveau, la classe, le redoublant et le boursier relèvent de l'inscription
     * (Registration) et se gèrent via le module d'inscription, pas par l'import.
     */
    private const EXCEL_HEADERS = [
        'Matricule interne', 'Matricule national', 'Nom', 'Prénom', 'Genre (M/F)', 'Date de naissance (JJ/MM/AAAA)',
        'Lieu de naissance', 'Nationalité', 'Numéro extrait de naissance', 'Numéro CMU',
        'Téléphone', 'Email', 'Adresse', 'Dernière école fréquentée', 'Statut (Affecté/Non affecté)',
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
            ->where('school.id = :schoolId')
            ->setParameter('schoolId', $school->getId())
            ->orderBy('s.lastName', 'ASC');

        // Statut = attribut de l'élève ; niveau = porté par l'inscription.
        if ($status) {
            $qb->andWhere('s.status = :status')->setParameter('status', $status);
        }
        if ($level) {
            $qb->innerJoin(PreRegistration::class, 'r_pre', 'WITH', 's.preRegistration = r_pre OR r_pre.existingStudent = s')
                ->innerJoin(Registration::class, 'r', 'WITH', 'r.preRegistration = r_pre')
                ->leftJoin('r.classroom', 'rc')
                ->distinct()
                ->andWhere('rc.level = :levelId')->setParameter('levelId', $level);
        }

        if ($search) {
            $qb->andWhere('s.firstName LIKE :search OR s.lastName LIKE :search OR s.matriculeInterne LIKE :search OR s.matriculeNational LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $students = $qb->getQuery()->getResult();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Élèves');
        $sheet->fromArray(self::EXCEL_HEADERS, null, 'A1');
        $sheet->getStyle('A1:U1')->getFont()->setBold(true);

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

        foreach (range('A', 'U') as $col) {
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
        $sheet->getStyle('A1:U1')->getFont()->setBold(true);

        // Ligne d'exemple (laisser le matricule interne vide pour génération automatique)
        $sheet->fromArray([
            '', '123456789', 'DUPONT', 'Jean', 'M', '15/09/2012',
            'Abidjan', 'Ivoirienne', 'EN-2012-001', 'CMU123456',
            '0612345678', 'jean.dupont@example.com', '15 rue de la Paix', 'École Les Oranges', 'Non affecté',
            'DUPONT Pierre', '0698765432', 'pierre.dupont@example.com', 'Commerçant', 'Cocody, Abidjan', '',
        ], null, 'A2');

        foreach (range('A', 'U') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->streamSpreadsheet($spreadsheet, 'modele_import_eleves.xlsx');
    }

    #[Route('/import', name: 'import', methods: ['POST'])]
    public function import(Request $request): Response
    {
        $school = $this->schoolContextService->getCurrentSchool();

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

        if (count($rows) < 2) {
            $this->addFlash('warning', 'Le fichier ne contient aucune donnée à importer.');
            return $this->redirectToRoute('admin_student_index');
        }

        // Mapping des colonnes par EN-TÊTE (et non par position) : l'import reste
        // correct même si l'ordre des colonnes change ou si des colonnes sont ajoutées.
        $colIndex = $this->mapImportColumns($rows[0]);

        if (!isset($colIndex['lastName'], $colIndex['firstName'])) {
            $this->addFlash('error', 'En-têtes non reconnus : la première ligne doit contenir au moins les colonnes « Nom » et « Prénom ». Téléchargez le modèle pour le bon format.');
            return $this->redirectToRoute('admin_student_index');
        }

        // Lecture d'une cellule de la ligne par nom de champ (via le mapping d'en-têtes).
        $cell = static function (array $row, ?int $idx): ?string {
            if ($idx === null || !array_key_exists($idx, $row)) {
                return null;
            }
            $v = trim((string) ($row[$idx] ?? ''));
            return $v === '' ? null : $v;
        };

        // L'import alimente uniquement le référentiel élève (Student). L'affectation
        // à une classe (inscription) se fait ensuite via le module d'inscription.
        $created = 0;
        $skipped = 0;

        foreach ($rows as $i => $row) {
            if ($i === 0) {
                continue; // Ligne d'en-tête
            }

            $lastName = (string) ($cell($row, $colIndex['lastName'] ?? null) ?? '');
            $firstName = (string) ($cell($row, $colIndex['firstName'] ?? null) ?? '');
            $matricule = (string) ($cell($row, $colIndex['matriculeInterne'] ?? null) ?? '');

            // Ligne entièrement vide : on ignore silencieusement.
            if ($lastName === '' && $firstName === '' && $matricule === '') {
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

            if ($matricule === '') {
                $matricule = $this->matriculeGenerator->generate($this->entityManager, Student::class);
            }
            $student->setMatriculeInterne($matricule);
            $student->setMatriculeNational($cell($row, $colIndex['matriculeNational'] ?? null));

            $gender = strtoupper((string) ($cell($row, $colIndex['gender'] ?? null) ?? ''));
            if (in_array($gender, ['M', 'F'], true)) {
                $student->setGender($gender);
            }

            $dobIdx = $colIndex['dateOfBirth'] ?? null;
            if ($dobIdx !== null && ($dob = $this->parseImportDate($row[$dobIdx] ?? null))) {
                $student->setDateOfBirth($dob);
            }

            $student->setPlaceOfBirth($cell($row, $colIndex['placeOfBirth'] ?? null));
            $student->setNationality($cell($row, $colIndex['nationality'] ?? null));
            $student->setBirthCertificateNumber($cell($row, $colIndex['birthCertificateNumber'] ?? null));
            $student->setCmuNumber($cell($row, $colIndex['cmuNumber'] ?? null));
            $student->setPhone($cell($row, $colIndex['phone'] ?? null));
            $student->setEmail($cell($row, $colIndex['email'] ?? null));
            $student->setAddress($cell($row, $colIndex['address'] ?? null));
            $student->setLastSchoolAttended($cell($row, $colIndex['lastSchoolAttended'] ?? null));

            // Statut administratif : « Affecté » sinon « Non affecté » par défaut.
            $statusCell = mb_strtolower((string) ($cell($row, $colIndex['status'] ?? null) ?? ''));
            $student->setStatus(in_array($statusCell, ['affecte', 'affecté', 'affectee', 'affectée'], true) ? 'affecte' : 'non_affecte');

            $student->setParentName($cell($row, $colIndex['parentName'] ?? null));
            $student->setParentPhone($cell($row, $colIndex['parentPhone'] ?? null));
            $student->setParentEmail($cell($row, $colIndex['parentEmail'] ?? null));
            $student->setParentFunction($cell($row, $colIndex['parentFunction'] ?? null));
            $student->setParentAddress($cell($row, $colIndex['parentAddress'] ?? null));

            $student->setSchool($school);

            $this->entityManager->persist($student);
            $created++;
        }

        if ($created > 0) {
            $this->entityManager->flush();
        }

        if ($created > 0) {
            $message = "{$created} élève(s) importé(s) dans le référentiel. Inscrivez-les dans une classe via le module d'inscription.";
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

    /**
     * Associe chaque colonne du fichier importé à un champ élève, à partir de la
     * ligne d'en-têtes (correspondance insensible à la casse et aux accents).
     * Rend l'import robuste à un changement d'ordre ou à des colonnes en plus.
     *
     * @param array<int, mixed> $headerRow
     * @return array<string, int> champ => index de colonne
     */
    private function mapImportColumns(array $headerRow): array
    {
        // Normalisation : minuscule, sans accents, espaces réduits.
        $normalize = static function (mixed $value): string {
            $s = mb_strtolower(trim((string) ($value ?? '')));
            $s = strtr($s, [
                'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a',
                'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
                'î' => 'i', 'ï' => 'i', ' í' => 'i',
                'ô' => 'o', 'ö' => 'o', 'ó' => 'o',
                'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
                'ç' => 'c',
            ]);
            return preg_replace('/\s+/', ' ', $s);
        };

        // Alias acceptés par champ (forme normalisée). La correspondance est EXACTE
        // pour éviter les collisions (« nom » vs « nom parent »).
        $aliases = [
            'matriculeInterne' => ['matricule interne', 'matricule'],
            'matriculeNational' => ['matricule national'],
            'lastName' => ['nom'],
            'firstName' => ['prenom'],
            'gender' => ['genre (m/f)', 'genre', 'sexe'],
            'dateOfBirth' => ['date de naissance (jj/mm/aaaa)', 'date de naissance', 'date naissance'],
            'placeOfBirth' => ['lieu de naissance', 'lieu naissance'],
            'nationality' => ['nationalite'],
            'birthCertificateNumber' => ['numero extrait de naissance', 'extrait de naissance', 'numero extrait'],
            'cmuNumber' => ['numero cmu', 'cmu'],
            'phone' => ['telephone', 'tel', 'telephone eleve'],
            'email' => ['email', 'e-mail', 'mail'],
            'address' => ['adresse'],
            'lastSchoolAttended' => ['derniere ecole frequentee', 'derniere ecole'],
            'status' => ['statut (affecte/non affecte)', 'statut', 'affectation'],
            'parentName' => ['nom parent', 'nom du parent', 'nom du parent/tuteur'],
            'parentPhone' => ['telephone parent', 'tel parent', 'telephone du parent'],
            'parentEmail' => ['email parent', 'email du parent', 'mail parent'],
            'parentFunction' => ['fonction parent', 'fonction du parent'],
            'parentAddress' => ['domicile parent', 'adresse parent', 'domicile du parent'],
            'photo' => ['photo'],
        ];

        // Index inverse : libellé normalisé => champ.
        $labelToField = [];
        foreach ($aliases as $field => $labels) {
            foreach ($labels as $label) {
                $labelToField[$label] = $field;
            }
        }

        $map = [];
        foreach ($headerRow as $idx => $header) {
            $label = $normalize($header);
            if ($label !== '' && isset($labelToField[$label]) && !isset($map[$labelToField[$label]])) {
                $map[$labelToField[$label]] = (int) $idx;
            }
        }

        return $map;
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

            // La fiche élève ne modifie que le référentiel (identité/contact/tuteur).
            // Le rattachement scolaire (classe/niveau/statut) est géré via l'inscription.
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
    #[IsGranted('ROLE_ADMIN')]
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

    /**
     * Change le statut (affecté / non affecté) de l'inscription de l'année courante
     * et réadapte les frais de scolarité en conséquence.
     */
    #[Route('/{id}/set-status', name: 'set_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function setStatus(Request $request, Student $student): Response
    {
        if (!$this->isCsrfTokenValid('set_status' . $student->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('admin_student_index');
        }

        $status = $request->request->get('status');
        if (!in_array($status, ['affecte', 'non_affecte'], true)) {
            $this->addFlash('error', 'Statut invalide.');
            return $this->redirectToRoute('admin_student_index');
        }

        $year = $this->schoolContextService->getCurrentSchoolYear();
        $registration = $year ? $student->getRegistrationForYear($year) : $student->getLatestRegistration();

        if (!$registration) {
            $this->addFlash('warning', sprintf('%s n\'a pas d\'inscription pour l\'année courante.', $student->getFullName()));
            return $this->redirectToRoute('admin_student_index');
        }

        $student->setStatus($status);

        // Réadapte les frais de l'inscription de l'année selon le nouveau statut.
        $result = $this->feeAssignmentService->syncScolariteFeesForRegistration($registration);
        $this->entityManager->flush();

        $message = sprintf('%s est désormais « %s ».', $student->getFullName(), $student->getStatusLabel());
        $details = [];
        if ($result['added'] > 0) {
            $details[] = sprintf('%d frais ajouté(s)', $result['added']);
        }
        if ($result['removed'] > 0) {
            $details[] = sprintf('%d frais retiré(s)', $result['removed']);
        }
        if ($result['kept_paid'] > 0) {
            $details[] = sprintf('%d frais déjà payé(s) conservé(s)', $result['kept_paid']);
        }
        if ($details !== []) {
            $message .= ' (' . implode(', ', $details) . ')';
        }
        $this->addFlash('success', $message);

        $referer = $request->headers->get('referer');
        return $referer ? $this->redirect($referer) : $this->redirectToRoute('admin_student_index');
    }
}
