<?php

namespace App\Controller;

use App\Controller\Concern\HandlesEntityDeletion;
use App\Entity\Level;
use App\Form\LevelType;
use App\Repository\CycleRepository;
use App\Repository\LevelRepository;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/levels', name: 'admin_level_')]
#[IsGranted('ROLE_DIRECTEUR')]
class LevelController extends AbstractController
{
    use HandlesEntityDeletion;

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(LevelRepository $levelRepository, SchoolContextService $contextService, \Symfony\Component\HttpFoundation\Request $request, \Knp\Component\Pager\PaginatorInterface $paginator): Response
    {
        // Récupérer l'établissement courant
        $currentSchool = $contextService->getCurrentSchool();
        
        // Si pas d'établissement sélectionné, afficher un message
        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour voir les niveaux.');
            return $this->render('level/index.html.twig', [
                'levels' => [],
                'current_school' => null,
            ]);
        }

        // Récupérer UNIQUEMENT les niveaux de l'établissement sélectionné
        $levels = $paginator->paginate($levelRepository->findBySchool($currentSchool->getId()), $request->query->getInt('page', 1), 50);

        return $this->render('level/index.html.twig', [
            'levels' => $levels,
            'current_school' => $currentSchool,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SchoolContextService $contextService, LevelRepository $levelRepository, CycleRepository $cycleRepository): Response
    {
        // Récupérer l'établissement courant
        $currentSchool = $contextService->getCurrentSchool();

        $level = new Level();

        // Pré-remplir avec l'établissement sélectionné
        if ($currentSchool) {
            $level->setSchool($currentSchool);
        }

        $form = $this->createForm(LevelType::class, $level, [
            'cycles' => $cycleRepository->findBySchool($currentSchool?->getId()),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Lier automatiquement à l'établissement sélectionné si non défini
            if (!$level->getSchool() && $currentSchool) {
                $level->setSchool($currentSchool);
            }

            // Ordre d'affichage automatique : dernier ordre de l'établissement + 1
            $level->setOrderNumber($levelRepository->getNextOrderNumber($level->getSchool()?->getId()));

            $entityManager->persist($level);
            $entityManager->flush();

            $this->addFlash('success', 'Le niveau a été créé avec succès.');

            return $this->redirectToRoute('admin_level_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('level/new.html.twig', [
            'level' => $level,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/show', name: 'show', methods: ['GET'])]
    public function show(Level $level): Response
    {
        return $this->render('level/show.html.twig', [
            'level' => $level,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Level $level, EntityManagerInterface $entityManager, CycleRepository $cycleRepository): Response
    {
        $form = $this->createForm(LevelType::class, $level, [
            'cycles' => $cycleRepository->findBySchool($level->getSchool()?->getId()),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Le niveau a été modifié avec succès.');

            return $this->redirectToRoute('admin_level_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('level/edit.html.twig', [
            'level' => $level,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Level $level, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$level->getId(), $request->request->get('_token'))) {
            $this->deleteEntity(
                $entityManager,
                $level,
                'Le niveau a été supprimé avec succès.',
                'Suppression impossible : ce niveau est encore lié à des classes, frais ou autres données.'
            );
        }

        return $this->redirectToRoute('admin_level_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/move/{direction}', name: 'move', methods: ['POST'], requirements: ['direction' => 'up|down'])]
    public function move(
        Request $request,
        Level $level,
        string $direction,
        EntityManagerInterface $entityManager,
        LevelRepository $levelRepository
    ): Response {
        if ($this->isCsrfTokenValid('move'.$level->getId(), $request->request->get('_token'))) {
            // Liste ordonnée des niveaux (actifs) du même établissement.
            $levels = $levelRepository->findBySchool($level->getSchool()?->getId());

            $index = null;
            foreach ($levels as $i => $l) {
                if ($l->getId() === $level->getId()) {
                    $index = $i;
                    break;
                }
            }

            if ($index !== null) {
                $target = $direction === 'up' ? $index - 1 : $index + 1;

                if ($target >= 0 && $target < count($levels)) {
                    // Échange les positions puis réattribue un ordre séquentiel (1..n).
                    [$levels[$index], $levels[$target]] = [$levels[$target], $levels[$index]];

                    foreach ($levels as $position => $l) {
                        $l->setOrderNumber($position + 1);
                    }

                    $entityManager->flush();
                    $this->addFlash('success', 'L\'ordre des niveaux a été mis à jour.');
                }
            }
        }

        return $this->redirectToRoute('admin_level_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(Request $request, Level $level, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('toggle'.$level->getId(), $request->request->get('_token'))) {
            $level->setIsActive(!$level->isActive());
            $entityManager->flush();

            $status = $level->isActive() ? 'activé' : 'désactivé';
            $this->addFlash('success', "Le niveau a été {$status} avec succès.");
        }

        return $this->redirectToRoute('admin_level_index', [], Response::HTTP_SEE_OTHER);
    }
}

