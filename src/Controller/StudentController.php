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
use App\Service\StudentImporter;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
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

    /**
     * Changement de niveau d'un élève inscrit pour l'année courante : l'inscription
     * est déplacée dans une classe d'un autre niveau, les frais de scolarité sont
     * remplacés par ceux du nouveau niveau et les paiements déjà faits y sont reportés.
     * L'opération est historisée comme un transfert (classe d'origine → destination).
     */
    #[Route('/level-change', name: 'level_change', methods: ['GET', 'POST'])]
    public function levelChange(
        Request $request,
        ClassroomRepository $classroomRepository,
        LevelRepository $levelRepository,
        \App\Repository\RegistrationRepository $registrationRepository,
        StudentTransferRepository $transferRepository,
        SluggerInterface $slugger
    ): Response {
        $school = $this->schoolContextService->getCurrentSchool();
        $schoolYear = $this->schoolContextService->getCurrentSchoolYear();

        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour changer le niveau d\'un élève.');
            return $this->redirectToRoute('admin_student_index');
        }

        $classrooms = $classroomRepository->findBySchoolAndYear($school->getId(), $schoolYear?->getId());
        $registrations = $registrationRepository->findBySchoolAndYear($school->getId(), $schoolYear?->getId());

        if ($request->isMethod('POST')) {
            $registration = $registrationRepository->find((int) $request->request->get('registration_id'));
            $toClassroom = $classroomRepository->find((int) $request->request->get('to_class_id'));
            $motif = trim((string) $request->request->get('motif'));
            /** @var UploadedFile|null $documentFile */
            $documentFile = $request->files->get('document');

            $student = $registration?->getStudent();
            $currentLevel = $registration?->getLevel() ?? $registration?->getPreRegistration()?->getRequestedLevel();
            $occupied = $toClassroom ? ($registrationRepository->countActiveByClassroom($school->getId(), $schoolYear?->getId())[$toClassroom->getId()] ?? 0) : 0;

            if (!$this->isCsrfTokenValid('level_change', $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de sécurité invalide.');
            } elseif (!$registration || !$student || !in_array($registration, $registrations, true)) {
                $this->addFlash('error', 'Veuillez sélectionner un élève inscrit pour l\'année courante.');
            } elseif (!$toClassroom || !in_array($toClassroom, $classrooms, true) || !$toClassroom->getLevel()) {
                $this->addFlash('error', 'Veuillez sélectionner une classe de destination valide.');
            } elseif ($currentLevel && $toClassroom->getLevel()->getId() === $currentLevel->getId()) {
                $this->addFlash('error', 'La classe de destination est du même niveau : utilisez plutôt le transfert de classe.');
            } elseif ($toClassroom->getCapacity() !== null && $occupied >= $toClassroom->getCapacity()) {
                $this->addFlash('error', sprintf('La classe « %s » est complète (%d/%d).', $toClassroom->getName(), $occupied, $toClassroom->getCapacity()));
            } elseif ($motif === '') {
                $this->addFlash('error', 'Le motif du changement de niveau est obligatoire.');
            } else {
                $transfer = (new StudentTransfer())
                    ->setStudent($student)
                    ->setFromClassroom($registration->getClassroom())
                    ->setToClassroom($toClassroom)
                    ->setSchoolYear($schoolYear)
                    ->setMotif('[Changement de niveau ' . ($currentLevel?->getName() ?? '—') . ' → ' . $toClassroom->getLevel()->getName() . '] ' . $motif)
                    ->setRecordedBy($this->getUser());
                if ($documentFile instanceof UploadedFile) {
                    $transfer->setDocumentPath($this->uploadFile($documentFile, 'transfers', $slugger));
                }
                $this->entityManager->persist($transfer);

                // Nouvelle classe (donc nouveau niveau) + niveau demandé de la préinscription.
                $registration->setClassroom($toClassroom);
                $registration->getPreRegistration()?->setRequestedLevel($toClassroom->getLevel());

                $result = $this->feeAssignmentService->syncScolariteFeesForRegistration($registration);
                $this->entityManager->flush();

                $details = [];
                if ($result['added'] > 0) {
                    $details[] = sprintf('%d frais du nouveau niveau ajouté(s)', $result['added']);
                }
                if ($result['removed'] > 0) {
                    $details[] = sprintf('%d frais de l\'ancien niveau retiré(s)', $result['removed']);
                }
                if ($result['moved_amount'] > 0) {
                    $details[] = sprintf('%s FCFA déjà payés reportés', number_format($result['moved_amount'], 0, ',', ' '));
                }
                $this->addFlash('success', sprintf(
                    '%s passe en %s (classe « %s »).%s',
                    $student->getFullName(),
                    $toClassroom->getLevel()->getName(),
                    $toClassroom->getName(),
                    $details !== [] ? ' ' . ucfirst(implode(', ', $details)) . '.' : ''
                ));
                if ($result['kept_paid'] > 0) {
                    $this->addFlash('warning', 'Le nouveau niveau n\'a aucun frais de scolarité : les frais déjà payés de l\'ancien niveau ont été conservés.');
                }
                if ($result['overpaid'] > 0) {
                    $this->addFlash('warning', sprintf(
                        'Les montants déjà payés dépassent les frais du nouveau niveau de %s FCFA (trop-perçu à régulariser).',
                        number_format($result['overpaid'], 0, ',', ' ')
                    ));
                }

                return $this->redirectToRoute('admin_student_level_change');
            }
        }

        // Historique : uniquement les transferts entre deux niveaux différents.
        $history = array_values(array_filter(
            $transferRepository->findBySchool($school->getId(), 200),
            static fn (StudentTransfer $t) => $t->getFromClassroom()?->getLevel()?->getId() !== $t->getToClassroom()?->getLevel()?->getId()
        ));

        return $this->render('student/level_change.html.twig', [
            'registrations' => $registrations,
            'classrooms' => $classrooms,
            'levels' => $levelRepository->findBySchool($school->getId()),
            'occupancy' => $registrationRepository->countActiveByClassroom($school->getId(), $schoolYear?->getId()),
            'history' => $history,
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
            // Recherche sur toute la base (nom, prénom, nom complet dans les deux sens, matricules).
            $queryBuilder->andWhere('s.firstName LIKE :search OR s.lastName LIKE :search'
                    . " OR CONCAT(s.lastName, ' ', s.firstName) LIKE :search OR CONCAT(s.firstName, ' ', s.lastName) LIKE :search"
                    . ' OR s.matriculeInterne LIKE :search OR s.matriculeNational LIKE :search')
                ->setParameter('search', '%' . trim($search) . '%');
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
            // Mêmes critères que la liste (index).
            $qb->andWhere('s.firstName LIKE :search OR s.lastName LIKE :search'
                    . " OR CONCAT(s.lastName, ' ', s.firstName) LIKE :search OR CONCAT(s.firstName, ' ', s.lastName) LIKE :search"
                    . ' OR s.matriculeInterne LIKE :search OR s.matriculeNational LIKE :search')
                ->setParameter('search', '%' . trim($search) . '%');
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

    /** Imports d'élèves en attente de confirmation, par jeton. */
    private const IMPORT_SESSION_KEY = 'student_imports';

    /**
     * Import d'élèves, étape 1 : analyse du fichier, sans rien enregistrer.
     * Chaque ligne est rapprochée d'un élève par son matricule interne : les élèves
     * connus sont mis à jour (champs modifiés uniquement), les autres sont créés.
     * Le fichier est conservé (var/imports/students) le temps de la confirmation.
     */
    #[Route('/import', name: 'import', methods: ['POST'])]
    public function import(Request $request, StudentImporter $importer): Response
    {
        if (!$this->isCsrfTokenValid('import_students', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide. Veuillez réessayer.');
            return $this->redirectToRoute('admin_student_index');
        }

        $school = $this->schoolContextService->getCurrentSchool();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour importer des élèves.');
            return $this->redirectToRoute('admin_student_index');
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            $this->addFlash('error', 'Veuillez sélectionner un fichier à importer.');
            return $this->redirectToRoute('admin_student_index');
        }
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (!\in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
            $this->addFlash('error', 'Format de fichier invalide. Formats acceptés : XLSX, XLS, CSV.');
            return $this->redirectToRoute('admin_student_index');
        }

        $token = bin2hex(random_bytes(16));
        $dir = $this->getParameter('kernel.project_dir') . '/var/imports/students';
        // Fichiers d'aperçus jamais confirmés : supprimés au bout de 24 h.
        foreach (glob($dir . '/*') ?: [] as $old) {
            if (is_file($old) && filemtime($old) < time() - 86400) {
                @unlink($old);
            }
        }
        $stored = $file->move($dir, $token . '.' . $extension)->getPathname();

        $result = $importer->import($stored, $school, false);
        if ($result['error'] !== null) {
            @unlink($stored);
            $this->addFlash('error', $result['error']);
            return $this->redirectToRoute('admin_student_index');
        }

        $imports = $request->getSession()->get(self::IMPORT_SESSION_KEY, []);
        $imports[$token] = ['path' => $stored, 'name' => $file->getClientOriginalName(), 'school_id' => $school->getId()];
        $request->getSession()->set(self::IMPORT_SESSION_KEY, $imports);

        return $this->render('student/import_preview.html.twig', [
            'result' => $result,
            'token' => $token,
            'file_name' => $file->getClientOriginalName(),
        ]);
    }

    /**
     * Import d'élèves, étape 2 : enregistrement des lignes valides (re-vérifiées)
     * dans une transaction unique.
     */
    #[Route('/import/{token}/confirm', name: 'import_confirm', methods: ['POST'], requirements: ['token' => '[a-f0-9]{32}'])]
    public function importConfirm(string $token, Request $request, StudentImporter $importer): Response
    {
        $session = $request->getSession();
        $imports = $session->get(self::IMPORT_SESSION_KEY, []);
        $pending = $imports[$token] ?? null;
        unset($imports[$token]);
        $session->set(self::IMPORT_SESSION_KEY, $imports);

        $school = $this->schoolContextService->getCurrentSchool();
        if (!$pending || !is_file($pending['path'])) {
            $this->addFlash('warning', 'Cet import a expiré ou a déjà été confirmé. Veuillez recommencer.');
            return $this->redirectToRoute('admin_student_index');
        }
        if (!$this->isCsrfTokenValid('import_students_confirm_' . $token, (string) $request->request->get('_token'))
            || !$school || $school->getId() !== $pending['school_id']) {
            @unlink($pending['path']);
            $this->addFlash('error', 'Import annulé : session expirée ou établissement modifié entre-temps.');
            return $this->redirectToRoute('admin_student_index');
        }

        try {
            $result = $importer->import($pending['path'], $school, true);
        } finally {
            @unlink($pending['path']);
        }

        if ($result['error'] !== null) {
            $this->addFlash('error', $result['error']);
        } elseif ($result['created'] + $result['updated'] > 0) {
            $this->addFlash('success', sprintf(
                'Import terminé : %d élève(s) créé(s), %d mis à jour, %d inchangé(s).%s',
                $result['created'],
                $result['updated'],
                $result['unchanged'],
                $result['skipped'] > 0 ? sprintf(' %d ligne(s) ignorée(s).', $result['skipped']) : ''
            ));
            if ($result['created'] > 0) {
                $this->addFlash('info', 'Inscrivez les nouveaux élèves dans une classe via le module d\'inscription.');
            }
        } else {
            $this->addFlash('warning', 'Aucune modification : aucune ligne valide à créer ou mettre à jour.');
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

    /**
     * Suppression (une ou plusieurs lignes cochées) d'élèves — super-administrateur
     * uniquement. Suppression EN CASCADE : préinscriptions, inscriptions, frais,
     * paiements (+ écritures comptables), notes, absences, bulletins, transferts…
     * Chaque élève est traité dans sa propre transaction.
     */
    #[Route('/bulk-delete', name: 'bulk_delete', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function bulkDelete(Request $request, StudentRepository $studentRepository, \Doctrine\Persistence\ManagerRegistry $registry, \App\Service\CascadeDeletionService $cascadeDeletion): Response
    {
        if (!$this->isCsrfTokenValid('bulk_delete_students', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide. Veuillez réessayer.');
            return $this->redirectToRoute('admin_student_index');
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $request->request->all('ids')))));
        if (!$ids) {
            $this->addFlash('warning', 'Aucun élève sélectionné.');
            return $this->redirectToRoute('admin_student_index');
        }

        $school = $this->schoolContextService->getCurrentSchool();
        $deleted = 0;
        $failed = [];

        foreach ($ids as $id) {
            $student = $studentRepository->find($id);
            // On ne supprime que les élèves de l'établissement courant.
            if (!$student || ($school && $student->getSchool()?->getId() !== $school->getId())) {
                continue;
            }
            $label = trim($student->getLastName() . ' ' . $student->getFirstName());

            try {
                $cascadeDeletion->deleteStudent($student);
                $deleted++;
            } catch (\Throwable $e) {
                // L'EntityManager est fermé après l'échec : on le réinitialise pour continuer.
                $registry->resetManager();
                $failed[] = $label;
            }
        }

        if ($deleted > 0) {
            $this->addFlash('success', sprintf('%d élève(s) supprimé(s) avec toutes leurs données.', $deleted));
        }
        if ($failed) {
            $this->addFlash('error', sprintf('Suppression impossible pour %d élève(s) : %s.', count($failed), implode(', ', $failed)));
        }

        $referer = $request->headers->get('referer');
        return $referer ? $this->redirect($referer) : $this->redirectToRoute('admin_student_index');
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

        // Regroupement des frais par année scolaire (affichage « frais + total par année »).
        // Les frais sans inscription rattachée tombent dans un groupe « non rattaché ».
        $feesByYear = [];
        foreach ($student->getStudentFees() as $sf) {
            $year = $sf->getRegistration()?->getSchoolYear();
            $key = $year?->getId() ?? 0;
            if (!isset($feesByYear[$key])) {
                $feesByYear[$key] = [
                    'year_name' => $year?->getName() ?? 'Frais non rattachés à une année',
                    'start' => $year?->getStartDate(),
                    'fees' => [],
                    'total' => 0.0,
                    'paid' => 0.0,
                    'remaining' => 0.0,
                ];
            }
            $feesByYear[$key]['fees'][] = $sf;
            if ($sf->getFee()?->isActive()) {
                $feesByYear[$key]['total'] += (float) $sf->getAmount();
                $feesByYear[$key]['paid'] += (float) $sf->getPaidAmount();
            }
        }
        foreach ($feesByYear as &$grp) {
            $grp['remaining'] = max(0, $grp['total'] - $grp['paid']);
        }
        unset($grp);
        // Années les plus récentes d'abord ; groupe « non rattaché » (start null) en dernier.
        uasort($feesByYear, static function (array $a, array $b): int {
            if ($a['start'] === null) {
                return $b['start'] === null ? 0 : 1;
            }
            if ($b['start'] === null) {
                return -1;
            }
            return $b['start'] <=> $a['start'];
        });

        return $this->render('student/show.html.twig', [
            'student' => $student,
            'available_fees' => $availableFees,
            'fees_by_year' => $feesByYear,
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
    public function edit(Request $request, Student $student, SluggerInterface $slugger, StudentRepository $studentRepository): Response
    {
        $form = $this->createForm(StudentType::class, $student);
        $form->handleRequest($request);

        // Matricule national unique tous établissements confondus (le validateur de
        // l'entité ne voit que l'établissement courant, à cause du filtre Doctrine).
        if ($form->isSubmitted() && $studentRepository->nationalMatriculeExists($student->getMatriculeNational(), $student->getId())) {
            $form->get('matriculeNational')->addError(new \Symfony\Component\Form\FormError('Un élève avec ce matricule national existe déjà.'));
        }

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

        // Réadapte les frais de l'inscription de l'année selon le nouveau statut : les
        // anciens frais sont supprimés et ce qui a déjà été payé est reporté sur les nouveaux.
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
        if ($result['moved_amount'] > 0) {
            $details[] = sprintf('%s FCFA déjà payés reportés sur les nouveaux frais', number_format($result['moved_amount'], 0, ',', ' '));
        }
        if ($details !== []) {
            $message .= ' (' . implode(', ', $details) . ')';
        }
        $this->addFlash('success', $message);
        if ($result['kept_paid'] > 0) {
            $this->addFlash('warning', 'Aucun frais de scolarité ne correspond au nouveau statut : les frais déjà payés ont été conservés.');
        }
        if ($result['overpaid'] > 0) {
            $this->addFlash('warning', sprintf(
                'Les montants déjà payés dépassent les nouveaux frais de %s FCFA (trop-perçu à régulariser).',
                number_format($result['overpaid'], 0, ',', ' ')
            ));
        }

        $referer = $request->headers->get('referer');
        return $referer ? $this->redirect($referer) : $this->redirectToRoute('admin_student_index');
    }
}
