<?php

namespace App\Controller\Api;

use App\Entity\Notification;
use App\Entity\OnlinePayment;
use App\Entity\Payment;
use App\Entity\Student;
use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Repository\OnlinePaymentRepository;
use App\Repository\PaymentRepository;
use App\Repository\StudentFeeRepository;
use App\Repository\StudentRepository;
use App\Repository\UserRepository;
use App\Security\Voter\ChildVoter;
use App\Service\GeniusPay\GeniusPayException;
use App\Service\GeniusPay\GeniusPayService;
use App\Service\MobileAuthException;
use App\Service\MobileAuthService;
use App\Service\ParentPortalService;
use App\Service\ParentRegistrationException;
use App\Service\ParentRegistrationService;
use App\Service\PortalPayloads;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * API REST de l'application mobile « EDU-SCHOOL Parent » : pendant JSON du
 * Portail Parent web ({@see \App\Controller\Portal\ParentPortalController}).
 *
 * Mêmes règles que le portail web :
 *  - ROLE_PARENT exigé (access_control ^/api/parent + IsGranted sur chaque action) ;
 *  - chaque action ciblant un élève vérifie ChildVoter::VIEW (protection IDOR) ;
 *  - les agrégations viennent de ParentPortalService (aucune logique dupliquée).
 *
 * Authentification par jeton porteur ({@see \App\Security\ApiTokenAuthenticator}),
 * émis par POST /api/parent/login aux seuls comptes parents.
 */
#[Route('/api/parent')]
class ParentApiController extends AbstractController
{
    /** Schéma de lien profond de l'application (cf. app.json « scheme »). */
    private const APP_SCHEME = 'eduschoolparent';

    public function __construct(
        private readonly ParentPortalService $portal,
        private readonly EntityManagerInterface $em,
        private readonly PortalPayloads $payloads,
        private readonly MobileAuthService $auth,
        private readonly RateLimiterFactory $parentApiLoginLimiter,
    ) {
    }

    // ─── Authentification ────────────────────────────────────────────────

    #[Route('/login', name: 'api_parent_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $user = $this->checkCredentials($request, (string) ($data['username'] ?? ''), (string) ($data['password'] ?? ''));
        } catch (MobileAuthException $e) {
            return $this->authError($e);
        }

        // Mot de passe provisoire (création / réinitialisation) : l'application
        // enchaîne sur POST /password/first-login pour le faire remplacer.
        if ($user->isMustChangePassword()) {
            return $this->error('password_change_required', 'Pour votre sécurité, choisissez un nouveau mot de passe avant votre première connexion.', Response::HTTP_FORBIDDEN);
        }

        return $this->tokenResponse($user);
    }

    /**
     * Auto-inscription d'un parent (pendant de /parent/inscription) : crée le compte
     * relié à l'enfant désigné par son matricule national, puis ouvre la session.
     */
    #[Route('/register', name: 'api_parent_register', methods: ['POST'])]
    public function register(Request $request, ParentRegistrationService $registration, RateLimiterFactory $parentApiRegisterLimiter): JsonResponse
    {
        if (!$parentApiRegisterLimiter->create((string) $request->getClientIp())->consume()->isAccepted()) {
            return $this->error('too_many_attempts', 'Trop de tentatives d\'inscription. Réessayez plus tard.', Response::HTTP_TOO_MANY_REQUESTS);
        }

        $data = json_decode($request->getContent(), true);

        try {
            $result = $registration->register(
                (string) ($data['firstName'] ?? ''),
                (string) ($data['lastName'] ?? ''),
                (string) ($data['phone'] ?? ''),
                (string) ($data['password'] ?? ''),
                (string) ($data['matriculeNational'] ?? ''),
            );
        } catch (ParentRegistrationException $e) {
            return $this->error('registration_refused', $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $response = $this->tokenResponse($result['parent']);
        $response->setStatusCode(Response::HTTP_CREATED);

        return $response;
    }

    /**
     * Premier login avec un mot de passe provisoire : le parent s'authentifie avec
     * ce mot de passe et en choisit un nouveau, dans la même requête (pendant de
     * la page web /change-password imposée par ForcePasswordChangeSubscriber).
     */
    #[Route('/password/first-login', name: 'api_parent_password_first_login', methods: ['POST'])]
    public function firstLoginPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $user = $this->checkCredentials($request, (string) ($data['username'] ?? ''), (string) ($data['currentPassword'] ?? ''));

            if (!$user->isMustChangePassword()) {
                return $this->error('not_required', "Aucun changement de mot de passe n'est requis : connectez-vous normalement.", Response::HTTP_BAD_REQUEST);
            }

            $this->auth->applyNewPassword($user, (string) ($data['newPassword'] ?? ''));
        } catch (MobileAuthException $e) {
            return $this->authError($e);
        }

        return $this->tokenResponse($user);
    }

    /**
     * Changement de mot de passe à la demande, depuis le profil de l'application.
     */
    #[Route('/password', name: 'api_parent_password', methods: ['POST'])]
    #[IsGranted('ROLE_PARENT')]
    public function changePassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $this->auth->changePassword($this->getParent(), (string) ($data['currentPassword'] ?? ''), (string) ($data['newPassword'] ?? ''));
        } catch (MobileAuthException $e) {
            return $this->authError($e);
        }

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/logout', name: 'api_parent_logout', methods: ['POST'])]
    #[IsGranted('ROLE_PARENT')]
    public function logout(): JsonResponse
    {
        $this->getParent()->setApiToken(null);
        $this->em->flush();

        return new JsonResponse(['ok' => true]);
    }

    /**
     * @throws MobileAuthException
     */
    private function checkCredentials(Request $request, string $username, string $password): User
    {
        return $this->auth->checkCredentials(
            $request,
            $username,
            $password,
            'ROLE_PARENT',
            "Cette application est réservée aux parents d'élèves.",
            $this->parentApiLoginLimiter,
        );
    }

    private function tokenResponse(User $user): JsonResponse
    {
        return new JsonResponse(['token' => $this->auth->issueToken($user), 'user' => $this->userPayload($user)]);
    }

    private function authError(MobileAuthException $e): JsonResponse
    {
        return $this->error($e->getErrorCode(), $e->getMessage(), $e->getStatus());
    }

    // ─── Tableau de bord ─────────────────────────────────────────────────

    #[Route('/me', name: 'api_parent_me', methods: ['GET'])]
    #[IsGranted('ROLE_PARENT')]
    public function me(Request $request, NotificationRepository $notifications): JsonResponse
    {
        $parent = $this->getParent();

        $children = [];
        foreach ($this->portal->getDashboard($parent) as $card) {
            $children[] = $this->payloads->student($card['child'], $request) + [
                'period' => $card['period']?->getName(),
                'generalAverage' => $this->payloads->num($card['general_average']),
                'absences' => $card['absences'],
                'remaining' => (float) $card['remaining'],
            ];
        }

        return new JsonResponse([
            'user' => $this->userPayload($parent),
            'children' => $children,
            'unreadNotifications' => $notifications->countUnreadForUser($parent),
        ]);
    }

    /**
     * Rattachement d'un enfant par son matricule national (mêmes règles que le web).
     */
    #[Route('/children/link', name: 'api_parent_link_child', methods: ['POST'])]
    #[IsGranted('ROLE_PARENT')]
    public function linkChild(Request $request, StudentRepository $studentRepository, UserRepository $userRepository): JsonResponse
    {
        $parent = $this->getParent();
        $data = json_decode($request->getContent(), true);
        $matricule = trim((string) ($data['matriculeNational'] ?? ''));

        if ($matricule === '') {
            return $this->error('missing_matricule', "Veuillez renseigner le matricule national de l'élève.", Response::HTTP_BAD_REQUEST);
        }

        $child = $studentRepository->findOneActiveByMatriculeNational($matricule);
        if (!$child) {
            return $this->error('not_found', 'Aucun élève actif ne correspond à ce matricule national. Vérifiez le matricule auprès du secrétariat.', Response::HTTP_NOT_FOUND);
        }

        if ($this->isGranted(ChildVoter::VIEW, $child)) {
            return new JsonResponse(['child' => $this->payloads->student($child, $request), 'alreadyLinked' => true]);
        }

        if ($userRepository->findParentOfStudent($child) !== null) {
            return $this->error('already_linked', "Cet élève est déjà rattaché à un autre parent. Si vous pensez qu'il s'agit d'une erreur, contactez le secrétariat.", Response::HTTP_CONFLICT);
        }

        $parent->addChild($child);
        if ($school = $child->getSchool() ?? $child->getClassroom()?->getSchool() ?? $child->getLevel()?->getSchool()) {
            $parent->addSchool($school);
        }
        $this->em->flush();

        return new JsonResponse(['child' => $this->payloads->student($child, $request), 'alreadyLinked' => false], Response::HTTP_CREATED);
    }

    // ─── Fiche enfant ────────────────────────────────────────────────────

    #[Route('/children/{id}', name: 'api_parent_child', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_PARENT')]
    public function child(Student $child, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ChildVoter::VIEW, $child);

        $period = $this->portal->getCurrentPeriod($child);
        $academic = $this->portal->getAcademicReport($child, $period);
        $attendance = $this->portal->getAttendanceReport($child, $period);
        $finance = $this->portal->getFinancialReport($child);

        return new JsonResponse([
            'child' => $this->payloads->student($child, $request),
            'period' => $this->payloads->period($period),
            'generalAverage' => $this->payloads->num($academic['general_average']),
            'gradeCount' => $academic['grade_count'],
            'attendance' => $this->payloads->attendanceTotals($attendance),
            'finance' => $this->financeTotals($finance),
        ]);
    }

    #[Route('/children/{id}/grades', name: 'api_parent_child_grades', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_PARENT')]
    public function grades(Student $child, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ChildVoter::VIEW, $child);

        return new JsonResponse($this->payloads->grades($child, $this->payloads->resolvePeriod($child, $request)));
    }

    #[Route('/children/{id}/absences', name: 'api_parent_child_absences', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_PARENT')]
    public function absences(Student $child, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ChildVoter::VIEW, $child);

        return new JsonResponse($this->payloads->absences($child, $this->payloads->resolvePeriod($child, $request)));
    }

    #[Route('/children/{id}/schedule', name: 'api_parent_child_schedule', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_PARENT')]
    public function schedule(Student $child): JsonResponse
    {
        $this->denyAccessUnlessGranted(ChildVoter::VIEW, $child);

        return new JsonResponse($this->payloads->schedule($child));
    }

    // ─── Finances & paiement en ligne ────────────────────────────────────

    #[Route('/children/{id}/finance', name: 'api_parent_child_finance', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_PARENT')]
    public function finance(
        Student $child,
        PaymentRepository $paymentRepository,
        OnlinePaymentRepository $onlinePaymentRepository,
        GeniusPayService $geniusPay,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(ChildVoter::VIEW, $child);

        $fees = [];
        foreach ($this->portal->getFeeScheduleDetail($child) as $fee) {
            $fees[] = [
                'studentFeeId' => $fee['student_fee_id'],
                'name' => $fee['name'],
                'category' => $fee['category'],
                'total' => $fee['total'],
                'paid' => $fee['paid'],
                'remaining' => $fee['remaining'],
                'schedules' => array_map(static fn (array $s) => [
                    'order' => $s['order'],
                    'due' => $s['due']?->format('Y-m-d'),
                    'amount' => $s['amount'],
                    'paid' => $s['paid'],
                    'remaining' => $s['remaining'],
                    'payable' => $s['payable'],
                ], $fee['schedules']),
            ];
        }

        $pending = array_values(array_filter(
            $onlinePaymentRepository->findByStudent($child),
            static fn (OnlinePayment $op) => $op->isPending(),
        ));

        return new JsonResponse([
            'totals' => $this->financeTotals($this->portal->getFinancialReport($child)),
            'fees' => $fees,
            'payments' => array_map(fn (Payment $p) => $this->paymentPayload($p), $paymentRepository->findByStudent($child)),
            'pendingOnlinePayments' => array_map(fn (OnlinePayment $op) => $this->onlinePaymentPayload($op), $pending),
            'onlinePaymentAvailable' => $geniusPay->isAvailableFor($child->getSchool()),
        ]);
    }

    /**
     * Prépare un paiement GeniusPay et renvoie l'URL de checkout, que l'application
     * ouvre dans un navigateur intégré. Comme sur le web, l'encaissement n'est
     * enregistré qu'à la confirmation (webhook ou {@see verifyPayment()}).
     */
    #[Route('/children/{id}/pay', name: 'api_parent_child_pay', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_PARENT')]
    public function pay(Student $child, Request $request, StudentFeeRepository $studentFeeRepository, GeniusPayService $geniusPay): JsonResponse
    {
        $this->denyAccessUnlessGranted(ChildVoter::VIEW, $child);

        if (!$geniusPay->isAvailableFor($child->getSchool())) {
            return $this->error('payment_unavailable', "Le paiement en ligne n'est pas disponible pour cet établissement.", Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $data = json_decode($request->getContent(), true);
        $studentFee = $studentFeeRepository->find((int) ($data['studentFeeId'] ?? 0));
        if (!$studentFee || $studentFee->getStudent()?->getId() !== $child->getId() || !$studentFee->getFee()?->isActive()) {
            return $this->error('invalid_fee', 'Frais invalide pour cet élève.', Response::HTTP_BAD_REQUEST);
        }

        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0 || $amount > $studentFee->getRemainingAmount() + 0.009) {
            return $this->error('invalid_amount', 'Le montant doit être supérieur à 0 et ne pas dépasser le reste dû.', Response::HTTP_BAD_REQUEST);
        }

        $returnUrl = $this->generateUrl('api_parent_payment_return', [], UrlGeneratorInterface::ABSOLUTE_URL);

        try {
            $onlinePayment = $geniusPay->initiate(
                student: $child,
                studentFee: $studentFee,
                amount: $amount,
                initiatedBy: $this->getParent(),
                successUrl: $returnUrl,
                errorUrl: $returnUrl . '?echec=1',
            );
        } catch (GeniusPayException $e) {
            return $this->error('gateway_error', $e->getMessage(), Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse([
            'reference' => $onlinePayment->getReference(),
            'checkoutUrl' => $onlinePayment->getCheckoutUrl(),
            'returnUrlPrefix' => self::APP_SCHEME . '://',
        ]);
    }

    /**
     * Vérifie l'état d'un paiement auprès de la passerelle au retour du parent
     * (le webhook peut arriver plus tard ou se perdre). `confirm()` est idempotent.
     */
    #[Route('/payments/{reference}/verify', name: 'api_parent_payment_verify', methods: ['POST'])]
    #[IsGranted('ROLE_PARENT')]
    public function verifyPayment(string $reference, OnlinePaymentRepository $onlinePayments, GeniusPayService $geniusPay): JsonResponse
    {
        $onlinePayment = $onlinePayments->findOneByReference($reference);
        if ($onlinePayment === null) {
            return $this->error('not_found', 'Transaction introuvable.', Response::HTTP_NOT_FOUND);
        }
        $this->denyAccessUnlessGranted(ChildVoter::VIEW, $onlinePayment->getStudent());

        if (!$onlinePayment->isCompleted()) {
            try {
                $geniusPay->confirm($reference, null, 'retour_parent_mobile');
            } catch (\Throwable) {
                // Le webhook reste le filet de sécurité : on renvoie l'état connu.
            }
            $this->em->refresh($onlinePayment);
        }

        return new JsonResponse($this->onlinePaymentPayload($onlinePayment));
    }

    /**
     * Page de retour de GeniusPay (publique, cf. access_control) : renvoie le
     * parent dans l'application via le lien profond, ce qui ferme le navigateur.
     */
    #[Route('/paiement/retour', name: 'api_parent_payment_return', methods: ['GET'])]
    public function paymentReturn(Request $request): Response
    {
        $deepLink = self::APP_SCHEME . '://paiement' . ($request->query->getBoolean('echec') ? '?echec=1' : '');
        $href = htmlspecialchars($deepLink, ENT_QUOTES);

        return new Response(<<<HTML
            <!doctype html><html lang="fr"><head><meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Retour à l'application</title>
            <meta http-equiv="refresh" content="0;url={$href}"></head>
            <body style="font-family:sans-serif;text-align:center;padding:48px 16px">
            <p>Paiement terminé. Retour à l'application EDU-SCHOOL Parent…</p>
            <p><a href="{$href}">Revenir à l'application</a></p></body></html>
            HTML);
    }

    /**
     * Historique de tous les paiements des enfants + tentatives en ligne non abouties.
     */
    #[Route('/payments', name: 'api_parent_payments', methods: ['GET'])]
    #[IsGranted('ROLE_PARENT')]
    public function payments(PaymentRepository $paymentRepository, OnlinePaymentRepository $onlinePaymentRepository): JsonResponse
    {
        $children = $this->portal->getChildren($this->getParent());
        $studentIds = array_map(static fn (Student $c) => $c->getId(), $children);

        $unresolved = [];
        foreach ($children as $c) {
            foreach ($onlinePaymentRepository->findByStudent($c) as $op) {
                if (!$op->isCompleted()) {
                    $unresolved[] = $this->onlinePaymentPayload($op);
                }
            }
        }

        return new JsonResponse([
            'payments' => array_map(fn (Payment $p) => $this->paymentPayload($p), $paymentRepository->findByStudentIds($studentIds)),
            'unresolvedOnline' => $unresolved,
        ]);
    }

    // ─── Notifications ───────────────────────────────────────────────────

    #[Route('/notifications', name: 'api_parent_notifications', methods: ['GET'])]
    #[IsGranted('ROLE_PARENT')]
    public function notifications(NotificationRepository $notificationRepository): JsonResponse
    {
        return new JsonResponse([
            'notifications' => array_map(static fn (Notification $n) => [
                'id' => $n->getId(),
                'title' => $n->getTitle(),
                'message' => $n->getMessage(),
                'icon' => $n->getIcon(),
                'isRead' => $n->isRead(),
                'createdAt' => $n->getCreatedAt()?->format(\DATE_ATOM),
            ], $notificationRepository->findForUser($this->getParent())),
        ]);
    }

    #[Route('/notifications/{id}/read', name: 'api_parent_notification_read', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_PARENT')]
    public function readNotification(Notification $notification): JsonResponse
    {
        if ($notification->getRecipient()?->getId() !== $this->getParent()->getId()) {
            return $this->error('not_found', 'Notification introuvable.', Response::HTTP_NOT_FOUND);
        }

        $notification->setIsRead(true);
        $this->em->flush();

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/notifications/read-all', name: 'api_parent_notifications_read_all', methods: ['POST'])]
    #[IsGranted('ROLE_PARENT')]
    public function readAllNotifications(NotificationRepository $notificationRepository): JsonResponse
    {
        foreach ($notificationRepository->findForUser($this->getParent()) as $notification) {
            $notification->setIsRead(true);
        }
        $this->em->flush();

        return new JsonResponse(['ok' => true]);
    }

    // ─── Sérialisation ───────────────────────────────────────────────────

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->getId(),
            'fullName' => $user->getFullName(),
            'firstName' => $user->getFirstName(),
            'email' => $user->getEmail(),
            'phone' => $user->getPhone(),
        ];
    }

    private function paymentPayload(Payment $payment): array
    {
        return [
            'id' => $payment->getId(),
            'number' => $payment->getPaymentNumber(),
            'receiptNumber' => $payment->getReceiptNumber(),
            'student' => $payment->getStudent()?->getFullName(),
            'studentId' => $payment->getStudent()?->getId(),
            'fee' => $payment->getFee()?->getName(),
            'amount' => (float) $payment->getAmount(),
            'date' => $payment->getPaymentDate()?->format('Y-m-d'),
            'method' => $payment->getPaymentMethodLabel(),
            'status' => $payment->getStatus(),
            'statusLabel' => $payment->getStatusLabel(),
        ];
    }

    private function onlinePaymentPayload(OnlinePayment $op): array
    {
        return [
            'reference' => $op->getReference(),
            'student' => $op->getStudent()?->getFullName(),
            'fee' => $op->getStudentFee()?->getFee()?->getName(),
            'amount' => (float) $op->getAmount(),
            'status' => $op->getStatus(),
            'statusLabel' => $op->getStatusLabel(),
            'completed' => $op->isCompleted(),
            'pending' => $op->isPending(),
            'failureReason' => $op->getFailureReason(),
            'createdAt' => $op->getCreatedAt()?->format(\DATE_ATOM),
        ];
    }

    private function financeTotals(array $finance): array
    {
        return [
            'totalTuition' => (float) $finance['total_tuition'],
            'totalPaid' => (float) $finance['total_paid'],
            'remaining' => (float) $finance['remaining'],
        ];
    }

    private function getParent(): User
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $code, 'message' => $message], $status);
    }
}
