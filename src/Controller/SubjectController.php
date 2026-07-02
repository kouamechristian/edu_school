<?php

namespace App\Controller;

use App\Controller\Concern\HandlesEntityDeletion;
use App\Entity\Level;
use App\Entity\Subject;
use App\Form\SubjectType;
use App\Repository\LevelRepository;
use App\Repository\SubjectRepository;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/subjects', name: 'admin_subject_')]
#[IsGranted('ROLE_DIRECTEUR')]
class SubjectController extends AbstractController
{
    use HandlesEntityDeletion;

    /**
     * Accueil : les matières sont rangées par niveau. On entre dans un niveau
     * pour voir et ajouter ses matières.
     */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(
        SubjectRepository $subjectRepository,
        LevelRepository $levelRepository,
        SchoolContextService $contextService
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();

        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour voir les matières.');
            return $this->render('subject/index.html.twig', [
                'levels' => [],
                'current_school' => null,
            ]);
        }

        $schoolId = $currentSchool->getId();
        $levels = $levelRepository->findBySchool($schoolId);

        // Nombre de matières (actives) par niveau, pour l'affichage des cartes.
        $levelData = [];
        foreach ($levels as $level) {
            $levelData[] = [
                'level' => $level,
                'count' => count($subjectRepository->findByLevel($level->getId())),
            ];
        }

        return $this->render('subject/index.html.twig', [
            'levels' => $levelData,
            'current_school' => $currentSchool,
        ]);
    }

    /**
     * Matières d'un niveau précis (avec ajout rattaché à ce niveau).
     */
    #[Route('/level/{id}', name: 'by_level', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function byLevel(Level $level, SubjectRepository $subjectRepository, SchoolContextService $contextService): Response
    {
        $currentSchool = $contextService->getCurrentSchool();

        // Sécurité : le niveau doit appartenir à l'établissement courant.
        if (!$currentSchool || $level->getSchool()?->getId() !== $currentSchool->getId()) {
            $this->addFlash('warning', "Ce niveau n'appartient pas à l'établissement courant.");
            return $this->redirectToRoute('admin_subject_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('subject/by_level.html.twig', [
            'level' => $level,
            'subjects' => $subjectRepository->findByLevel($level->getId()),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        LevelRepository $levelRepository,
        SchoolContextService $contextService
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();

        $subject = new Subject();

        if ($currentSchool) {
            $subject->setSchool($currentSchool);
        }

        // Pré-remplir le niveau si on arrive depuis la page d'un niveau.
        $levelId = $request->query->getInt('level');
        $presetLevel = null;
        if ($levelId > 0) {
            $presetLevel = $levelRepository->find($levelId);
            if ($presetLevel && $currentSchool && $presetLevel->getSchool()?->getId() === $currentSchool->getId()) {
                $subject->setLevel($presetLevel);
            } else {
                $presetLevel = null;
            }
        }

        $form = $this->createForm(SubjectType::class, $subject);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$subject->getSchool() && $currentSchool) {
                $subject->setSchool($currentSchool);
            }

            $entityManager->persist($subject);
            $entityManager->flush();

            $this->addFlash('success', 'La matière a été créée avec succès.');

            return $this->redirectToSubjectList($subject->getLevel()?->getId());
        }

        return $this->render('subject/new.html.twig', [
            'subject' => $subject,
            'form' => $form,
            'preset_level' => $presetLevel,
        ]);
    }

    #[Route('/{id}/show', name: 'show', methods: ['GET'])]
    public function show(Subject $subject): Response
    {
        return $this->render('subject/show.html.twig', [
            'subject' => $subject,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Subject $subject, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SubjectType::class, $subject);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'La matière a été modifiée avec succès.');

            return $this->redirectToRoute('admin_subject_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('subject/edit.html.twig', [
            'subject' => $subject,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Subject $subject, EntityManagerInterface $entityManager): Response
    {
        $levelId = $subject->getLevel()?->getId();

        if ($this->isCsrfTokenValid('delete'.$subject->getId(), $request->request->get('_token'))) {
            $this->deleteEntity(
                $entityManager,
                $subject,
                'La matière a été supprimée avec succès.',
                'Suppression impossible : cette matière est encore utilisée par des cours ou évaluations.'
            );
        }

        return $this->redirectToSubjectList($levelId);
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(Request $request, Subject $subject, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('toggle'.$subject->getId(), $request->request->get('_token'))) {
            $subject->setIsActive(!$subject->isActive());
            $entityManager->flush();

            $status = $subject->isActive() ? 'activée' : 'désactivée';
            $this->addFlash('success', "La matière a été {$status} avec succès.");
        }

        return $this->redirectToSubjectList($subject->getLevel()?->getId());
    }

    /**
     * Redirige vers la page du niveau concerné, ou vers l'accueil des matières
     * si le niveau est inconnu.
     */
    private function redirectToSubjectList(?int $levelId): Response
    {
        if ($levelId) {
            return $this->redirectToRoute('admin_subject_by_level', ['id' => $levelId], Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('admin_subject_index', [], Response::HTTP_SEE_OTHER);
    }
}

