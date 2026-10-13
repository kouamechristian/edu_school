<?php

namespace App\Controller\Api;

use App\Controller\Concern\HandlesFileUpload;
use App\Entity\Absence;
use App\Entity\AbsenceType;
use App\Entity\Classroom;
use App\Entity\Course;
use App\Entity\Evaluation;
use App\Entity\Grade;
use App\Entity\Homework;
use App\Entity\Period;
use App\Entity\Student;
use App\Entity\Subject;
use App\Entity\User;
use App\Repository\AbsenceRepository;
use App\Repository\AbsenceTypeRepository;
use App\Repository\EvaluationRepository;
use App\Repository\GradeRepository;
use App\Repository\HomeworkRepository;
use App\Repository\PeriodRepository;
use App\Repository\StudentRepository;
use App\Service\MobileAuthException;
use App\Service\MobileAuthService;
use App\Service\TeacherScope;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * API REST de l'application mobile « EDU-SCHOOL Enseignant » : pendant JSON de
 * l'espace enseignant web (emploi du temps, élèves, évaluations et notes,
 * exercices de maison), plus l'appel (absences / retards) de ses classes.
 *
 * Cloisonnement : toutes les classes, évaluations et devoirs sont vérifiés contre
 * le périmètre de l'enseignant ({@see TeacherScope}, déduit de ses cours) ; un
 * identifiant hors périmètre renvoie 404. Les identifiants sont reçus en entiers
 * (pas d'entités résolues) : les contrôles sont donc explicites ici.
 *
 * Authentification par jeton porteur ({@see \App\Security\ApiTokenAuthenticator}),
 * émis par POST /api/enseignant/login aux comptes ayant ROLE_ENSEIGNANT.
 */
#[Route('/api/enseignant')]
class TeacherApiController extends AbstractController
{
    use HandlesFileUpload;

    private const EVALUATION_TYPES = [
        'controle_continu' => 'Contrôle continu',
        'devoir_surveille' => 'Devoir surveillé',
        'devoir_maison' => 'Devoir maison',
        'examen' => 'Examen',
        'oral' => 'Oral',
        'pratique' => 'Pratique',
        'projet' => 'Projet',
    ];

    /** Jours de Course.dayOfWeek, indexés comme date('N'). */
    private const DAYS = [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MobileAuthService $auth,
        private readonly RateLimiterFactory $teacherApiLoginLimiter,
        private readonly TeacherScope $scope,
        private readonly StudentRepository $studentRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    // ─── Authentification ────────────────────────────────────────────────

    #[Route('/login', name: 'api_teacher_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $user = $this->checkCredentials($request, (string) ($data['username'] ?? ''), (string) ($data['password'] ?? ''));
        } catch (MobileAuthException $e) {
            return $this->authError($e);
        }

        if ($user->isMustChangePassword()) {
            return $this->error('password_change_required', 'Pour votre sécurité, choisissez un nouveau mot de passe avant votre première connexion.', Response::HTTP_FORBIDDEN);
        }

        return $this->tokenResponse($user);
    }

    #[Route('/password/first-login', name: 'api_teacher_password_first_login', methods: ['POST'])]
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

    #[Route('/password', name: 'api_teacher_password', methods: ['POST'])]
    #[IsGranted('ROLE_ENSEIGNANT')]
    public function changePassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $this->auth->changePassword($this->getTeacher(), (string) ($data['currentPassword'] ?? ''), (string) ($data['newPassword'] ?? ''));
        } catch (MobileAuthException $e) {
            return $this->authError($e);
        }

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/logout', name: 'api_teacher_logout', methods: ['POST'])]
    #[IsGranted('ROLE_ENSEIGNANT')]
    public function logout(): JsonResponse
    {
        $this->getTeacher()->setApiToken(null);
        $this->em->flush();

        return new JsonResponse(['ok' => true]);
    }

    // ─── Tableau de bord & emploi du temps ───────────────────────────────

    #[Route('/me', name: 'api_teacher_me', methods: ['GET'])]
    #[IsGranted('ROLE_ENSEIGNANT')]
    public function me(EvaluationRepository $evaluations, HomeworkRepository $homeworks): JsonResponse
    {
        $teacher = $this->getTeacher();
        $today = self::DAYS[(int) date('N')] ?? null;

        $todayCourses = array_values(array_filter(
            $this->scope->courses($teacher),
            static fn (Course $c) => $c->getDayOfWeek() === $today,
        ));
        usort($todayCourses, static fn (Course $a, Course $b) => $a->getStartTime() <=> $b->getStartTime());

        $studentCount = 0;
        foreach ($this->scope->classrooms($teacher) as $classroom) {
            $studentCount += count($this->studentRepository->findActiveByClassroom($classroom->getId()));
        }

        $toGrade = array_filter($evaluations->findByTeacher($teacher->getId()), static fn (Evaluation $e) => $e->isEditable());

        return new JsonResponse([
            'user' => $this->userPayload($teacher),
            'today' => $today,
            'todayCourses' => array_map(fn (Course $c) => $this->coursePayload($c), $todayCourses),
            'stats' => [
                'classrooms' => count($this->scope->classrooms($teacher)),
                'students' => $studentCount,
                'courses' => count($this->scope->courses($teacher)),
                'evaluationsToGrade' => count($toGrade),
            ],
            'recentHomeworks' => array_map(
                fn (Homework $h) => $this->homeworkPayload($h),
                \array_slice($homeworks->findByTeacher($teacher->getId()), 0, 3),
            ),
        ]);
    }

    #[Route('/schedule', name: 'api_teacher_schedule', methods: ['GET'])]
    #[IsGranted('ROLE_ENSEIGNANT')]
    public function schedule(): JsonResponse
    {
        $days = array_fill_keys(array_values(self::DAYS), []);
        foreach ($this->scope->courses($this->getTeacher()) as $course) {
            if (isset($days[$course->getDayOfWeek()])) {
                $days[$course->getDayOfWeek()][] = $course;
            }
        }

        $result = [];
        foreach ($days as $day => $courses) {
            usort($courses, static fn (Course $a, Course $b) => $a->getStartTime() <=> $b->getStartTime());
            $result[] = ['day' => $day, 'courses' => array_map(fn (Course $c) => $this->coursePayload($c), $courses)];
        }

        return new JsonResponse(['days' => $result]);
    }

    // ─── Classes & élèves ────────────────────────────────────────────────

    #[Route('/classrooms', name: 'api_teacher_classrooms', methods: ['GET'])]
    #[IsGranted('ROLE_ENSEIGNANT')]
    public function classrooms(): JsonResponse
    {
        $teacher = $this->getTeacher();

        $subjectsByClassroom = [];
        foreach ($this->scope->courses($teacher) as $course) {
            if ($course->getClassroom() && $course->getSubject()) {
                $subjectsByClassroom[$course->getClassroom()->getId()][$course->getSubject()->getId()] = $course->getSubject()->getName();
            }
        }

        return new JsonResponse([
            'classrooms' => array_map(fn (Classroom $c) => $this->classroomPayload($c) + [
                'studentCount' => count($this->studentRepository->findActiveByClassroom($c->getId())),
                'subjects' => array_values($subjectsByClassroom[$c->getId()] ?? []),
            ], $this->scope->classrooms($teacher)),
        ]);
    }

    #[Route('/classrooms/{id}/students', name: 'api_teacher_classroom_students', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_ENSEIGNANT')]
    public function students(int $id, Request $request): JsonResponse
    {
        $classroom = $this->scope->classroom($this->getTeacher(), $id);
        if (!$classroom) {
            return $this->notFound('Classe introuvable.');
        }

        return new JsonResponse([
            'classroom' => $this->classroomPayload($classroom),
            'students' => array_map(
                fn (Student $s) => $this->studentPayload($s, $request),
                $this->studentRepository->findActiveByClassroom($classroom->getId()),
            ),
        ]);
    }

    // ─── Évaluations & notes ─────────────────────────────────────────────

    #[Route('/evaluations', name: 'api_teacher_evaluations', methods: ['GET'])]
    #[IsGranted('ROLE_ENSEIGNANT')]
    public function evaluations(Request $request, EvaluationRepository $evaluationRepository, GradeRepository $gradeRepository): JsonResponse
    {
        $classroomId = $request->query->getInt('classroom');
        $periodId = $request->query->getInt('period');

        $evaluations = array_filter(
            $evaluationRepository->findByTeacher($this->getTeacher()->getId()),
            static fn (Evaluation $e) => (!$classroomId || $e->getClassroom()?->getId() === $classroomId)
                && (!$periodId || $e->getPeriod()?->getId() === $periodId),
        );

        return new JsonResponse([
            'evaluations' => array_map(fn (Evaluation $e) => $this->evaluationPayload($e) + [
                'gradeCount' => (int) $gradeRepository->getEvaluationStatistics($e->getId())['count'],
            ], array_values($evaluations)),
        ]);
    }

    /**
     * Données du formulaire de création : pour chaque classe de l'enseignant, ses
     * périodes (établissement + année de la classe) et les matières autorisées.
     */
    #[Route('/evaluations/options', name: 'api_teacher_evaluation_options', methods: ['GET'])]
    #[IsGranted('ROLE_ENSEIGNANT')]
    public function evaluationOptions(PeriodRepository $periodRepository): JsonResponse
    {
        $teacher = $this->getTeacher();
        $subjects = $this->scope->subjects($teacher, true);

        $classrooms = [];
        foreach ($this->scope->classrooms($teacher) as $classroom) {
            $classrooms[] = $this->classroomPayload($classroom) + [
                'periods' => array_map(
                    fn (Period $p) => $this->periodPayload($p),
                    $periodRepository->findBySchoolAndYear($classroom->getSchool()?->getId(), $classroom->getSchoolYear()?->getId()),
                ),
                'subjects' => array_values(array_map(
                    static fn (Subject $s) => ['id' => $s->getId(), 'name' => $s->getName()],
                    array_filter($subjects, fn (Subject $s) => $this->subjectFitsClassroom($s, $classroom)),
                )),
            ];
        }

        $types = [];
        foreach (self::EVALUATION_TYPES as $value => $label) {
            $types[] = ['value' => $value, 'label' => $label];
        }

        return new JsonResponse(['classrooms' => $classrooms, 'types' => $types]);
    }

    #[Route('/evaluations', name: 'api_teacher_evaluation_create', methods: ['POST'])]
    #[IsGranted('ROLE_ENSEIGNANT')]
    public function createEvaluation(Request $request, PeriodRepository $periodRepository): JsonResponse
    {
        $teacher = $this->getTeacher();
        $data = json_decode($request->getContent(), true) ?? [];

        $classroom = $this->scope->classroom($teacher, (int) ($data['classroomId'] ?? 0));
        if (!$classroom) {
            return $this->error('invalid_classroom', "Choisissez l'une de vos classes.", Response::HTTP_BAD_REQUEST);
        }

        $subject = null;
        foreach ($this->scope->subjects($teacher, true) as $s) {
            if ($s->getId() === (int) ($data['subjectId'] ?? 0) && $this->subjectFitsClassroom($s, $classroom)) {
                $subject = $s;
            }
        }
        if (!$subject) {
            return $this->error('invalid_subject', "Choisissez l'une de vos matières pour cette classe.", Response::HTTP_BAD_REQUEST);
        }

        $period = null;
        foreach ($periodRepository->findBySchoolAndYear($classroom->getSchool()?->getId(), $classroom->getSchoolYear()?->getId()) as $p) {
            if ($p->getId() === (int) ($data['periodId'] ?? 0)) {
                $period = $p;
            }
        }
        if (!$period) {
            return $this->error('invalid_period', 'Choisissez une période de la classe.', Response::HTTP_BAD_REQUEST);
        }

        $date = \DateTime::createFromFormat('!Y-m-d', (string) ($data['date'] ?? ''));
        if (!$date) {
            return $this->error('invalid_date', "La date de l'évaluation est obligatoire.", Response::HTTP_BAD_REQUEST);
        }

        if (!isset(self::EVALUATION_TYPES[(string) ($data['type'] ?? '')])) {
            return $this->error('invalid_type', "Choisissez un type d'évaluation.", Response::HTTP_BAD_REQUEST);
        }

        $maxGrade = str_replace(',', '.', (string) ($data['maxGrade'] ?? '20'));
        $coefficient = str_replace(',', '.', (string) ($data['coefficient'] ?? '1'));
        if (!is_numeric($maxGrade) || (float) $maxGrade <= 0 || !is_numeric($coefficient) || (float) $coefficient <= 0) {
            return $this->error('invalid_numbers', 'La note maximale et le coefficient doivent être des nombres positifs.', Response::HTTP_BAD_REQUEST);
        }

        $evaluation = (new Evaluation())
            ->setClassroom($classroom)
            ->setSubject($subject)
            ->setPeriod($period)
            ->setTeacher($teacher)
            ->setName(trim((string) ($data['name'] ?? '')))
            ->setType((string) ($data['type'] ?? ''))
            ->setDate($date)
            ->setMaxGrade($maxGrade)
            ->setCoefficient($coefficient)
            ->setDescription(trim((string) ($data['description'] ?? '')) ?: null);

        if ($error = $this->firstViolation($evaluation)) {
            return $this->error('invalid_evaluation', $error, Response::HTTP_BAD_REQUEST);
        }

        $this->em->persist($evaluation);
        $this->em->flush();

        return new JsonResponse($this->evaluationPayload($evaluation), Response::HTTP_CREATED);
    }

    #[Route('/evaluations/{id}', name: 'api_teacher_evaluation', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_ENSEIGNANT')]
    public function evaluation(int $id, Request $request, GradeRepository $gradeRepository): JsonResponse
    {
        $evaluation = $this->ownEvaluation($id);
        if (!$evaluation) {
            return $this->notFound('Évaluation introuvable.');
        }

        $grades = [];
        foreach ($gradeRepository->findByEvaluation($evaluation->getId()) as $grade) {
            $grades[$grade->getStudent()?->getId()] = $grade;
        }

        $students = [];
        foreach ($this->studentRepository->findActiveByClassroom($evaluation->getClassroom()->getId()) as $student) {
            $grade = $grades[$student->getId()] ?? null;
            $students[] = $this->studentPayload($student, $request) + [
                'grade' => $grade?->getValue() !== null ? round((float) $grade->getValue(), 2) : null,
                'gradeStatus' => $grade?->getStatus(),
            ];
        }

        $stats = $gradeRepository->getEvaluationStatistics($evaluation->getId());

        return new JsonResponse($this->evaluationPayload($evaluation) + [
            'students' => $students,
            'statistics' => [
                'average' => $stats['average'] !== null ? (float) $stats['average'] : null,
                'min' => $stats['min'] !== null ? (float) $stats['min'] : null,
                'max' => $stats['max'] !== null ? (float) $stats['max'] : null,
                'count' => (int) $stats['count'],
            ],
        ]);
    }

    /**
     * Saisie des notes puis validation, comme le bouton « Valider » du web
     * (EvaluationController::grades) : une note vide n'écrase rien.
     */
    #[Route('/evaluations/{id}/grades', name: 'api_teacher_evaluation_grades', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ENSEIGNANT')]
    public function saveGrades(int $id, Request $request, GradeRepository $gradeRepository): JsonResponse
    {
        $evaluation = $this->ownEvaluation($id);
        if (!$evaluation) {
            return $this->notFound('Évaluation introuvable.');
        }

        if (!$evaluation->isEditable()) {
            return $this->error('not_editable', $evaluation->isLockedByBulletin()
                ? 'Ces notes sont verrouillées : un bulletin a déjà été généré pour cette évaluation.'
                : 'Cette évaluation est validée. Annulez la validation pour modifier les notes.', Response::HTTP_CONFLICT);
        }

        $data = json_decode($request->getContent(), true);
        $values = \is_array($data['grades'] ?? null) ? $data['grades'] : [];
        $max = (float) $evaluation->getMaxGrade();

        $existing = [];
        foreach ($gradeRepository->findByEvaluation($evaluation->getId()) as $grade) {
            $existing[$grade->getStudent()?->getId()] = $grade;
        }

        // Contrôle de toutes les valeurs avant d'écrire quoi que ce soit.
        $students = $this->studentRepository->findActiveByClassroom($evaluation->getClassroom()->getId());
        $toSave = [];
        foreach ($students as $student) {
            $raw = $values[(string) $student->getId()] ?? null;
            $raw = \is_string($raw) ? str_replace(',', '.', trim($raw)) : $raw;
            if ($raw === null || $raw === '') {
                continue;
            }
            if (!is_numeric($raw) || (float) $raw < 0 || (float) $raw > $max) {
                return $this->error('invalid_grade', sprintf(
                    'Note invalide pour %s : saisissez un nombre entre 0 et %s.',
                    $student->getFullName(),
                    rtrim(rtrim(number_format($max, 2, ',', ''), '0'), ','),
                ), Response::HTTP_BAD_REQUEST);
            }
            $toSave[] = [$student, (string) round((float) $raw, 2)];
        }

        foreach ($toSave as [$student, $value]) {
            $grade = $existing[$student->getId()] ?? null;
            if (!$grade) {
                $grade = (new Grade())->setEvaluation($evaluation)->setStudent($student);
                $this->em->persist($grade);
            }
            $grade->setEnteredBy($this->getTeacher());
            $grade->setValue($value);
            $grade->setStatus(null);
            $grade->setComment(null);
        }

        // Valider l'évaluation (verrouille les notes jusqu'à « Annuler la validation »).
        $evaluation->setIsValidated(true);
        $this->em->flush();

        return new JsonResponse(['ok' => true, 'saved' => \count($toSave)]);
    }

    #[Route('/evaluations/{id}/unvalidate', name: 'api_teacher_evaluation_unvalidate', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ENSEIGNANT')]
    public function unvalidate(int $id): JsonResponse
    {
        $evaluation = $this->ownEvaluation($id);
        if (!$evaluation) {
            return $this->notFound('Évaluation introuvable.');
        }
        if ($evaluation->isLockedByBulletin()) {
            return $this->error('locked', 'Modification impossible : un bulletin a déjà été généré pour cette évaluation.', Response::HTTP_CONFLICT);
        }

        $evaluation->setIsValidated(false);
        $this->em->flush();

        return new JsonResponse($this->evaluationPayload($evaluation));
    }

    /**
     * Publication : rend (ou retire) les notes visibles aux parents.
     */
    #[Route('/evaluations/{id}/publish', name: 'api_teacher_evaluation_publish', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ENSEIGNANT')]
    public function publish(int $id, Request $request): JsonResponse
    {
        $evaluation = $this->ownEvaluation($id);
        if (!$evaluation) {
            return $this->notFound('Évaluation introuvable.');
        }

        $data = json_decode($request->getContent(), true);
        $evaluation->setIsPublished((bool) ($data['published'] ?? !$evaluation->isPublished()));
        $this->em->flush();

        return new JsonResponse($this->evaluationPayload($evaluation));
    }

    // ─── Exercices de maison ─────────────────────────────────────────────

    #[Route('/homeworks', name: 'api_teacher_homeworks', methods: ['GET'])]
    #[IsGranted('ROLE_ENSEIGNANT')]
    public function homeworks(HomeworkRepository $homeworkRepository): JsonResponse
    {
        $teacher = $this->getTeacher();

        $classrooms = [];
        foreach ($this->scope->classrooms($teacher) as $classroom) {
            $classrooms[] = $this->classroomPayload($classroom);
        }

        return new JsonResponse([
            'homeworks' => array_map(fn (Homework $h) => $this->homeworkPayload($h), $homeworkRepository->findByTeacher($teacher->getId())),
            // Options du formulaire (règles de HomeworkType : classes et matières des cours).
            'classrooms' => $classrooms,
            'subjects' => array_map(static fn (Subject $s) => ['id' => $s->getId(), 'name' => $s->getName()], $this->scope->subjects($teacher)),
        ]);
    }

    /**
     * Publication d'un exercice (multipart : champs + pièce jointe « attachment »
     * facultative), mêmes règles que TeacherSpaceController / HomeworkType.
     */
    #[Route('/homeworks', name: 'api_teacher_homework_create', methods: ['POST'])]
    #[IsGranted('ROLE_ENSEIGNANT')]
    public function createHomework(Request $request, SluggerInterface $slugger): JsonResponse
    {
        $teacher = $this->getTeacher();

        $classroom = $this->scope->classroom($teacher, $request->request->getInt('classroomId'));
        if (!$classroom) {
            return $this->error('invalid_classroom', "Vous ne pouvez publier un exercice que pour l'une de vos classes.", Response::HTTP_BAD_REQUEST);
        }

        $subject = null;
        if ($subjectId = $request->request->getInt('subjectId')) {
            foreach ($this->scope->subjects($teacher) as $s) {
                if ($s->getId() === $subjectId) {
                    $subject = $s;
                }
            }
            if (!$subject) {
                return $this->error('invalid_subject', "Choisissez l'une de vos matières.", Response::HTTP_BAD_REQUEST);
            }
        }

        $title = trim((string) $request->request->get('title'));
        if ($title === '') {
            return $this->error('invalid_title', 'Le titre est obligatoire.', Response::HTTP_BAD_REQUEST);
        }

        $dueDate = \DateTime::createFromFormat('!Y-m-d', (string) $request->request->get('dueDate'));
        if (!$dueDate) {
            return $this->error('invalid_due_date', 'La date de remise est obligatoire.', Response::HTTP_BAD_REQUEST);
        }

        $file = $request->files->get('attachment');
        if ($file instanceof UploadedFile) {
            $violations = $this->validator->validate($file, new File(
                maxSize: '5M',
                mimeTypes: [
                    'application/pdf',
                    'image/jpeg', 'image/png', 'image/webp',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ],
                mimeTypesMessage: 'Veuillez téléverser un PDF, une image ou un document Word.',
            ));
            if (\count($violations) > 0) {
                return $this->error('invalid_attachment', (string) $violations[0]->getMessage(), Response::HTTP_BAD_REQUEST);
            }
        }

        $homework = (new Homework())
            ->setTitle($title)
            ->setInstructions(trim((string) $request->request->get('instructions')) ?: null)
            ->setDueDate($dueDate)
            ->setClassroom($classroom)
            ->setSubject($subject)
            ->setTeacher($teacher)
            ->setSchool($classroom->getSchool())
            ->setSchoolYear($classroom->getSchoolYear());

        if ($file instanceof UploadedFile && ($path = $this->uploadFile($file, 'homework', $slugger))) {
            $homework->setAttachmentPath($path);
        }

        $this->em->persist($homework);
        $this->em->flush();

        return new JsonResponse($this->homeworkPayload($homework), Response::HTTP_CREATED);
    }

    #[Route('/homeworks/{id}', name: 'api_teacher_homework_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[IsGranted('ROLE_ENSEIGNANT')]
    public function deleteHomework(int $id, HomeworkRepository $homeworkRepository): JsonResponse
    {
        $homework = $homeworkRepository->find($id);
        if (!$homework || $homework->getTeacher()?->getId() !== $this->getTeacher()->getId()) {
            return $this->notFound('Exercice introuvable.');
        }

        try {
            $this->em->remove($homework);
            $this->em->flush();
        } catch (ForeignKeyConstraintViolationException) {
            return $this->error('in_use', 'Cet exercice ne peut pas être supprimé.', Response::HTTP_CONFLICT);
        }

        return new JsonResponse(['ok' => true]);
    }

    // ─── Appel (absences / retards) ──────────────────────────────────────

    /**
     * Feuille d'appel d'une classe pour une date : élèves, types d'absence de
     * l'établissement et absences déjà enregistrées ce jour-là.
     */
    #[Route('/attendance', name: 'api_teacher_attendance', methods: ['GET'])]
    #[IsGranted('ROLE_ENSEIGNANT')]
    public function attendance(Request $request, AbsenceRepository $absenceRepository, AbsenceTypeRepository $absenceTypeRepository): JsonResponse
    {
        $classroom = $this->scope->classroom($this->getTeacher(), $request->query->getInt('classroom'));
        if (!$classroom) {
            return $this->notFound('Classe introuvable.');
        }

        $date = \DateTime::createFromFormat('!Y-m-d', (string) $request->query->get('date', date('Y-m-d'))) ?: new \DateTime('today');
        $students = $this->studentRepository->findActiveByClassroom($classroom->getId());

        $absences = [];
        foreach ($absenceRepository->findActiveByStudentsAndDate(array_map(static fn (Student $s) => $s->getId(), $students), $date) as $absence) {
            $absences[$absence->getStudent()?->getId()][] = $this->absencePayload($absence);
        }

        return new JsonResponse([
            'classroom' => $this->classroomPayload($classroom),
            'date' => $date->format('Y-m-d'),
            'absenceTypes' => array_map(static fn (AbsenceType $t) => [
                'id' => $t->getId(),
                'name' => $t->getName(),
                'kind' => $t->getType(),
                'kindLabel' => $t->getTypeLabel(),
                'color' => $t->getColor(),
            ], $absenceTypeRepository->findActiveBySchool((int) $classroom->getSchool()?->getId())),
            'students' => array_map(fn (Student $s) => $this->studentPayload($s, $request) + [
                'absences' => $absences[$s->getId()] ?? [],
            ], $students),
        ]);
    }

    /**
     * Enregistre l'appel d'un créneau : crée (ou met à jour) une absence par élève
     * signalé, et retire celles que CET enseignant avait saisies sur ce créneau pour
     * des élèves finalement présents (tant qu'elles ne sont pas encore traitées).
     */
    #[Route('/attendance', name: 'api_teacher_attendance_save', methods: ['POST'])]
    #[IsGranted('ROLE_ENSEIGNANT')]
    public function saveAttendance(
        Request $request,
        AbsenceRepository $absenceRepository,
        AbsenceTypeRepository $absenceTypeRepository,
        PeriodRepository $periodRepository,
    ): JsonResponse {
        $teacher = $this->getTeacher();
        $data = json_decode($request->getContent(), true) ?? [];

        $classroom = $this->scope->classroom($teacher, (int) ($data['classroomId'] ?? 0));
        if (!$classroom) {
            return $this->notFound('Classe introuvable.');
        }

        $date = \DateTime::createFromFormat('!Y-m-d', (string) ($data['date'] ?? ''));
        if (!$date || $date > new \DateTime('today')) {
            return $this->error('invalid_date', "La date de l'appel doit être aujourd'hui ou une date passée.", Response::HTTP_BAD_REQUEST);
        }
        $start = $this->parseTime($data['startTime'] ?? null);
        $end = $this->parseTime($data['endTime'] ?? null);

        $students = [];
        foreach ($this->studentRepository->findActiveByClassroom($classroom->getId()) as $student) {
            $students[$student->getId()] = $student;
        }
        $types = [];
        foreach ($absenceTypeRepository->findActiveBySchool((int) $classroom->getSchool()?->getId()) as $type) {
            $types[$type->getId()] = $type;
        }

        // Contrôle complet avant écriture.
        $marked = [];
        foreach ((array) ($data['entries'] ?? []) as $entry) {
            $student = $students[(int) ($entry['studentId'] ?? 0)] ?? null;
            $type = $types[(int) ($entry['absenceTypeId'] ?? 0)] ?? null;
            if (!$student || !$type) {
                return $this->error('invalid_entry', "Élève ou type d'absence invalide pour cette classe.", Response::HTTP_BAD_REQUEST);
            }
            $marked[$student->getId()] = $type;
        }

        $period = $this->periodForDate($periodRepository, $classroom, $date);
        $slot = $start?->format('H:i');

        // Absences déjà saisies sur ce créneau (même date, même heure de début).
        $existing = [];
        foreach ($absenceRepository->findActiveByStudentsAndDate(array_keys($students), $date) as $absence) {
            if ($absence->getStartTime()?->format('H:i') === $slot) {
                $existing[$absence->getStudent()?->getId()] = $absence;
            }
        }

        $created = $updated = $removed = 0;
        foreach ($marked as $studentId => $type) {
            $absence = $existing[$studentId] ?? null;
            if ($absence) {
                if ($absence->getAbsenceType()?->getId() !== $type->getId()) {
                    $absence->setAbsenceType($type);
                    $updated++;
                }
                continue;
            }

            $absence = (new Absence())
                ->setStudent($students[$studentId])
                ->setAbsenceType($type)
                ->setDate($date)
                ->setStartTime($start)
                ->setEndTime($end)
                ->setSchool($classroom->getSchool())
                ->setPeriod($period)
                ->setRecordedBy($teacher)
                ->setJustificationStatus('pending');
            $this->em->persist($absence);
            $created++;
        }

        foreach ($existing as $studentId => $absence) {
            if (!isset($marked[$studentId])
                && $absence->getRecordedBy()?->getId() === $teacher->getId()
                && $absence->getJustificationStatus() === 'pending') {
                $this->em->remove($absence);
                $removed++;
            }
        }

        $this->em->flush();

        return new JsonResponse(['ok' => true, 'created' => $created, 'updated' => $updated, 'removed' => $removed]);
    }

    // ─── Aides ───────────────────────────────────────────────────────────

    /**
     * @throws MobileAuthException
     */
    private function checkCredentials(Request $request, string $username, string $password): User
    {
        return $this->auth->checkCredentials(
            $request,
            $username,
            $password,
            'ROLE_ENSEIGNANT',
            'Cette application est réservée aux enseignants.',
            $this->teacherApiLoginLimiter,
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

    /**
     * Évaluation créée par l'enseignant connecté, sinon null (404).
     */
    private function ownEvaluation(int $id): ?Evaluation
    {
        $evaluation = $this->em->getRepository(Evaluation::class)->find($id);

        return $evaluation && $evaluation->isActive() && $evaluation->getTeacher()?->getId() === $this->getTeacher()->getId()
            ? $evaluation
            : null;
    }

    /**
     * Matière de l'établissement de la classe, et de son niveau quand la matière en a un.
     */
    private function subjectFitsClassroom(Subject $subject, Classroom $classroom): bool
    {
        if ($subject->getSchool() && $classroom->getSchool() && $subject->getSchool()->getId() !== $classroom->getSchool()->getId()) {
            return false;
        }

        return !$subject->getLevel() || !$classroom->getLevel() || $subject->getLevel()->getId() === $classroom->getLevel()->getId();
    }

    /**
     * Période de la classe contenant la date, à défaut la période courante.
     */
    private function periodForDate(PeriodRepository $periodRepository, Classroom $classroom, \DateTimeInterface $date): ?Period
    {
        $schoolId = $classroom->getSchool()?->getId();
        $yearId = $classroom->getSchoolYear()?->getId();

        foreach ($periodRepository->findBySchoolAndYear($schoolId, $yearId) as $period) {
            if ($period->getStartDate() && $period->getEndDate()
                && $date >= $period->getStartDate() && $date <= $period->getEndDate()) {
                return $period;
            }
        }

        return $periodRepository->findCurrentPeriod($schoolId, $yearId);
    }

    private function parseTime(mixed $value): ?\DateTime
    {
        return \is_string($value) && preg_match('/^\d{2}:\d{2}$/', $value)
            ? (\DateTime::createFromFormat('!H:i', $value) ?: null)
            : null;
    }

    private function firstViolation(object $entity): ?string
    {
        $violations = $this->validator->validate($entity);

        return \count($violations) > 0 ? (string) $violations[0]->getMessage() : null;
    }

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

    private function classroomPayload(Classroom $classroom): array
    {
        return [
            'id' => $classroom->getId(),
            'name' => $classroom->getName(),
            'level' => $classroom->getLevel()?->getName(),
            'school' => $classroom->getSchool()?->getName(),
            'schoolYear' => $classroom->getSchoolYear()?->getName(),
        ];
    }

    private function studentPayload(Student $student, Request $request): array
    {
        $photo = $student->getPhoto();

        return [
            'id' => $student->getId(),
            'fullName' => $student->getFullName(),
            'firstName' => $student->getFirstName(),
            'lastName' => $student->getLastName(),
            'gender' => $student->getGender(),
            'matricule' => $student->getMatriculeInterne() ?? $student->getMatriculeNational(),
            // getBasePath() couvre les déploiements en sous-dossier (ex. /edu_school/public).
            'photoUrl' => $photo
                ? $request->getSchemeAndHttpHost() . $request->getBasePath() . '/' . ltrim($photo, '/')
                : null,
        ];
    }

    private function coursePayload(Course $course): array
    {
        return [
            'id' => $course->getId(),
            'day' => $course->getDayOfWeek(),
            'start' => $course->getStartTime()?->format('H:i'),
            'end' => $course->getEndTime()?->format('H:i'),
            'subject' => $course->getSubject()?->getName(),
            'color' => $course->getSubject()?->getColor(),
            'classroomId' => $course->getClassroom()?->getId(),
            'classroom' => $course->getClassroom()?->getName(),
            'room' => $course->getRoom()?->getName(),
        ];
    }

    private function periodPayload(Period $period): array
    {
        return [
            'id' => $period->getId(),
            'name' => $period->getName(),
            'start' => $period->getStartDate()?->format('Y-m-d'),
            'end' => $period->getEndDate()?->format('Y-m-d'),
        ];
    }

    private function evaluationPayload(Evaluation $evaluation): array
    {
        return [
            'id' => $evaluation->getId(),
            'name' => $evaluation->getName(),
            'type' => $evaluation->getType(),
            'typeLabel' => $evaluation->getTypeLabel(),
            'date' => $evaluation->getDate()?->format('Y-m-d'),
            'maxGrade' => (float) $evaluation->getMaxGrade(),
            'coefficient' => (float) $evaluation->getCoefficient(),
            'description' => $evaluation->getDescription(),
            'classroomId' => $evaluation->getClassroom()?->getId(),
            'classroom' => $evaluation->getClassroom()?->getName(),
            'subject' => $evaluation->getSubject()?->getName(),
            'periodId' => $evaluation->getPeriod()?->getId(),
            'period' => $evaluation->getPeriod()?->getName(),
            'isValidated' => $evaluation->isValidated(),
            'isPublished' => $evaluation->isPublished(),
            'isLocked' => $evaluation->isLockedByBulletin(),
            'editable' => $evaluation->isEditable(),
        ];
    }

    private function homeworkPayload(Homework $homework): array
    {
        return [
            'id' => $homework->getId(),
            'title' => $homework->getTitle(),
            'instructions' => $homework->getInstructions(),
            'dueDate' => $homework->getDueDate()?->format('Y-m-d'),
            'classroom' => $homework->getClassroom()?->getName(),
            'classroomId' => $homework->getClassroom()?->getId(),
            'subject' => $homework->getSubject()?->getName(),
            'hasAttachment' => $homework->hasAttachment(),
            'createdAt' => $homework->getCreatedAt()?->format(\DATE_ATOM),
        ];
    }

    private function absencePayload(Absence $absence): array
    {
        return [
            'id' => $absence->getId(),
            'absenceTypeId' => $absence->getAbsenceType()?->getId(),
            'type' => $absence->getAbsenceType()?->getName(),
            'kind' => $absence->getAbsenceType()?->getType(),
            'start' => $absence->getStartTime()?->format('H:i'),
            'end' => $absence->getEndTime()?->format('H:i'),
            'status' => $absence->getJustificationStatus(),
            'recordedByMe' => $absence->getRecordedBy()?->getId() === $this->getTeacher()->getId(),
        ];
    }

    private function getTeacher(): User
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }

    private function notFound(string $message): JsonResponse
    {
        return $this->error('not_found', $message, Response::HTTP_NOT_FOUND);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $code, 'message' => $message], $status);
    }
}
