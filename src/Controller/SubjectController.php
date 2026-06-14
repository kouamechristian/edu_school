<?php

namespace App\Controller;

use App\Controller\Concern\HandlesEntityDeletion;
use App\Entity\Subject;
use App\Form\SubjectType;
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

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(SubjectRepository $subjectRepository, SchoolContextService $contextService): Response
    {
        $currentSchool = $contextService->getCurrentSchool();
        
        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour voir les matières.');
            return $this->render('subject/index.html.twig', [
                'subjects' => [],
                'current_school' => null,
            ]);
        }

        $subjects = $subjectRepository->findBySchool($currentSchool->getId());

        return $this->render('subject/index.html.twig', [
            'subjects' => $subjects,
            'current_school' => $currentSchool,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SchoolContextService $contextService): Response
    {
        $currentSchool = $contextService->getCurrentSchool();
        
        $subject = new Subject();
        
        if ($currentSchool) {
            $subject->setSchool($currentSchool);
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

            return $this->redirectToRoute('admin_subject_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('subject/new.html.twig', [
            'subject' => $subject,
            'form' => $form,
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
    public function delete(Request $request, Subject $subject, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$subject->getId(), $request->request->get('_token'))) {
            $this->deleteEntity(
                $entityManager,
                $subject,
                'La matière a été supprimée avec succès.',
                'Suppression impossible : cette matière est encore utilisée par des cours ou évaluations.'
            );
        }

        return $this->redirectToRoute('admin_subject_index', [], Response::HTTP_SEE_OTHER);
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

        return $this->redirectToRoute('admin_subject_index', [], Response::HTTP_SEE_OTHER);
    }
}

