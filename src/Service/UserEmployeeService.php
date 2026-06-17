<?php

namespace App\Service;

use App\Entity\Employee;
use App\Entity\Teacher;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class UserEmployeeService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Crée automatiquement un employé lors de la création d'un utilisateur
     */
    public function createEmployeeForUser(User $user): Employee
    {
        // Idempotence : ne jamais créer un second Employee pour le même User
        // (la colonne employee.user_id est unique).
        if ($existing = $user->getEmployee()) {
            return $existing;
        }

        $employee = new Employee();
        $employee->setUser($user);
        $employee->setFirstName($user->getFirstName() ?? '');
        $employee->setLastName($user->getLastName() ?? '');
        $employee->setPhone($user->getPhone());
        $employee->setAddress($user->getAddress());
        $employee->setDateOfBirth($user->getDateOfBirth());
        $employee->setGender($user->getGender());
        
        // Déterminer le type d'employé basé sur le type d'utilisateur
        $employeeType = match($user->getUserType()) {
            'directeur' => 'directeur',
            'enseignant' => 'enseignant',
            'personnel' => 'personnel',
            default => 'personnel'
        };
        
        $employee->setEmployeeType($employeeType);
        $employee->setIsActive($user->isActive());
        
        // Copier les écoles de l'utilisateur vers l'employé
        foreach ($user->getSchools() as $school) {
            $employee->addSchool($school);
        }

        $this->entityManager->persist($employee);
        
        // Si c'est un enseignant, créer aussi l'entité Teacher
        if ($employeeType === 'enseignant') {
            $this->createTeacherForEmployee($employee);
        }

        return $employee;
    }

    /**
     * Crée automatiquement un enseignant lors de la création d'un employé de type enseignant
     */
    public function createTeacherForEmployee(Employee $employee): Teacher
    {
        $teacher = new Teacher();
        $teacher->setEmployee($employee);
        
        $this->entityManager->persist($teacher);
        
        return $teacher;
    }

    /**
     * Met à jour un employé lors de la modification d'un utilisateur
     */
    public function updateEmployeeFromUser(User $user): ?Employee
    {
        $employee = $user->getEmployee();
        
        if (!$employee) {
            return $this->createEmployeeForUser($user);
        }

        $employee->setFirstName($user->getFirstName() ?? '');
        $employee->setLastName($user->getLastName() ?? '');
        $employee->setPhone($user->getPhone());
        $employee->setAddress($user->getAddress());
        $employee->setDateOfBirth($user->getDateOfBirth());
        $employee->setGender($user->getGender());
        $employee->setIsActive($user->isActive());

        // Mettre à jour le type d'employé si nécessaire
        $newEmployeeType = match($user->getUserType()) {
            'directeur' => 'directeur',
            'enseignant' => 'enseignant',
            'personnel' => 'personnel',
            default => 'personnel'
        };

        $oldEmployeeType = $employee->getEmployeeType();
        $employee->setEmployeeType($newEmployeeType);

        // Si le type a changé vers enseignant et qu'il n'y a pas encore de Teacher, en créer un
        if ($newEmployeeType === 'enseignant' && $oldEmployeeType !== 'enseignant' && !$employee->getTeacher()) {
            $this->createTeacherForEmployee($employee);
        }

        // Si le type a changé et qu'il y avait un Teacher, le supprimer
        if ($newEmployeeType !== 'enseignant' && $oldEmployeeType === 'enseignant' && $employee->getTeacher()) {
            $this->entityManager->remove($employee->getTeacher());
        }

        return $employee;
    }

    /**
     * Synchronise les écoles entre User et Employee
     */
    public function syncSchoolsBetweenUserAndEmployee(User $user): void
    {
        $employee = $user->getEmployee();
        
        if (!$employee) {
            return;
        }

        // Vider les écoles de l'employé
        foreach ($employee->getSchools() as $school) {
            $employee->removeSchool($school);
        }

        // Ajouter les écoles de l'utilisateur à l'employé
        foreach ($user->getSchools() as $school) {
            $employee->addSchool($school);
        }
    }
}
