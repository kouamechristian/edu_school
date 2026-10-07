<?php

namespace App\Controller;

use App\Controller\Concern\HandlesEntityDeletion;
use App\Entity\SchoolGroup;
use App\Form\SchoolGroupType;
use App\Repository\SchoolGroupRepository;
use App\Security\SchoolManagementScope;
use App\Security\Voter\SchoolManagementVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/school-groups', name: 'admin_school_group_')]
#[IsGranted('ROLE_ADMIN')]
class SchoolGroupController extends AbstractController
{
    use HandlesEntityDeletion;

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(SchoolGroupRepository $schoolGroupRepository, \Symfony\Component\HttpFoundation\Request $request, \Knp\Component\Pager\PaginatorInterface $paginator, SchoolManagementScope $scope): Response
    {
        // Un administrateur ne voit que son groupe (cf. SchoolManagementScope).
        $query = $scope->restrictGroups($schoolGroupRepository->createQueryBuilder('sg'), 'sg')->orderBy('sg.name', 'ASC');
        $groups = $paginator->paginate($query, $request->query->getInt('page', 1), 50);

        return $this->render('school_group/index.html.twig', [
            'school_groups' => $groups,
        ]);
    }

    /**
     * Création, suppression et (dés)activation sont réservées au super-administrateur :
     * un groupe créé par un administrateur ne lui serait pas rattaché, et supprimer ou
     * désactiver son propre groupe lui retirerait l'accès à ses établissements.
     */
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $schoolGroup = new SchoolGroup();
        $form = $this->createForm(SchoolGroupType::class, $schoolGroup);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($schoolGroup);
            $entityManager->flush();

            $this->addFlash('success', 'Le groupe d\'établissements a été créé avec succès.');

            return $this->redirectToRoute('admin_school_group_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('school_group/new.html.twig', [
            'school_group' => $schoolGroup,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(SchoolGroup $schoolGroup): Response
    {
        $this->denyAccessUnlessGranted(SchoolManagementVoter::MANAGE, $schoolGroup);

        return $this->render('school_group/show.html.twig', [
            'school_group' => $schoolGroup,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, SchoolGroup $schoolGroup, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(SchoolManagementVoter::MANAGE, $schoolGroup);

        $form = $this->createForm(SchoolGroupType::class, $schoolGroup);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Le groupe d\'établissements a été modifié avec succès.');

            return $this->redirectToRoute('admin_school_group_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('school_group/edit.html.twig', [
            'school_group' => $schoolGroup,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function delete(Request $request, SchoolGroup $schoolGroup, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$schoolGroup->getId(), $request->request->get('_token'))) {
            $this->deleteEntity(
                $entityManager,
                $schoolGroup,
                'Le groupe d\'établissements a été supprimé avec succès.',
                'Suppression impossible : ce groupe contient encore des établissements. Veuillez d\'abord les détacher ou les supprimer.'
            );
        }

        return $this->redirectToRoute('admin_school_group_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function toggle(Request $request, SchoolGroup $schoolGroup, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('toggle'.$schoolGroup->getId(), $request->request->get('_token'))) {
            $schoolGroup->setIsActive(!$schoolGroup->isActive());
            $entityManager->flush();

            $status = $schoolGroup->isActive() ? 'activé' : 'désactivé';
            $this->addFlash('success', "Le groupe a été {$status} avec succès.");
        }

        return $this->redirectToRoute('admin_school_group_index', [], Response::HTTP_SEE_OTHER);
    }
}

