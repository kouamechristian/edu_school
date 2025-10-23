<?php

namespace App\Controller;

use App\Entity\Student;
use App\Entity\PreRegistration;
use App\Form\StudentType;
use App\Repository\StudentRepository;
use App\Repository\PreRegistrationRepository;
use App\Repository\ClassroomRepository;
use App\Repository\LevelRepository;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
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
        private SchoolContextService $schoolContextService
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
            ->setParameter('statuses', ['graduated', 'transferred', 'inactive'])
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
        LevelRepository $levelRepository
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
            foreach ($studentIds as $preRegistrationId) {
                $preRegistration = $preRegistrationRepository->find($preRegistrationId);
                if ($preRegistration && $preRegistration->getStatus() === 'validated') {
                    // Créer l'élève à partir de la préinscription
                    $student = $this->createStudentFromPreRegistration($preRegistration, $classId);
                    $this->entityManager->persist($student);
                    
                    // Mettre à jour le statut de la préinscription
                    $preRegistration->setStatus('enrolled');
                    $preRegistration->setEnrolledAt(new \DateTime());
                    $student->setPreRegistration($preRegistration);
                    
                    $enrolledCount++;
                }
            }

            $this->entityManager->flush();
            $this->addFlash('success', "{$enrolledCount} élève(s) inscrit(s) avec succès.");
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
        ]);
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(StudentRepository $studentRepository, Request $request): Response
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

        // Statistiques
        $stats = [
            'total' => count($students),
            'active' => count(array_filter($students, fn($s) => $s->getStatus() === 'active')),
            'inactive' => count(array_filter($students, fn($s) => $s->getStatus() === 'inactive')),
            'graduated' => count(array_filter($students, fn($s) => $s->getStatus() === 'graduated')),
        ];

        return $this->render('student/index.html.twig', [
            'students' => $students,
            'stats' => $stats,
            'search' => $search,
            'status' => $status,
            'level' => $level,
        ]);
    }


    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Student $student): Response
    {
        return $this->render('student/show.html.twig', [
            'student' => $student,
        ]);
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

    private function createStudentFromPreRegistration(PreRegistration $preRegistration, ?int $classId): Student
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
        
        // Utiliser le niveau de la préinscription
        $student->setLevel($preRegistration->getRequestedLevel());
        
        // Assigner la classe si sélectionnée
        if ($classId) {
            $classroom = $this->entityManager->getRepository(\App\Entity\Classroom::class)->find($classId);
            $student->setClassroom($classroom);
        }
        
        $student->setStatus('active');
        $student->setStudentNumber($this->generateStudentNumber());
        // La relation est maintenant gérée par PreRegistration

        return $student;
    }
}
