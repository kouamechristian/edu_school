<?php

namespace App\Controller;

use App\Controller\Concern\HandlesEntityDeletion;
use App\Controller\Concern\HandlesFileUpload;
use App\Entity\Classroom;
use App\Entity\Homework;
use App\Entity\Subject;
use App\Entity\User;
use App\Form\HomeworkType;
use App\Repository\CourseRepository;
use App\Repository\HomeworkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Espace Enseignant — publication des exercices de maison aux classes enseignées.
 *
 * Les classes et matières proposées sont strictement celles que l'enseignant
 * enseigne (déduites de ses cours, Course.teacher). Un enseignant ne peut publier
 * un devoir que pour l'une de ses classes, ni modifier/supprimer le devoir d'un autre.
 */
#[Route('/enseignant')]
#[IsGranted('ROLE_ENSEIGNANT')]
class TeacherSpaceController extends AbstractController
{
    use HandlesEntityDeletion;
    use HandlesFileUpload;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CourseRepository $courseRepository,
        private readonly HomeworkRepository $homeworkRepository,
    ) {
    }

    #[Route('', name: 'enseignant_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $teacher = $this->getCurrentTeacher();

        return $this->render('enseignant/dashboard.html.twig', [
            'classrooms' => $this->teacherClassrooms($teacher),
            'homeworks' => \array_slice($this->homeworkRepository->findByTeacher($teacher->getId()), 0, 6),
        ]);
    }

    #[Route('/exercices', name: 'enseignant_homework_index', methods: ['GET'])]
    public function index(): Response
    {
        $teacher = $this->getCurrentTeacher();

        return $this->render('enseignant/homework/index.html.twig', [
            'homeworks' => $this->homeworkRepository->findByTeacher($teacher->getId()),
        ]);
    }

    #[Route('/exercices/nouveau', name: 'enseignant_homework_new', methods: ['GET', 'POST'])]
    public function new(Request $request, SluggerInterface $slugger): Response
    {
        $teacher = $this->getCurrentTeacher();
        $classrooms = $this->teacherClassrooms($teacher);

        if ($classrooms === []) {
            $this->addFlash('warning', "Vous n'êtes affecté à aucune classe : impossible de publier un exercice.");

            return $this->redirectToRoute('enseignant_homework_index');
        }

        $homework = new Homework();

        return $this->handleForm($homework, $request, $slugger, $teacher, $classrooms);
    }

    #[Route('/exercices/{id}/modifier', name: 'enseignant_homework_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Homework $homework, Request $request, SluggerInterface $slugger): Response
    {
        $teacher = $this->getCurrentTeacher();
        $this->assertAuthor($homework, $teacher);

        return $this->handleForm($homework, $request, $slugger, $teacher, $this->teacherClassrooms($teacher));
    }

    #[Route('/exercices/{id}', name: 'enseignant_homework_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Homework $homework): Response
    {
        $this->assertAuthor($homework, $this->getCurrentTeacher());

        return $this->render('enseignant/homework/show.html.twig', [
            'homework' => $homework,
        ]);
    }

    #[Route('/exercices/{id}/telecharger', name: 'enseignant_homework_download', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function download(Homework $homework): Response
    {
        $this->assertAuthor($homework, $this->getCurrentTeacher());

        if (!$homework->hasAttachment()) {
            throw $this->createNotFoundException();
        }

        $path = $this->getParameter('kernel.project_dir') . '/public/' . ltrim((string) $homework->getAttachmentPath(), '/');
        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $response->getFile()->getFilename());

        return $response;
    }

    #[Route('/exercices/{id}/supprimer', name: 'enseignant_homework_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Homework $homework, Request $request): Response
    {
        $this->assertAuthor($homework, $this->getCurrentTeacher());

        if ($this->isCsrfTokenValid('delete_homework_' . $homework->getId(), (string) $request->request->get('_token'))) {
            $this->deleteEntity($this->entityManager, $homework, 'Exercice supprimé.');
        }

        return $this->redirectToRoute('enseignant_homework_index');
    }

    /**
     * Crée/édite un devoir : borne la classe aux classes enseignées puis persiste.
     */
    private function handleForm(Homework $homework, Request $request, SluggerInterface $slugger, User $teacher, array $classrooms): Response
    {
        $form = $this->createForm(HomeworkType::class, $homework, [
            'classrooms' => $classrooms,
            'subjects' => $this->teacherSubjects($teacher),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $classroom = $homework->getClassroom();

            // Garde-fou : la classe visée doit faire partie des classes enseignées.
            if (!$classroom || !$this->isOwnClassroom($classroom, $classrooms)) {
                $this->addFlash('error', "Vous ne pouvez publier un exercice que pour l'une de vos classes.");

                return $this->redirectToRoute('enseignant_homework_index');
            }

            $homework->setTeacher($teacher);
            $homework->setSchool($classroom->getSchool());
            $homework->setSchoolYear($classroom->getSchoolYear());

            if ($file = $this->uploadFile($form->get('attachmentFile')->getData(), 'homework', $slugger)) {
                $homework->setAttachmentPath($file);
            }

            $this->entityManager->persist($homework);
            $this->entityManager->flush();

            $this->addFlash('success', 'Exercice de maison enregistré.');

            return $this->redirectToRoute('enseignant_homework_show', ['id' => $homework->getId()]);
        }

        return $this->render('enseignant/homework/form.html.twig', [
            'form' => $form,
            'homework' => $homework,
        ]);
    }

    /**
     * Classes distinctes enseignées par l'utilisateur (déduites de ses cours).
     *
     * @return Classroom[]
     */
    private function teacherClassrooms(User $teacher): array
    {
        $classrooms = [];
        foreach ($this->courseRepository->findByTeacher($teacher->getId()) as $course) {
            $classroom = $course->getClassroom();
            if ($classroom && $classroom->getId() !== null) {
                $classrooms[$classroom->getId()] = $classroom;
            }
        }

        return array_values($classrooms);
    }

    /**
     * Matières distinctes enseignées par l'utilisateur (déduites de ses cours).
     *
     * @return Subject[]
     */
    private function teacherSubjects(User $teacher): array
    {
        $subjects = [];
        foreach ($this->courseRepository->findByTeacher($teacher->getId()) as $course) {
            $subject = $course->getSubject();
            if ($subject && $subject->getId() !== null) {
                $subjects[$subject->getId()] = $subject;
            }
        }

        return array_values($subjects);
    }

    /**
     * @param Classroom[] $classrooms
     */
    private function isOwnClassroom(Classroom $classroom, array $classrooms): bool
    {
        foreach ($classrooms as $c) {
            if ($c->getId() === $classroom->getId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * L'auteur du devoir (ou un administrateur) uniquement.
     */
    private function assertAuthor(Homework $homework, User $teacher): void
    {
        if ($homework->getTeacher()?->getId() === $teacher->getId() || $this->isGranted('ROLE_ADMIN')) {
            return;
        }

        throw $this->createNotFoundException();
    }

    private function getCurrentTeacher(): User
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }
}
