<?php

namespace App\Controller;

use App\Entity\Student;
use App\Repository\AbsenceRepository;
use App\Repository\GradeRepository;
use App\Repository\StudentRepository;
use App\Service\AI\AIService;
use App\Service\AI\AttendanceAIService;
use App\Service\AI\BulletinAIService;
use App\Service\AI\ChatbotAIService;
use App\Service\AI\ReportAIService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/ai')]
#[IsGranted('ROLE_USER')]
class AIController extends AbstractController
{
    #[Route('/bulletin-comment', name: 'ai_bulletin_comment', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function bulletinComment(
        Request $request,
        BulletinAIService $bulletinAI,
        StudentRepository $studentRepo,
        GradeRepository $gradeRepo,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $studentId = $data['student_id'] ?? null;
        $periodName = $data['period'] ?? 'Période en cours';

        if (!$studentId) {
            return $this->json(['error' => 'student_id requis'], Response::HTTP_BAD_REQUEST);
        }

        $student = $studentRepo->find($studentId);
        if (!$student) {
            return $this->json(['error' => 'Élève introuvable'], Response::HTTP_NOT_FOUND);
        }

        $grades = $this->buildGradesArray($student, $gradeRepo);
        $comment = $bulletinAI->generateComment($student, $grades, $periodName);

        return $this->json([
            'success' => true,
            'comment' => $comment,
            'student' => $student->getFullName(),
            'period' => $periodName,
        ]);
    }

    #[Route('/chatbot', name: 'ai_chatbot', methods: ['POST'])]
    public function chatbot(
        Request $request,
        ChatbotAIService $chatbotAI,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $question = trim($data['message'] ?? '');
        $history = $data['history'] ?? [];

        if ($question === '') {
            return $this->json(['error' => 'Le message est vide'], Response::HTTP_BAD_REQUEST);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $answer = $chatbotAI->answer($question, $user, $history);

        return $this->json([
            'success' => true,
            'answer' => $answer,
            'timestamp' => (new \DateTime())->format('H:i'),
        ]);
    }

    #[Route('/attendance-analysis', name: 'ai_attendance_analysis', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function attendanceAnalysis(
        Request $request,
        AttendanceAIService $attendanceAI,
        StudentRepository $studentRepo,
        AbsenceRepository $absenceRepo,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $studentId = $data['student_id'] ?? null;

        if (!$studentId) {
            return $this->json(['error' => 'student_id requis'], Response::HTTP_BAD_REQUEST);
        }

        $student = $studentRepo->find($studentId);
        if (!$student) {
            return $this->json(['error' => 'Élève introuvable'], Response::HTTP_NOT_FOUND);
        }

        $absences = $absenceRepo->findBy(
            ['student' => $student, 'isActive' => true],
            ['date' => 'ASC']
        );

        $absencesData = [];
        $dayNames = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
        foreach ($absences as $absence) {
            $absencesData[] = [
                'date' => $absence->getDate()->format('Y-m-d'),
                'day_of_week' => $dayNames[(int) $absence->getDate()->format('w')],
                'type' => $absence->getAbsenceType()?->getName() ?? 'Absence',
                'justified' => $absence->isJustified(),
                'duration_hours' => $absence->getDurationInHours(),
            ];
        }

        $analysis = $attendanceAI->analyzeAbsences($student, $absencesData);

        return $this->json([
            'success' => true,
            'student' => $student->getFullName(),
            'analysis' => $analysis,
        ]);
    }

    #[Route('/report', name: 'ai_report', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function report(
        Request $request,
        ReportAIService $reportAI,
        EntityManagerInterface $em,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $schoolId = $data['school_id'] ?? null;

        if (!$schoolId) {
            return $this->json(['error' => 'school_id requis'], Response::HTTP_BAD_REQUEST);
        }

        $school = $em->getRepository(\App\Entity\School::class)->find($schoolId);
        if (!$school) {
            return $this->json(['error' => 'Établissement introuvable'], Response::HTTP_NOT_FOUND);
        }

        $stats = $data['stats'] ?? $this->gatherSchoolStats($school, $em);
        $summary = $reportAI->generateSummary($school, $stats);

        return $this->json([
            'success' => true,
            'school' => $school->getName(),
            'summary' => $summary,
        ]);
    }

    #[Route('/dashboard', name: 'ai_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function dashboard(
        AIService $aiService,
        EntityManagerInterface $em,
    ): Response {
        $stats = $aiService->getStats();

        $studentCount = $em->getRepository(Student::class)->count(['isActive' => true]);

        return $this->render('ai/dashboard.html.twig', [
            'ai_stats' => $stats,
            'student_count' => $studentCount,
        ]);
    }

    #[Route('/status', name: 'ai_status', methods: ['GET'])]
    public function status(AIService $aiService): JsonResponse
    {
        return $this->json([
            'enabled' => $aiService->isEnabled(),
            'stats' => $aiService->getStats(),
        ]);
    }

    private function buildGradesArray(Student $student, GradeRepository $gradeRepo): array
    {
        $grades = $gradeRepo->findBy(['student' => $student]);
        $result = [];

        foreach ($grades as $grade) {
            if ($grade->getValue() === null) {
                continue;
            }
            $eval = $grade->getEvaluation();
            $result[] = [
                'subject' => $eval?->getSubject()?->getName() ?? 'Matière',
                'value' => $grade->getValue(),
                'max' => $eval?->getMaxGrade() ?? '20',
                'coefficient' => $eval?->getCoefficient() ?? '1',
                'type' => $eval?->getTypeLabel() ?? '',
            ];
        }

        return $result;
    }

    private function gatherSchoolStats(\App\Entity\School $school, EntityManagerInterface $em): array
    {
        $studentRepo = $em->getRepository(Student::class);

        return [
            'total_students' => $studentRepo->count(['school' => $school]),
            'active_students' => $studentRepo->count(['school' => $school, 'isActive' => true]),
            'period' => (new \DateTime())->format('F Y'),
        ];
    }
}
