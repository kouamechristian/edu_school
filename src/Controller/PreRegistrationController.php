<?php

namespace App\Controller;

use App\Entity\PreRegistration;
use App\Entity\DocumentType;
use App\Form\PreRegistrationType;
use App\Repository\PreRegistrationRepository;
use App\Repository\DocumentTypeRepository;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/pre-registrations', name: 'admin_pre_registration_')]
#[IsGranted('ROLE_ADMIN')]
class PreRegistrationController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        PreRegistrationRepository $preRegistrationRepository,
        SchoolContextService $contextService
    ): Response {
        // Récupérer l'établissement courant
        $currentSchool = $contextService->getCurrentSchool();
        
        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour voir les préinscriptions.');
            return $this->render('pre_registration/index.html.twig', [
                'pre_registrations' => [],
                'stats' => [],
                'current_status' => null,
                'search_term' => null,
                'current_school' => null,
            ]);
        }

        $schoolId = $currentSchool->getId();

        // Filtres
        $status = $request->query->get('status');
        $search = $request->query->get('search');

        // Filtrer les préinscriptions selon l'établissement sélectionné
        if ($search) {
            $preRegistrations = $preRegistrationRepository->searchByName($search, $schoolId);
        } elseif ($status) {
            $preRegistrations = $preRegistrationRepository->findBySchoolAndStatus($schoolId, $status);
        } else {
            $preRegistrations = $preRegistrationRepository->findBySchool($schoolId);
        }

        // Statistiques filtrées par établissement
        $stats = $preRegistrationRepository->countByStatusInSchool($schoolId);

        return $this->render('pre_registration/index.html.twig', [
            'pre_registrations' => $preRegistrations,
            'stats' => $stats,
            'current_status' => $status,
            'search_term' => $search,
            'current_school' => $currentSchool,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        SchoolContextService $contextService
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();
        $currentSchoolYear = $contextService->getCurrentSchoolYear();
        
        if (!$currentSchool) {
            $this->addFlash('error', 'Veuillez sélectionner un établissement pour créer une préinscription.');
            return $this->redirectToRoute('admin_pre_registration_index');
        }

        $preRegistration = new PreRegistration();
        $preRegistration->setSchool($currentSchool);
        
        // Définir l'année scolaire en cours par défaut
        if ($currentSchoolYear) {
            $preRegistration->setSchoolYear($currentSchoolYear);
        }
        
        // Récupérer les niveaux de l'établissement pour le formulaire
        $levels = $entityManager->getRepository(\App\Entity\Level::class)
            ->findBy(['school' => $currentSchool], ['name' => 'ASC']);

        // Récupérer les années scolaires (priorité à l'année en cours)
        $schoolYears = $entityManager->getRepository(\App\Entity\SchoolYear::class)
            ->findBy([], ['startDate' => 'DESC']);
        
        // Mettre l'année scolaire en cours en premier
        if ($currentSchoolYear) {
            $schoolYears = array_filter($schoolYears, fn($sy) => $sy->getId() !== $currentSchoolYear->getId());
            array_unshift($schoolYears, $currentSchoolYear);
        }

        // Créer le formulaire avec les options personnalisées
        $form = $this->createForm(PreRegistrationType::class, $preRegistration, [
            'levels' => $levels,
            'school_years' => $schoolYears,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($preRegistration);
            $entityManager->flush();

            $this->addFlash('success', 'La préinscription a été créée avec succès.');

            return $this->redirectToRoute('admin_pre_registration_show', ['id' => $preRegistration->getId()]);
        }

        return $this->render('pre_registration/new.html.twig', [
            'pre_registration' => $preRegistration,
            'form' => $form,
            'current_school_year' => $currentSchoolYear,
            'levels' => $levels,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(
        PreRegistration $preRegistration,
        EntityManagerInterface $entityManager
    ): Response {
        // Récupérer les types de documents pour le modal d'upload
        $documentTypes = $entityManager->getRepository(\App\Entity\DocumentType::class)
            ->findBy([], ['name' => 'ASC']);

        return $this->render('pre_registration/show.html.twig', [
            'pre_registration' => $preRegistration,
            'document_types' => $documentTypes,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        PreRegistration $preRegistration,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(PreRegistrationType::class, $preRegistration);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'La préinscription a été modifiée avec succès.');

            return $this->redirectToRoute('admin_pre_registration_show', ['id' => $preRegistration->getId()]);
        }

        return $this->render('pre_registration/edit.html.twig', [
            'pre_registration' => $preRegistration,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/validate', name: 'validate', methods: ['POST'])]
    public function validate(
        PreRegistration $preRegistration,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$preRegistration->canBeValidated()) {
            $this->addFlash('error', 'Cette préinscription ne peut pas être validée dans son état actuel.');
            return $this->redirectToRoute('admin_pre_registration_show', ['id' => $preRegistration->getId()]);
        }

        $preRegistration->setStatus('validated');
        $preRegistration->setValidatedAt(new \DateTime());
        $preRegistration->setValidatedBy($this->getUser());

        $entityManager->flush();

        $this->addFlash('success', 'La préinscription a été validée avec succès.');

        return $this->redirectToRoute('admin_pre_registration_show', ['id' => $preRegistration->getId()]);
    }

    #[Route('/{id}/reject', name: 'reject', methods: ['POST'])]
    public function reject(
        Request $request,
        PreRegistration $preRegistration,
        EntityManagerInterface $entityManager
    ): Response {
        $rejectionReason = $request->request->get('rejection_reason');
        
        if (empty($rejectionReason)) {
            $this->addFlash('error', 'Veuillez fournir une raison de rejet.');
            return $this->redirectToRoute('admin_pre_registration_show', ['id' => $preRegistration->getId()]);
        }

        $preRegistration->setStatus('rejected');
        $preRegistration->setRejectionReason($rejectionReason);
        $preRegistration->setValidatedBy($this->getUser());

        $entityManager->flush();

        $this->addFlash('success', 'La préinscription a été rejetée.');

        return $this->redirectToRoute('admin_pre_registration_show', ['id' => $preRegistration->getId()]);
    }

    #[Route('/{id}/enroll', name: 'enroll', methods: ['POST'])]
    public function enroll(
        PreRegistration $preRegistration,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$preRegistration->canBeEnrolled()) {
            $this->addFlash('error', 'Cette préinscription ne peut pas être inscrite dans son état actuel.');
            return $this->redirectToRoute('admin_pre_registration_show', ['id' => $preRegistration->getId()]);
        }

        // Créer l'étudiant à partir de la préinscription
        $student = new \App\Entity\Student();
        $student->setFirstName($preRegistration->getFirstName());
        $student->setLastName($preRegistration->getLastName());
        $student->setDateOfBirth($preRegistration->getDateOfBirth());
        $student->setGender($preRegistration->getGender());
        $student->setPhone($preRegistration->getPhone());
        $student->setAddress($preRegistration->getAddress());
        $student->setParentName($preRegistration->getParentName());
        $student->setParentPhone($preRegistration->getParentPhone());
        $student->setParentEmail($preRegistration->getParentEmail());
        $student->setEmergencyContact($preRegistration->getEmergencyContact());
        $student->setEmergencyPhone($preRegistration->getEmergencyPhone());
        $student->setMedicalInfo($preRegistration->getMedicalInfo());
        $student->setNotes($preRegistration->getNotes());
        $student->setSchool($preRegistration->getSchool());
        $student->setLevel($preRegistration->getRequestedLevel());
        $student->setSchoolYear($preRegistration->getSchoolYear());

        $entityManager->persist($student);

        // Mettre à jour la préinscription
        $preRegistration->setStatus('enrolled');
        $preRegistration->setEnrolledAt(new \DateTime());
        $preRegistration->setStudent($student);

        $entityManager->flush();

        $this->addFlash('success', 'L\'élève a été inscrit avec succès.');

        return $this->redirectToRoute('admin_student_show', ['id' => $student->getId()]);
    }

    #[Route('/{id}/change-status/{status}', name: 'change_status', methods: ['POST'])]
    public function changeStatus(
        PreRegistration $preRegistration,
        string $status,
        EntityManagerInterface $entityManager
    ): Response {
        // Vérifier que le statut est valide
        $validStatuses = ['pending', 'documents_required', 'documents_received', 'validated', 'rejected', 'enrolled'];
        if (!in_array($status, $validStatuses)) {
            $this->addFlash('error', 'Statut invalide.');
            return $this->redirectToRoute('admin_pre_registration_show', ['id' => $preRegistration->getId()]);
        }

        // Vérifier les transitions autorisées
        $currentStatus = $preRegistration->getStatus();
        $allowedTransitions = [
            'pending' => ['documents_required', 'rejected'],
            'documents_required' => ['documents_received', 'rejected'],
            'documents_received' => ['validated', 'rejected'],
            'validated' => ['enrolled', 'rejected'],
            'rejected' => ['pending'],
            'enrolled' => [] // Aucune transition possible depuis enrolled
        ];

        if (!in_array($status, $allowedTransitions[$currentStatus] ?? [])) {
            $this->addFlash('error', 'Transition de statut non autorisée.');
            return $this->redirectToRoute('admin_pre_registration_show', ['id' => $preRegistration->getId()]);
        }

        $preRegistration->setStatus($status);
        
        // Mettre à jour les dates selon le statut
        if ($status === 'validated') {
            $preRegistration->setValidatedAt(new \DateTime());
            $preRegistration->setValidatedBy($this->getUser());
        } elseif ($status === 'enrolled') {
            $preRegistration->setEnrolledAt(new \DateTime());
        }

        $entityManager->flush();

        $statusLabels = [
            'pending' => 'En attente',
            'documents_required' => 'Documents requis',
            'documents_received' => 'Documents reçus',
            'validated' => 'Validée',
            'rejected' => 'Rejetée',
            'enrolled' => 'Inscrite'
        ];

        $this->addFlash('success', "Le statut a été changé vers '{$statusLabels[$status]}' avec succès.");

        return $this->redirectToRoute('admin_pre_registration_show', ['id' => $preRegistration->getId()]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(
        Request $request,
        PreRegistration $preRegistration,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('delete'.$preRegistration->getId(), $request->request->get('_token'))) {
            $entityManager->remove($preRegistration);
            $entityManager->flush();

            $this->addFlash('success', 'La préinscription a été supprimée avec succès.');
        }

        return $this->redirectToRoute('admin_pre_registration_index');
    }
}
