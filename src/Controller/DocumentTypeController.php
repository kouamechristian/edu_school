<?php

namespace App\Controller;

use App\Entity\DocumentType;
use App\Form\DocumentTypeType;
use App\Repository\DocumentTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/document-types', name: 'admin_document_type_')]
#[IsGranted('ROLE_ADMIN')]
class DocumentTypeController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(DocumentTypeRepository $documentTypeRepository): Response
    {
        return $this->render('document_type/index.html.twig', [
            'document_types' => $documentTypeRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $documentType = new DocumentType();
        $form = $this->createForm(DocumentTypeType::class, $documentType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($documentType);
            $entityManager->flush();

            $this->addFlash('success', 'Le type de document a été créé avec succès.');

            return $this->redirectToRoute('admin_document_type_index');
        }

        return $this->render('document_type/new.html.twig', [
            'document_type' => $documentType,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(DocumentType $documentType): Response
    {
        return $this->render('document_type/show.html.twig', [
            'document_type' => $documentType,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        DocumentType $documentType,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(DocumentTypeType::class, $documentType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Le type de document a été modifié avec succès.');

            return $this->redirectToRoute('admin_document_type_index');
        }

        return $this->render('document_type/edit.html.twig', [
            'document_type' => $documentType,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(
        Request $request,
        DocumentType $documentType,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('delete'.$documentType->getId(), $request->request->get('_token'))) {
            $entityManager->remove($documentType);
            $entityManager->flush();

            $this->addFlash('success', 'Le type de document a été supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_document_type_index');
    }
}
