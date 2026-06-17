<?php

namespace App\Controller;

use App\Controller\Concern\HandlesEntityDeletion;
use App\Entity\Employee;
use App\Entity\User;
use App\Form\EmployeeType;
use App\Repository\EmployeeRepository;
use App\Repository\UserRepository;
use App\Service\SchoolContextService;
use App\Service\UserEmployeeService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/hr/employees')]
#[IsGranted('ROLE_RH')]
class EmployeeController extends AbstractController
{
    use HandlesEntityDeletion;

    #[Route('/', name: 'admin_employee_index', methods: ['GET'])]
    public function index(
        EmployeeRepository $employeeRepository,
        SchoolContextService $contextService,
        Request $request
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();

        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour voir les employés.');

            return $this->render('employee/index.html.twig', [
                'employees' => [],
                'current_school' => null,
                'counts' => [],
            ]);
        }

        $schoolId = $currentSchool->getId();
        $type = $request->query->get('type');

        if ($type) {
            $employees = $employeeRepository->findByTypeAndSchool($type, $schoolId);
        } else {
            $employees = $employeeRepository->findActiveBySchool($schoolId);
        }

        return $this->render('employee/index.html.twig', [
            'employees' => $employees,
            'current_school' => $currentSchool,
            'counts' => $employeeRepository->countByTypeInSchool($schoolId),
            'type' => $type,
        ]);
    }

    #[Route('/new', name: 'admin_employee_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserEmployeeService $userEmployeeService,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        SluggerInterface $slugger,
        SchoolContextService $contextService
    ): Response {
        $employee = new Employee();

        $form = $this->createForm(EmployeeType::class, $employee, ['include_account' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();

            // Création automatique du compte utilisateur associé à l'employé.
            $user = new User();
            $user->setEmail($email);
            $user->setUsername($this->generateUniqueUsername($email, $employee, $slugger, $userRepository));
            $user->setFirstName($employee->getFirstName());
            $user->setLastName($employee->getLastName());
            $user->setPhone($employee->getPhone());
            $user->setAddress($employee->getAddress());
            $user->setDateOfBirth($employee->getDateOfBirth());
            $user->setGender($employee->getGender());
            $user->setUserType($employee->getEmployeeType());
            $user->setRoles($this->defaultRolesForType($employee->getEmployeeType()));
            $user->setIsActive($employee->isActive());

            // Mot de passe temporaire à changer à la première connexion.
            $tempPassword = bin2hex(random_bytes(5));
            $user->setPassword($passwordHasher->hashPassword($user, $tempPassword));
            $user->setMustChangePassword(true);

            // Rattache l'utilisateur et l'employé à l'établissement courant.
            $currentSchool = $contextService->getCurrentSchool();
            if ($currentSchool) {
                $user->addSchool($currentSchool);
                $employee->addSchool($currentSchool);
            }

            // Lien bidirectionnel : le subscriber User->Employee restera idempotent
            // car l'employé est déjà rattaché au compte.
            $employee->setUser($user);
            $user->setEmployee($employee);

            // Crée l'entité Teacher associée si l'employé est un enseignant.
            if ($employee->getEmployeeType() === 'enseignant' && !$employee->getTeacher()) {
                $userEmployeeService->createTeacherForEmployee($employee);
            }

            $entityManager->persist($user);
            $entityManager->persist($employee);

            try {
                $entityManager->flush();
            } catch (UniqueConstraintViolationException $e) {
                $this->addFlash('error', "Impossible de créer l'employé : un compte existe déjà avec cette adresse e-mail.");

                return $this->redirectToRoute('admin_employee_new', [], Response::HTTP_SEE_OTHER);
            }

            $this->addFlash('success', sprintf(
                'Employé créé avec son compte utilisateur. Identifiant : « %s » · Mot de passe temporaire : « %s » (à changer à la première connexion).',
                $user->getUsername(),
                $tempPassword
            ));

            return $this->redirectToRoute('admin_employee_show', ['id' => $employee->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('employee/new.html.twig', [
            'employee' => $employee,
            'form' => $form,
        ]);
    }

    /**
     * Génère un identifiant de connexion unique à partir de l'e-mail ou du nom.
     */
    private function generateUniqueUsername(
        string $email,
        Employee $employee,
        SluggerInterface $slugger,
        UserRepository $userRepository
    ): string {
        $base = strtolower((string) $slugger->slug(
            strstr($email, '@', true)
                ?: trim($employee->getFirstName() . '.' . $employee->getLastName()),
            '.'
        ));
        $base = $base !== '' ? $base : 'employe';

        $username = $base;
        $suffix = 1;
        while ($userRepository->findOneBy(['username' => $username]) !== null) {
            $username = $base . $suffix;
            $suffix++;
        }

        return $username;
    }

    /**
     * Rôle par défaut attribué au compte créé, selon le type d'employé.
     *
     * @return list<string>
     */
    private function defaultRolesForType(?string $employeeType): array
    {
        return match ($employeeType) {
            'directeur' => ['ROLE_DIRECTEUR'],
            'enseignant' => ['ROLE_ENSEIGNANT'],
            default => ['ROLE_USER'],
        };
    }

    #[Route('/{id}/show', name: 'admin_employee_show', methods: ['GET'])]
    public function show(Employee $employee): Response
    {
        return $this->render('employee/show.html.twig', [
            'employee' => $employee,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_employee_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Employee $employee,
        EntityManagerInterface $entityManager,
        UserEmployeeService $userEmployeeService
    ): Response {
        $previousType = $employee->getEmployeeType();

        $form = $this->createForm(EmployeeType::class, $employee);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Synchronise l'entité Teacher si le type a basculé vers/depuis enseignant.
            $newType = $employee->getEmployeeType();
            if ($newType === 'enseignant' && $previousType !== 'enseignant' && !$employee->getTeacher()) {
                $userEmployeeService->createTeacherForEmployee($employee);
            } elseif ($newType !== 'enseignant' && $previousType === 'enseignant' && $employee->getTeacher()) {
                $entityManager->remove($employee->getTeacher());
            }

            $entityManager->flush();

            $this->addFlash('success', 'La fiche employé a été modifiée avec succès.');

            return $this->redirectToRoute('admin_employee_show', ['id' => $employee->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('employee/edit.html.twig', [
            'employee' => $employee,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/toggle', name: 'admin_employee_toggle', methods: ['POST'])]
    public function toggle(Request $request, Employee $employee, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('toggle' . $employee->getId(), $request->getPayload()->getString('_token'))) {
            $employee->setIsActive(!$employee->isActive());
            $entityManager->flush();

            $status = $employee->isActive() ? 'activé' : 'désactivé';
            $this->addFlash('success', "L'employé a été {$status} avec succès.");
        }

        return $this->redirectToRoute('admin_employee_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/delete', name: 'admin_employee_delete', methods: ['POST'])]
    public function delete(Request $request, Employee $employee, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $employee->getId(), $request->getPayload()->getString('_token'))) {
            $this->deleteEntity(
                $entityManager,
                $employee,
                'La fiche employé a été supprimée avec succès.',
                'Suppression impossible : cet employé est encore lié à d\'autres données (cours, contrats...).'
            );
        }

        return $this->redirectToRoute('admin_employee_index', [], Response::HTTP_SEE_OTHER);
    }
}
