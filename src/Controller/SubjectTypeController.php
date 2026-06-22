<?php

namespace App\Controller;

use App\Controller\Concern\HandlesEntityDeletion;
use App\Entity\SubjectType;
use App\Form\SubjectTypeType;
use App\Repository\SubjectTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/subject-types', name: 'admin_subject_type_')]
#[IsGranted('ROLE_ADMIN')]
class SubjectTypeController extends AbstractController
{
    use HandlesEntityDeletion;

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(SubjectTypeRepository $repository, \Symfony\Component\HttpFoundation\Request $request, \Knp\Component\Pager\PaginatorInterface $paginator): Response
    {
        return $this->render('subject_type/index.html.twig', [
            'subject_types' => $paginator->paginate($repository->findAllOrdered(), $request->query->getInt('page', 1), 50),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $subjectType = new SubjectType();
        $form = $this->createForm(SubjectTypeType::class, $subjectType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($subjectType);
            $entityManager->flush();

            $this->addFlash('success', 'Le type de matière a été créé avec succès.');

            return $this->redirectToRoute('admin_subject_type_index');
        }

        return $this->render('subject_type/new.html.twig', [
            'subject_type' => $subjectType,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, SubjectType $subjectType, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SubjectTypeType::class, $subjectType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Le type de matière a été modifié avec succès.');

            return $this->redirectToRoute('admin_subject_type_index');
        }

        return $this->render('subject_type/edit.html.twig', [
            'subject_type' => $subjectType,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, SubjectType $subjectType, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$subjectType->getId(), $request->request->get('_token'))) {
            $this->deleteEntity(
                $entityManager,
                $subjectType,
                'Le type de matière a été supprimé avec succès.',
                'Suppression impossible : ce type est encore utilisé par des matières.'
            );
        }

        return $this->redirectToRoute('admin_subject_type_index');
    }
}
