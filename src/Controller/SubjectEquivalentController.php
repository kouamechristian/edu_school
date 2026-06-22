<?php

namespace App\Controller;

use App\Controller\Concern\HandlesEntityDeletion;
use App\Entity\SubjectEquivalent;
use App\Form\SubjectEquivalentType;
use App\Repository\SubjectEquivalentRepository;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * CRUD des matières équivalentes (regroupement d'une ou plusieurs matières sous un code).
 */
#[Route('/admin/subject-equivalents', name: 'admin_subject_equivalent_')]
#[IsGranted('ROLE_ADMIN')]
class SubjectEquivalentController extends AbstractController
{
    use HandlesEntityDeletion;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SchoolContextService $schoolContextService,
    ) {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(SubjectEquivalentRepository $repository, PaginatorInterface $paginator, Request $request): Response
    {
        $school = $this->schoolContextService->getCurrentSchool();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour voir les matières équivalentes.');
            return $this->redirectToRoute('admin_subject_index');
        }

        $equivalents = $paginator->paginate(
            $repository->findBySchool($school->getId()),
            $request->query->getInt('page', 1),
            50
        );

        return $this->render('subject_equivalent/index.html.twig', [
            'equivalents' => $equivalents,
            'current_school' => $school,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, SubjectEquivalentRepository $repository): Response
    {
        $school = $this->schoolContextService->getCurrentSchool();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour créer une matière équivalente.');
            return $this->redirectToRoute('admin_subject_equivalent_index');
        }

        $equivalent = new SubjectEquivalent();
        // L'établissement (NotNull) doit être posé AVANT handleRequest : la validation du
        // formulaire s'exécute pendant handleRequest (POST_SUBMIT), pas à isValid().
        $equivalent->setSchool($school);

        $form = $this->createForm(SubjectEquivalentType::class, $equivalent);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Numéro d'ordre attribué automatiquement (séquentiel par établissement).
            $equivalent->setNumeroOrdre($repository->getNextNumeroOrdre($school->getId()));

            $this->entityManager->persist($equivalent);
            $this->entityManager->flush();

            $this->addFlash('success', 'La matière équivalente a été créée avec succès.');

            return $this->redirectToRoute('admin_subject_equivalent_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('subject_equivalent/new.html.twig', [
            'equivalent' => $equivalent,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, SubjectEquivalent $equivalent): Response
    {
        $form = $this->createForm(SubjectEquivalentType::class, $equivalent);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'La matière équivalente a été modifiée avec succès.');

            return $this->redirectToRoute('admin_subject_equivalent_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('subject_equivalent/edit.html.twig', [
            'equivalent' => $equivalent,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, SubjectEquivalent $equivalent): Response
    {
        if ($this->isCsrfTokenValid('delete' . $equivalent->getId(), $request->request->get('_token'))) {
            $this->deleteEntity($this->entityManager, $equivalent, 'La matière équivalente a été supprimée avec succès.');
        }

        return $this->redirectToRoute('admin_subject_equivalent_index', [], Response::HTTP_SEE_OTHER);
    }
}
