<?php

namespace App\Controller\Api;

use App\Entity\Course;
use App\Entity\Homework;
use App\Entity\Student;
use App\Entity\User;
use App\Repository\HomeworkRepository;
use App\Repository\StudentRepository;
use App\Service\MaintenanceMode;
use App\Service\MobileAuthService;
use App\Service\ParentPortalService;
use App\Service\PortalPayloads;
use App\Service\StudentAccountManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * API REST de l'application mobile « EDU-SCHOOL Élève » : pendant JSON de l'espace
 * élève web ({@see \App\Controller\Portal\StudentPortalController}), en lecture seule.
 *
 * Connexion comme sur le web ({@see \App\Security\StudentAuthenticator}) : matricule
 * national + date de naissance, le compte élève étant créé à la première connexion.
 * Ce second facteur étant faible, les tentatives sont doublement limitées (par IP +
 * matricule, et par matricule toutes IP confondues).
 *
 * L'élève est TOUJOURS résolu depuis le compte connecté (aucun identifiant d'élève
 * dans les URLs) et les devoirs sont bornés à sa classe : pas de surface IDOR.
 */
#[Route('/api/eleve')]
class StudentApiController extends AbstractController
{
    /** Jours de Course.dayOfWeek, indexés comme date('N'). */
    private const DAYS = [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi'];

    public function __construct(
        private readonly StudentRepository $studentRepository,
        private readonly ParentPortalService $portal,
        private readonly PortalPayloads $payloads,
        private readonly EntityManagerInterface $em,
    ) {
    }

    // ─── Authentification ────────────────────────────────────────────────

    #[Route('/login', name: 'api_student_login', methods: ['POST'])]
    public function login(
        Request $request,
        StudentAccountManager $accountManager,
        MobileAuthService $auth,
        MaintenanceMode $maintenance,
        RoleHierarchyInterface $roleHierarchy,
        RateLimiterFactory $studentApiLoginLimiter,
        RateLimiterFactory $studentApiMatriculeLimiter,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $matricule = trim((string) ($data['matricule'] ?? ''));
        $dateOfBirth = trim((string) ($data['dateOfBirth'] ?? ''));

        if ($matricule === '' || $dateOfBirth === '') {
            return $this->error('missing_credentials', 'Matricule et date de naissance requis.', Response::HTTP_BAD_REQUEST);
        }

        $byIp = $studentApiLoginLimiter->create($request->getClientIp() . '|' . mb_strtolower($matricule));
        $byMatricule = $studentApiMatriculeLimiter->create(mb_strtolower($matricule));
        if (!$byIp->consume()->isAccepted() || !$byMatricule->consume()->isAccepted()) {
            return $this->error('too_many_attempts', 'Trop de tentatives. Réessayez plus tard.', Response::HTTP_TOO_MANY_REQUESTS);
        }

        // Même contrôle que StudentAuthenticator : la date doit correspondre exactement.
        $student = $this->studentRepository->findOneActiveByMatriculeNational($matricule);
        if (!$student || !$student->getDateOfBirth() || $student->getDateOfBirth()->format('Y-m-d') !== $dateOfBirth) {
            return $this->error('invalid_credentials', 'Matricule ou date de naissance incorrect.', Response::HTTP_UNAUTHORIZED);
        }

        $user = $accountManager->ensureAccount($student);
        // Le compte doit être un compte élève actif : jamais de jeton pour un autre type
        // de compte qui porterait ce matricule comme identifiant.
        if (!$user || !$user->isActive()
            || !in_array('ROLE_ELEVE', $roleHierarchy->getReachableRoleNames($user->getRoles()), true)) {
            return $this->error('forbidden', "Impossible d'accéder à l'espace élève. Contactez le secrétariat.", Response::HTTP_FORBIDDEN);
        }

        if ($maintenance->isEnabled() && !$maintenance->canBypass($user)) {
            return $this->error('maintenance', "L'application est en maintenance. Veuillez réessayer plus tard.", Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $byIp->reset();
        $byMatricule->reset();

        return new JsonResponse([
            'token' => $auth->issueToken($user),
            'user' => ['id' => $user->getId(), 'fullName' => $student->getFullName(), 'firstName' => $student->getFirstName()],
        ]);
    }

    #[Route('/logout', name: 'api_student_logout', methods: ['POST'])]
    #[IsGranted('ROLE_ELEVE')]
    public function logout(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $user->setApiToken(null);
        $this->em->flush();

        return new JsonResponse(['ok' => true]);
    }

    // ─── Consultation ────────────────────────────────────────────────────

    /**
     * Tableau de bord : fiche, période courante (moyenne, absences), cours du jour
     * et devoirs à venir.
     */
    #[Route('/me', name: 'api_student_me', methods: ['GET'])]
    #[IsGranted('ROLE_ELEVE')]
    public function me(Request $request, HomeworkRepository $homeworkRepository): JsonResponse
    {
        $student = $this->getStudent();
        $period = $this->portal->getCurrentPeriod($student);
        $academic = $this->portal->getAcademicReport($student, $period);
        $classroom = $student->getClassroom();

        $today = self::DAYS[(int) date('N')] ?? null;
        $todayCourses = [];
        foreach ($this->payloads->schedule($student)['days'] as $day) {
            if ($day['day'] === $today) {
                $todayCourses = $day['courses'];
            }
        }

        return new JsonResponse([
            'student' => $this->payloads->student($student, $request),
            'period' => $this->payloads->period($period),
            'generalAverage' => $this->payloads->num($academic['general_average']),
            'gradeCount' => $academic['grade_count'],
            'attendance' => $this->payloads->attendanceTotals($this->portal->getAttendanceReport($student, $period)),
            'today' => $today,
            'todayCourses' => $todayCourses,
            'upcomingHomeworks' => array_map(
                fn (Homework $h) => $this->homeworkPayload($h),
                $classroom ? $homeworkRepository->findUpcomingByClassroom($classroom->getId()) : [],
            ),
        ]);
    }

    #[Route('/grades', name: 'api_student_grades', methods: ['GET'])]
    #[IsGranted('ROLE_ELEVE')]
    public function grades(Request $request): JsonResponse
    {
        $student = $this->getStudent();

        return new JsonResponse($this->payloads->grades($student, $this->payloads->resolvePeriod($student, $request)));
    }

    #[Route('/absences', name: 'api_student_absences', methods: ['GET'])]
    #[IsGranted('ROLE_ELEVE')]
    public function absences(Request $request): JsonResponse
    {
        $student = $this->getStudent();

        return new JsonResponse($this->payloads->absences($student, $this->payloads->resolvePeriod($student, $request)));
    }

    #[Route('/schedule', name: 'api_student_schedule', methods: ['GET'])]
    #[IsGranted('ROLE_ELEVE')]
    public function schedule(): JsonResponse
    {
        return new JsonResponse($this->payloads->schedule($this->getStudent()));
    }

    #[Route('/homeworks', name: 'api_student_homeworks', methods: ['GET'])]
    #[IsGranted('ROLE_ELEVE')]
    public function homeworks(HomeworkRepository $homeworkRepository): JsonResponse
    {
        $classroom = $this->getStudent()->getClassroom();

        return new JsonResponse([
            'classroom' => $classroom?->getName(),
            'homeworks' => array_map(
                fn (Homework $h) => $this->homeworkPayload($h),
                $classroom ? $homeworkRepository->findByClassroom($classroom->getId()) : [],
            ),
        ]);
    }

    /**
     * Pièce jointe d'un devoir de SA classe (téléchargée par l'appli avec le jeton).
     */
    #[Route('/homeworks/{id}/attachment', name: 'api_student_homework_attachment', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_ELEVE')]
    public function attachment(int $id, HomeworkRepository $homeworkRepository): Response
    {
        $classroom = $this->getStudent()->getClassroom();
        $homework = $homeworkRepository->find($id);

        if (!$homework || !$classroom || $homework->getClassroom()?->getId() !== $classroom->getId() || !$homework->hasAttachment()) {
            return $this->error('not_found', 'Pièce jointe introuvable.', Response::HTTP_NOT_FOUND);
        }

        $path = $this->getParameter('kernel.project_dir') . '/public/' . ltrim((string) $homework->getAttachmentPath(), '/');
        if (!is_file($path)) {
            return $this->error('not_found', 'Pièce jointe introuvable.', Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $response->getFile()->getFilename());

        return $response;
    }

    // ─── Aides ───────────────────────────────────────────────────────────

    private function homeworkPayload(Homework $homework): array
    {
        $path = (string) $homework->getAttachmentPath();

        return [
            'id' => $homework->getId(),
            'title' => $homework->getTitle(),
            'instructions' => $homework->getInstructions(),
            'dueDate' => $homework->getDueDate()?->format('Y-m-d'),
            'subject' => $homework->getSubject()?->getName(),
            'teacher' => $homework->getTeacher()?->getFullName(),
            'hasAttachment' => $homework->hasAttachment(),
            'attachmentName' => $homework->hasAttachment() ? basename($path) : null,
            'upcoming' => $homework->isUpcoming(),
            'createdAt' => $homework->getCreatedAt()?->format(\DATE_ATOM),
        ];
    }

    /**
     * L'élève rattaché au compte connecté ; 404 si le compte n'est lié à aucune fiche.
     */
    private function getStudent(): Student
    {
        $student = $this->studentRepository->findOneBy(['studentUser' => $this->getUser()]);
        if (!$student instanceof Student) {
            throw $this->createNotFoundException('Aucune fiche élève rattachée à ce compte.');
        }

        return $student;
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $code, 'message' => $message], $status);
    }
}
