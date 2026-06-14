<?php

namespace App\Controller;

use App\Controller\Concern\HandlesEntityDeletion;
use App\Entity\StudentDropout;
use App\Form\StudentDropoutType;
use App\Repository\StudentDropoutRepository;
use App\Repository\StudentRepository;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/abandons', name: 'admin_dropout_')]
#[IsGranted('ROLE_INSCRIPTION')]
class StudentDropoutController extends AbstractController
{
    use HandlesEntityDeletion;

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(StudentDropoutRepository $dropoutRepository, SchoolContextService $contextService): Response
    {
        $school = $contextService->getCurrentSchool();

        return $this->render('student_dropout/index.html.twig', [
            'dropouts' => $dropoutRepository->findBySchool($school?->getId()),
            'current_school' => $school,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        SchoolContextService $contextService,
        StudentRepository $studentRepository
    ): Response {
        $school = $contextService->getCurrentSchool();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour enregistrer un abandon.');
            return $this->redirectToRoute('admin_dropout_index');
        }

        // Élèves actifs de l'établissement courant.
        $students = $studentRepository->createQueryBuilder('s')
            ->leftJoin('s.school', 'school')
            ->where('school.id = :schoolId')
            ->andWhere('s.isActive = :active')
            ->setParameter('schoolId', $school->getId())
            ->setParameter('active', true)
            ->orderBy('s.lastName', 'ASC')
            ->addOrderBy('s.firstName', 'ASC')
            ->getQuery()
            ->getResult();

        $dropout = new StudentDropout();
        $form = $this->createForm(StudentDropoutType::class, $dropout, ['students' => $students]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $dropout->setRecordedBy($this->getUser());
            $dropout->setStatus('enregistré');

            $entityManager->persist($dropout);
            $entityManager->flush();

            $this->addFlash('success', 'L\'abandon a été enregistré. Il reste à valider.');

            return $this->redirectToRoute('admin_dropout_index');
        }

        return $this->render('student_dropout/new.html.twig', [
            'dropout' => $dropout,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/valider', name: 'validate', methods: ['POST'])]
    public function validate(Request $request, StudentDropout $dropout, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('validate'.$dropout->getId(), $request->request->get('_token'))) {
            if ($dropout->isValidated()) {
                $this->addFlash('info', 'Cet abandon est déjà validé.');
            } else {
                $dropout->setStatus('validé')
                    ->setValidatedBy($this->getUser())
                    ->setValidatedAt(new \DateTime());

                // L'élève quitte l'établissement : il est désactivé.
                if ($dropout->getStudent()) {
                    $dropout->getStudent()->setIsActive(false);
                }

                $entityManager->flush();
                $this->addFlash('success', 'L\'abandon a été validé. L\'élève a été retiré des effectifs actifs.');
            }
        }

        return $this->redirectToRoute('admin_dropout_index');
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, StudentDropout $dropout, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$dropout->getId(), $request->request->get('_token'))) {
            // Si l'abandon était validé, on réactive l'élève.
            if ($dropout->isValidated() && $dropout->getStudent()) {
                $dropout->getStudent()->setIsActive(true);
            }

            $this->deleteEntity(
                $entityManager,
                $dropout,
                'L\'abandon a été supprimé avec succès.'
            );
        }

        return $this->redirectToRoute('admin_dropout_index');
    }
}
