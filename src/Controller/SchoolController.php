<?php

namespace App\Controller;

use App\Entity\School;
use App\Form\SchoolType;
use App\Repository\SchoolRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/schools', name: 'admin_school_')]
#[IsGranted('ROLE_ADMIN')]
class SchoolController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(SchoolRepository $schoolRepository): Response
    {
        $schools = $schoolRepository->findAll();
        $countByType = $schoolRepository->countByType();

        return $this->render('school/index.html.twig', [
            'schools' => $schools,
            'stats' => $countByType,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $school = new School();
        $form = $this->createForm(SchoolType::class, $school);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($school);
            $entityManager->flush();

            $this->addFlash('success', 'L\'établissement a été créé avec succès.');

            return $this->redirectToRoute('admin_school_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('school/new.html.twig', [
            'school' => $school,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(School $school): Response
    {
        return $this->render('school/show.html.twig', [
            'school' => $school,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, School $school, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SchoolType::class, $school);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'L\'établissement a été modifié avec succès.');

            return $this->redirectToRoute('admin_school_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('school/edit.html.twig', [
            'school' => $school,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, School $school, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$school->getId(), $request->request->get('_token'))) {
            $entityManager->remove($school);
            $entityManager->flush();

            $this->addFlash('success', 'L\'établissement a été supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_school_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(Request $request, School $school, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('toggle'.$school->getId(), $request->request->get('_token'))) {
            $school->setIsActive(!$school->isActive());
            $entityManager->flush();

            $status = $school->isActive() ? 'activé' : 'désactivé';
            $this->addFlash('success', "L'établissement a été {$status} avec succès.");
        }

        return $this->redirectToRoute('admin_school_index', [], Response::HTTP_SEE_OTHER);
    }
}

