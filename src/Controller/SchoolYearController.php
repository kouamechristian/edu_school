<?php

namespace App\Controller;

use App\Controller\Concern\HandlesEntityDeletion;
use App\Entity\SchoolYear;
use App\Form\SchoolYearType;
use App\Repository\SchoolYearRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/school-years', name: 'admin_school_year_')]
#[IsGranted('ROLE_ADMIN')]
class SchoolYearController extends AbstractController
{
    use HandlesEntityDeletion;

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(SchoolYearRepository $schoolYearRepository, \Symfony\Component\HttpFoundation\Request $request, \Knp\Component\Pager\PaginatorInterface $paginator): Response
    {
        $schoolYears = $paginator->paginate($schoolYearRepository->findAll(), $request->query->getInt('page', 1), 50);

        return $this->render('school_year/index.html.twig', [
            'school_years' => $schoolYears,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $schoolYear = new SchoolYear();
        $form = $this->createForm(SchoolYearType::class, $schoolYear);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($schoolYear);
            $entityManager->flush();

            $this->addFlash('success', 'L\'année scolaire a été créée avec succès.');

            return $this->redirectToRoute('admin_school_year_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('school_year/new.html.twig', [
            'school_year' => $schoolYear,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(SchoolYear $schoolYear): Response
    {
        return $this->render('school_year/show.html.twig', [
            'school_year' => $schoolYear,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, SchoolYear $schoolYear, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SchoolYearType::class, $schoolYear);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'L\'année scolaire a été modifiée avec succès.');

            return $this->redirectToRoute('admin_school_year_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('school_year/edit.html.twig', [
            'school_year' => $schoolYear,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, SchoolYear $schoolYear, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$schoolYear->getId(), $request->request->get('_token'))) {
            $this->deleteEntity(
                $entityManager,
                $schoolYear,
                'L\'année scolaire a été supprimée avec succès.',
                'Suppression impossible : cette année scolaire est encore liée à des périodes, inscriptions ou autres données.'
            );
        }

        return $this->redirectToRoute('admin_school_year_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/set-current', name: 'set_current', methods: ['POST'])]
    public function setCurrent(
        Request $request, 
        SchoolYear $schoolYear, 
        SchoolYearRepository $repository
    ): Response {
        if ($this->isCsrfTokenValid('set-current'.$schoolYear->getId(), $request->request->get('_token'))) {
            $repository->setAsCurrent($schoolYear);

            $this->addFlash('success', 'L\'année scolaire a été définie comme courante.');
        }

        return $this->redirectToRoute('admin_school_year_index', [], Response::HTTP_SEE_OTHER);
    }
}

