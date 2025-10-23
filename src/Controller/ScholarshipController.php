<?php

namespace App\Controller;

use App\Entity\Scholarship;
use App\Form\ScholarshipType;
use App\Repository\ScholarshipRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/scholarship')]
class ScholarshipController extends AbstractController
{
    #[Route('/', name: 'admin_scholarship_index', methods: ['GET'])]
    public function index(ScholarshipRepository $scholarshipRepository): Response
    {
        return $this->render('scholarship/index.html.twig', [
            'scholarships' => $scholarshipRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'admin_scholarship_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $scholarship = new Scholarship();
        $form = $this->createForm(ScholarshipType::class, $scholarship);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($scholarship);
            $entityManager->flush();

            return $this->redirectToRoute('admin_scholarship_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('scholarship/new.html.twig', [
            'scholarship' => $scholarship,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_scholarship_show', methods: ['GET'])]
    public function show(Scholarship $scholarship): Response
    {
        return $this->render('scholarship/show.html.twig', [
            'scholarship' => $scholarship,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_scholarship_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Scholarship $scholarship, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ScholarshipType::class, $scholarship);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('admin_scholarship_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('scholarship/edit.html.twig', [
            'scholarship' => $scholarship,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_scholarship_delete', methods: ['POST'])]
    public function delete(Request $request, Scholarship $scholarship, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$scholarship->getId(), $request->request->get('_token'))) {
            $entityManager->remove($scholarship);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_scholarship_index', [], Response::HTTP_SEE_OTHER);
    }
}
