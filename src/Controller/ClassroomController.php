<?php

namespace App\Controller;

use App\Entity\Classroom;
use App\Form\ClassroomType;
use App\Repository\ClassroomRepository;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/classrooms', name: 'admin_classroom_')]
#[IsGranted('ROLE_ADMIN')]
class ClassroomController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(ClassroomRepository $classroomRepository, SchoolContextService $contextService): Response
    {
        $currentSchool = $contextService->getCurrentSchool();
        $currentSchoolYear = $contextService->getCurrentSchoolYear();
        
        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour voir les classes.');
            return $this->render('classroom/index.html.twig', [
                'classrooms' => [],
                'current_school' => null,
            ]);
        }

        $schoolId = $currentSchool->getId();
        $yearId = $currentSchoolYear?->getId();

        $classrooms = $classroomRepository->findBySchoolAndYear($schoolId, $yearId);

        return $this->render('classroom/index.html.twig', [
            'classrooms' => $classrooms,
            'current_school' => $currentSchool,
            'current_school_year' => $currentSchoolYear,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SchoolContextService $contextService, ClassroomRepository $classroomRepository): Response
    {
        $currentSchool = $contextService->getCurrentSchool();
        $currentSchoolYear = $contextService->getCurrentSchoolYear();
        
        $classroom = new Classroom();
        
        if ($currentSchool) {
            $classroom->setSchool($currentSchool);
        }
        
        if ($currentSchoolYear) {
            $classroom->setSchoolYear($currentSchoolYear);
        }
        
        $form = $this->createForm(ClassroomType::class, $classroom);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $classroom->setCode($this->generateClassroomCode($classroom, $classroomRepository));
            $entityManager->persist($classroom);
            $entityManager->flush();

            $this->addFlash('success', 'La classe a été créée avec succès.');

            return $this->redirectToRoute('admin_classroom_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('classroom/new.html.twig', [
            'classroom' => $classroom,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/show', name: 'show', methods: ['GET'])]
    public function show(Classroom $classroom): Response
    {
        return $this->render('classroom/show.html.twig', [
            'classroom' => $classroom,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Classroom $classroom, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ClassroomType::class, $classroom);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'La classe a été modifiée avec succès.');

            return $this->redirectToRoute('admin_classroom_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('classroom/edit.html.twig', [
            'classroom' => $classroom,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Classroom $classroom, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$classroom->getId(), $request->request->get('_token'))) {
            $entityManager->remove($classroom);
            $entityManager->flush();

            $this->addFlash('success', 'La classe a été supprimée avec succès.');
        }

        return $this->redirectToRoute('admin_classroom_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(Request $request, Classroom $classroom, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('toggle'.$classroom->getId(), $request->request->get('_token'))) {
            $classroom->setIsActive(!$classroom->isActive());
            $entityManager->flush();

            $status = $classroom->isActive() ? 'activée' : 'désactivée';
            $this->addFlash('success', "La classe a été {$status} avec succès.");
        }

        return $this->redirectToRoute('admin_classroom_index', [], Response::HTTP_SEE_OTHER);
    }

    private function generateClassroomCode(Classroom $classroom, ClassroomRepository $classroomRepository): string
    {
        $levelName = $classroom->getLevel()?->getName() ?? 'CLS';
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $levelName), 0, 4));

        if (empty($prefix)) {
            $prefix = 'CLS';
        }

        $count = $classroomRepository->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.code LIKE :prefix')
            ->setParameter('prefix', $prefix . '-%')
            ->getQuery()
            ->getSingleScalarResult();

        do {
            $count++;
            $code = sprintf('%s-%04d', $prefix, $count);
            $exists = $classroomRepository->findOneBy(['code' => $code]);
        } while ($exists);

        return $code;
    }
}

