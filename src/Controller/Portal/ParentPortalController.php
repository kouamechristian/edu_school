<?php

namespace App\Controller\Portal;

use App\Controller\Concern\HandlesFileUpload;
use App\Entity\Level;
use App\Entity\Notification;
use App\Entity\Payment;
use App\Entity\PreRegistration;
use App\Entity\PreRegistrationDocument;
use App\Entity\Student;
use App\Form\PreRegistrationType;
use App\Repository\CourseRepository;
use App\Repository\NotificationRepository;
use App\Repository\PaymentRepository;
use App\Repository\PeriodRepository;
use App\Repository\PreRegistrationRepository;
use App\Repository\TimeSlotRepository;
use App\Repository\StudentFeeRepository;
use App\Repository\StudentRepository;
use App\Repository\UserRepository;
use App\Security\Voter\ChildVoter;
use App\Service\MatriculeGenerator;
use App\Service\ParentContextService;
use App\Service\ParentPortalService;
use App\Service\Payment\PaymentGatewayException;
use App\Service\Payment\PaymentInitiator;
use App\Service\PreRegistrationFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Portail Parent — espace en lecture seule pour le suivi des enfants.
 *
 * Sécurité en profondeur :
 *  1. ROLE_PARENT exigé au niveau de la classe (et via access_control ^/parent).
 *  2. Chaque action ciblant un élève vérifie ChildVoter::VIEW → un parent ne peut
 *     jamais accéder à l'enfant d'un autre (protection IDOR).
 */
#[Route('/parent')]
#[IsGranted('ROLE_PARENT')]
class ParentPortalController extends AbstractController
{
    use HandlesFileUpload;

    public function __construct(
        private readonly ParentPortalService $portal,
    ) {
    }

    #[Route('', name: 'parent_dashboard', methods: ['GET'])]
    public function dashboard(ParentContextService $context): Response
    {
        $selectedYear = $context->getSelectedYear();

        return $this->render('parent/dashboard.html.twig', [
            'cards' => $this->portal->getDashboard($this->getCurrentParent(), $selectedYear?->getId()),
            'selected_year' => $selectedYear,
        ]);
    }

    /**
     * Bascule de l'année scolaire affichée dans l'espace parent.
     */
    #[Route('/annee/{id}', name: 'parent_switch_year', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function switchYear(int $id, Request $request, ParentContextService $context): Response
    {
        $context->setSelectedYear($id);

        // Retour à la page précédente (ou tableau de bord par défaut).
        $referer = $request->headers->get('referer');

        return $this->redirect($referer ?: $this->generateUrl('parent_dashboard'));
    }

    /**
     * Auto-association : un parent rattache un de ses enfants à son compte en
     * fournissant le matricule interne et la date de naissance de l'élève.
     *
     * Le rattachement est explicite et additif (table parent_child) : il n'écrase
     * jamais les données du secrétariat et ne retire aucun accès existant.
     */
    #[Route('/rattacher', name: 'parent_link_child', methods: ['GET', 'POST'])]
    public function linkChild(Request $request, StudentRepository $studentRepository, UserRepository $userRepository, EntityManagerInterface $em): Response
    {
        $parent = $this->getCurrentParent();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('parent_link_child', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

                return $this->redirectToRoute('parent_link_child');
            }

            $matriculeNational = trim((string) $request->request->get('matricule_national'));

            if ($matriculeNational === '') {
                $this->addFlash('error', 'Veuillez renseigner le matricule national de l\'élève.');

                return $this->redirectToRoute('parent_link_child');
            }

            $child = $studentRepository->findOneActiveByMatriculeNational($matriculeNational);

            if (!$child) {
                $this->addFlash('error', "Aucun élève actif ne correspond à ce matricule national. Vérifiez le matricule auprès du secrétariat.");

                return $this->redirectToRoute('parent_link_child');
            }

            // Déjà rattaché à VOTRE compte (lien explicite ou historique par e-mail) ?
            if ($this->isGranted(ChildVoter::VIEW, $child)) {
                $this->addFlash('info', sprintf('%s est déjà rattaché·e à votre compte.', $child->getFullName()));

                return $this->redirectToRoute('parent_child_show', ['id' => $child->getId()]);
            }

            // Exclusivité : un enfant ne peut être rattaché qu'à un seul parent à la fois.
            if ($userRepository->findParentOfStudent($child) !== null) {
                $this->addFlash('error', "Cet élève est déjà rattaché à un autre parent. Si vous pensez qu'il s'agit d'une erreur, contactez le secrétariat.");

                return $this->redirectToRoute('parent_link_child');
            }

            $parent->addChild($child);

            // Rattache aussi le parent à l'établissement de l'enfant (contexte de navigation).
            if ($school = $child->getSchool() ?? $child->getClassroom()?->getSchool() ?? $child->getLevel()?->getSchool()) {
                $parent->addSchool($school);
            }

            $em->flush();

            $this->addFlash('success', sprintf('%s a bien été rattaché·e à votre compte.', $child->getFullName()));

            return $this->redirectToRoute('parent_child_show', ['id' => $child->getId()]);
        }

        return $this->render('parent/link_child.html.twig');
    }

    #[Route('/enfant/{id}', name: 'parent_child_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function child(
        Student $child,
        CourseRepository $courseRepository,
        TimeSlotRepository $timeSlotRepository,
        PreRegistrationRepository $preRegistrationRepository,
        StudentFeeRepository $studentFeeRepository,
    ): Response {
        $this->denyAccessUnlessGranted(ChildVoter::VIEW, $child);

        $period = $this->portal->getCurrentPeriod($child);

        $classroom = $child->getClassroom();
        $schedule = $classroom ? $courseRepository->findScheduleByClassroom($classroom->getId()) : [];
        $timeSlots = $classroom ? $timeSlotRepository->findBySchool($classroom->getSchool()?->getId()) : [];

        // Préinscription de suivi : ancien élève (existingStudent) ou nouvel élève
        // (préinscription d'origine portée par Student.preRegistration) — la plus récente.
        $preRegistration = $preRegistrationRepository->findLatestForStudent($child->getId());
        $own = $child->getPreRegistration();
        if ($own !== null && ($preRegistration === null || $own->getCreatedAt() > $preRegistration->getCreatedAt())) {
            $preRegistration = $own;
        }

        // Détail des frais par échéancier : affiché à la validation (frais affectés).
        $feeDetail = ($preRegistration !== null && in_array($preRegistration->getStatus(), ['validated', 'enrolled'], true))
            ? $this->buildFeeScheduleDetail($child, $studentFeeRepository)
            : [];

        return $this->render('parent/child_show.html.twig', [
            'child' => $child,
            'period' => $period,
            'academic' => $this->portal->getAcademicReport($child, $period),
            'attendance' => $this->portal->getAttendanceReport($child, $period),
            'finance' => $this->portal->getFinancialReport($child),
            'classroom' => $classroom,
            'schedule' => $schedule,
            'time_slots' => $timeSlots,
            'pre_registration' => $preRegistration,
            'fee_detail' => $feeDetail,
        ]);
    }

    /**
     * Réinscription (préinscription) d'un enfant déjà connu de l'établissement
     * par son parent, pour une nouvelle année scolaire.
     *
     * Le formulaire est pré-rempli avec les informations de l'élève ; la demande
     * est enregistrée au statut « pending » et suit ensuite le circuit de
     * validation classique du secrétariat (back-office).
     */
    #[Route('/enfant/{id}/reinscription', name: 'parent_child_reenroll', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function reenroll(
        Student $child,
        Request $request,
        EntityManagerInterface $em,
        PreRegistrationFactory $preRegistrationFactory,
        MatriculeGenerator $matriculeGenerator,
        SluggerInterface $slugger,
    ): Response {
        $this->denyAccessUnlessGranted(ChildVoter::VIEW, $child);

        // L'établissement de l'enfant (avec repli sur la classe / le niveau).
        $school = $child->getSchool()
            ?? $child->getClassroom()?->getSchool()
            ?? $child->getLevel()?->getSchool();

        if (!$school) {
            $this->addFlash('error', "Cet élève n'est rattaché à aucun établissement. Contactez le secrétariat.");

            return $this->redirectToRoute('parent_child_show', ['id' => $child->getId()]);
        }

        $levels = $em->getRepository(Level::class)->findBy(['school' => $school], ['name' => 'ASC']);

        // L'identité de l'élève au fil des années est portée par le matricule national
        // (comme le flux « ancien élève » du secrétariat) : on ne lie pas la relation
        // OneToOne Student↔PreRegistration, réservée à l'inscription effective.
        $preRegistration = $preRegistrationFactory->fromStudent($child, $school, null);

        $form = $this->createForm(PreRegistrationType::class, $preRegistration, [
            'levels' => $levels,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Garde-fou : une demande identique (même élève, même année) est déjà en cours.
            $duplicate = $em->getRepository(PreRegistration::class)->createQueryBuilder('p')
                ->andWhere('p.firstName = :first')
                ->andWhere('p.lastName = :last')
                ->andWhere('p.dateOfBirth = :dob')
                ->andWhere('p.schoolYear = :year')
                ->andWhere('p.status IN (:statuses)')
                ->setParameter('first', $preRegistration->getFirstName())
                ->setParameter('last', $preRegistration->getLastName())
                ->setParameter('dob', $preRegistration->getDateOfBirth())
                ->setParameter('year', $preRegistration->getSchoolYear())
                ->setParameter('statuses', ['pending', 'documents_received', 'validated'])
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($duplicate) {
                $this->addFlash('warning', sprintf(
                    'Une demande de préinscription pour %s sur cette année scolaire est déjà en cours de traitement.',
                    $child->getFullName()
                ));

                return $this->redirectToRoute('parent_child_show', ['id' => $child->getId()]);
            }

            if (!$preRegistration->getMatriculeInterne()) {
                $preRegistration->setMatriculeInterne(
                    $matriculeGenerator->generate($em, PreRegistration::class)
                );
            }

            if ($photo = $this->uploadFile($form->get('photoFile')->getData(), 'students', $slugger)) {
                $preRegistration->setPhoto($photo);
            }

            // Marque l'origine « parent » : déclenche frais + notification à la validation.
            $preRegistration->setSubmittedBy($this->getCurrentParent());

            // Pièces jointes téléversées par le parent en même temps que la demande.
            $docCount = $this->attachUploadedDocuments($preRegistration, $request, $slugger);

            // Avec des documents, la demande est directement « prête à valider » ;
            // sinon elle reste « en attente » (le secrétariat réclamera les pièces).
            $preRegistration->setStatus($docCount > 0 ? 'documents_received' : 'pending');

            $em->persist($preRegistration);
            $em->flush();

            $this->addFlash('success', sprintf(
                'La demande de préinscription de %s a bien été transmise%s. Vous serez notifié(e) dès sa validation.',
                $child->getFullName(),
                $docCount > 0 ? sprintf(' avec %d document(s)', $docCount) : ''
            ));

            return $this->redirectToRoute('parent_child_show', ['id' => $child->getId()]);
        }

        return $this->render('parent/reenroll.html.twig', [
            'child' => $child,
            'school' => $school,
            'form' => $form,
        ]);
    }

    #[Route('/enfant/{id}/notes', name: 'parent_child_grades', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function grades(Student $child, Request $request, PeriodRepository $periodRepository): Response
    {
        $this->denyAccessUnlessGranted(ChildVoter::VIEW, $child);

        $period = $this->resolvePeriod($child, $request, $periodRepository);

        return $this->render('parent/grades.html.twig', [
            'child' => $child,
            'periods' => $this->portal->getPeriods($child),
            'period' => $period,
            'academic' => $this->portal->getAcademicReport($child, $period),
        ]);
    }

    #[Route('/enfant/{id}/absences', name: 'parent_child_absences', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function absences(Student $child, Request $request, PeriodRepository $periodRepository): Response
    {
        $this->denyAccessUnlessGranted(ChildVoter::VIEW, $child);

        $period = $this->resolvePeriod($child, $request, $periodRepository);

        return $this->render('parent/absences.html.twig', [
            'child' => $child,
            'periods' => $this->portal->getPeriods($child),
            'period' => $period,
            'attendance' => $this->portal->getAttendanceReport($child, $period),
        ]);
    }

    #[Route('/enfant/{id}/finances', name: 'parent_child_finance', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function finance(Student $child, PaymentRepository $paymentRepository): Response
    {
        $this->denyAccessUnlessGranted(ChildVoter::VIEW, $child);

        return $this->render('parent/finance.html.twig', [
            'child' => $child,
            'finance' => $this->portal->getFinancialReport($child),
            'registration' => $child->getScolariteRegistration(),
            'payments' => $paymentRepository->findByStudent($child),
        ]);
    }

    /**
     * Emploi du temps de la classe de l'enfant (lecture seule).
     */
    #[Route('/enfant/{id}/emploi-du-temps', name: 'parent_child_schedule', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function schedule(
        Student $child,
        CourseRepository $courseRepository,
        TimeSlotRepository $timeSlotRepository,
    ): Response {
        $this->denyAccessUnlessGranted(ChildVoter::VIEW, $child);

        $classroom = $child->getClassroom();
        $schedule = [];
        $timeSlots = [];

        if ($classroom) {
            $schedule = $courseRepository->findScheduleByClassroom($classroom->getId());
            $timeSlots = $timeSlotRepository->findBySchool($classroom->getSchool()?->getId());
        }

        return $this->render('parent/schedule.html.twig', [
            'child' => $child,
            'classroom' => $classroom,
            'schedule' => $schedule,
            'time_slots' => $timeSlots,
        ]);
    }

    /**
     * Paiement en ligne de la scolarité (espace parent → passerelle GeniusPay).
     *
     * On valide la demande, on crée un paiement « en attente » via PaymentInitiator,
     * puis on redirige le parent vers la page de checkout de la passerelle. Le solde
     * n'est imputé qu'au retour du webhook confirmant le paiement.
     */
    #[Route('/enfant/{id}/payer', name: 'parent_child_pay', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function payFee(
        Student $child,
        Request $request,
        StudentFeeRepository $studentFeeRepository,
        PaymentInitiator $paymentInitiator,
    ): Response {
        $this->denyAccessUnlessGranted(ChildVoter::VIEW, $child);

        $redirect = $this->redirectToRoute('parent_child_finance', ['id' => $child->getId()]);

        if (!$this->isCsrfTokenValid('parent_pay' . $child->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $redirect;
        }

        $amount = (float) $request->request->get('amount', 0);

        $studentFee = $studentFeeRepository->find((int) $request->request->get('student_fee_id'));
        if (!$studentFee || $studentFee->getStudent()?->getId() !== $child->getId() || !$studentFee->getFee()?->isActive()) {
            $this->addFlash('error', 'Frais invalide pour cet élève.');

            return $redirect;
        }

        try {
            $payment = $paymentInitiator->initiate($studentFee, $amount, $this->getCurrentParent());
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $redirect;
        } catch (PaymentGatewayException $e) {
            $this->addFlash('error', $e->getMessage() ?: 'Le paiement en ligne est momentanément indisponible. Veuillez réessayer plus tard.');

            return $redirect;
        }

        // Redirection vers la page de paiement sécurisée de GeniusPay.
        if ($payment->getCheckoutUrl()) {
            return $this->redirect($payment->getCheckoutUrl());
        }

        $this->addFlash('warning', 'Paiement initié, mais l\'URL de paiement est indisponible. Réessayez.');

        return $redirect;
    }

    /**
     * Page de retour après le checkout GeniusPay.
     *
     * Affiche l'état du paiement (le statut définitif est posé par le webhook ;
     * ici on montre l'état courant, éventuellement encore « en attente »).
     */
    #[Route('/paiement/{id}/retour', name: 'parent_payment_return', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function paymentReturn(
        \App\Entity\Payment $payment,
        \App\Service\Payment\PaymentStatusSynchronizer $statusSynchronizer,
    ): Response {
        $child = $payment->getStudent();
        if (!$child) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(ChildVoter::VIEW, $child);

        // Vérification du statut auprès de la passerelle (complément au webhook) :
        // confirme le paiement dès le retour, même sans URL publique en local.
        $statusSynchronizer->synchronize($payment);

        return $this->render('parent/payment_return.html.twig', [
            'payment' => $payment,
            'child' => $child,
        ]);
    }

    /**
     * « Mes paiements » : historique de tous les paiements des enfants du parent.
     */
    #[Route('/paiements', name: 'parent_payments', methods: ['GET'])]
    public function payments(\App\Repository\PaymentRepository $paymentRepository): Response
    {
        $children = $this->portal->getChildren($this->getCurrentParent());
        $studentIds = array_map(static fn (Student $c) => $c->getId(), $children);

        return $this->render('parent/payments.html.twig', [
            'payments' => $paymentRepository->findByStudentIds($studentIds),
        ]);
    }

    /**
     * Centre de notifications du parent (frais à régler, validations, etc.).
     */
    #[Route('/notifications', name: 'parent_notifications', methods: ['GET'])]
    public function notifications(NotificationRepository $notificationRepository): Response
    {
        return $this->render('parent/notifications.html.twig', [
            'notifications' => $notificationRepository->findForUser($this->getCurrentParent()),
        ]);
    }

    /**
     * Ouvre une notification : la marque comme lue puis redirige vers son lien.
     */
    #[Route('/notifications/{id}/ouvrir', name: 'parent_notification_open', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function openNotification(Notification $notification, EntityManagerInterface $em): Response
    {
        if ($notification->getRecipient()?->getId() !== $this->getCurrentParent()->getId()) {
            throw $this->createAccessDeniedException();
        }

        $notification->setIsRead(true);
        $em->flush();

        return $this->redirect($notification->getLink() ?: $this->generateUrl('parent_notifications'));
    }

    /**
     * Marque toutes les notifications du parent comme lues.
     */
    #[Route('/notifications/lire-tout', name: 'parent_notifications_read_all', methods: ['POST'])]
    public function readAllNotifications(Request $request, NotificationRepository $notificationRepository, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('parent_notifications_read_all', (string) $request->request->get('_token'))) {
            foreach ($notificationRepository->findForUser($this->getCurrentParent()) as $notification) {
                $notification->setIsRead(true);
            }
            $em->flush();
            $this->addFlash('success', 'Toutes vos notifications ont été marquées comme lues.');
        }

        return $this->redirectToRoute('parent_notifications');
    }

    /**
     * Résout la période demandée (?period=ID) en la validant contre les périodes
     * de l'élève ; à défaut, retourne la période courante.
     */
    private function resolvePeriod(Student $child, Request $request, PeriodRepository $periodRepository): mixed
    {
        $requestedId = $request->query->getInt('period');

        if ($requestedId > 0) {
            foreach ($this->portal->getPeriods($child) as $period) {
                if ($period->getId() === $requestedId) {
                    return $period;
                }
            }
        }

        return $this->portal->getCurrentPeriod($child);
    }

    /**
     * Téléverse les pièces jointes envoyées avec la préinscription (champ
     * « documents[] ») et les rattache à la préinscription.
     *
     * @return int Nombre de documents effectivement enregistrés
     */
    private function attachUploadedDocuments(PreRegistration $preRegistration, Request $request, SluggerInterface $slugger): int
    {
        $files = $request->files->get('documents', []);
        if (!is_array($files)) {
            $files = [$files];
        }

        $count = 0;
        foreach ($files as $file) {
            if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                continue;
            }

            $originalName = $file->getClientOriginalName();
            $size = $file->getSize();
            $mime = $file->getClientMimeType();

            $relativePath = $this->uploadFile($file, 'pre_registration_documents', $slugger);
            if (!$relativePath) {
                continue;
            }

            $doc = new PreRegistrationDocument();
            $doc->setFileName(basename($relativePath));
            $doc->setOriginalFileName($originalName);
            $doc->setMimeType($mime ?: 'application/octet-stream');
            $doc->setFileSize((int) $size);
            $doc->setFilePath($relativePath);
            $doc->setPreRegistration($preRegistration);
            $preRegistration->addDocument($doc);

            $count++;
        }

        return $count;
    }

    /**
     * Détail des frais de l'élève rangés par échéancier : pour chaque frais, ses
     * échéances (ordre, date, montant) avec l'imputation des paiements en cascade
     * (les plus anciennes d'abord) → déjà réglé / reste à payer par échéance.
     *
     * @return list<array{name: string, category: string, total: float, paid: float,
     *     remaining: float, schedules: list<array{order: int, due: ?\DateTimeInterface,
     *     amount: float, paid: float, remaining: float}>}>
     */
    private function buildFeeScheduleDetail(Student $child, StudentFeeRepository $studentFeeRepository): array
    {
        $detail = [];

        foreach ($studentFeeRepository->findByStudent($child->getId()) as $studentFee) {
            $fee = $studentFee->getFee();
            if (!$fee) {
                continue;
            }

            $schedules = $fee->getSchedules()->toArray();
            usort($schedules, static fn ($a, $b) => ($a->getOrderNumber() ?? 0) <=> ($b->getOrderNumber() ?? 0));

            // Imputation du montant payé sur les échéances, en cascade. Le bouton
            // « Payer » n'est actif que sur la PREMIÈRE échéance non soldée (la prochaine
            // due), car l'imputation d'un paiement se fait de la plus ancienne à la plus
            // récente : on règle donc les échéances dans l'ordre.
            $paidLeft = (float) $studentFee->getPaidAmount();
            $payActive = $fee->isActive();
            $nextPayableSet = false;
            $rows = [];

            if ($schedules !== []) {
                foreach ($schedules as $i => $schedule) {
                    $amount = (float) $schedule->getAmount();
                    $imputed = min($paidLeft, $amount);
                    $paidLeft -= $imputed;
                    $remaining = round($amount - $imputed, 2);
                    $payable = $payActive && !$nextPayableSet && $remaining > 0;
                    if ($payable) {
                        $nextPayableSet = true;
                    }
                    $rows[] = [
                        'order' => $schedule->getOrderNumber() ?? ($i + 1),
                        'due' => $schedule->getDueDate(),
                        'amount' => $amount,
                        'paid' => round($imputed, 2),
                        'remaining' => $remaining,
                        'payable' => $payable,
                    ];
                }
            } else {
                // Frais sans échéancier : une échéance unique.
                $amount = (float) $studentFee->getAmount();
                $imputed = min($paidLeft, $amount);
                $remaining = round($amount - $imputed, 2);
                $rows[] = [
                    'order' => 1,
                    'due' => null,
                    'amount' => $amount,
                    'paid' => round($imputed, 2),
                    'remaining' => $remaining,
                    'payable' => $payActive && $remaining > 0,
                ];
            }

            $detail[] = [
                'student_fee_id' => $studentFee->getId(),
                'name' => (string) $fee->getName(),
                'category' => $fee->getCategoryLabel(),
                'total' => (float) $studentFee->getAmount(),
                'paid' => (float) $studentFee->getPaidAmount(),
                'remaining' => $studentFee->getRemainingAmount(),
                'schedules' => $rows,
            ];
        }

        return $detail;
    }

    private function getCurrentParent(): \App\Entity\User
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        return $user;
    }
}
