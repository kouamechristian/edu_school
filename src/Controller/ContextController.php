<?php

namespace App\Controller;

use App\Entity\School;
use App\Entity\SchoolYear;
use App\Service\SchoolContextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/context', name: 'context_')]
#[IsGranted('ROLE_USER')]
class ContextController extends AbstractController
{
    #[Route('/switch-school/{id}', name: 'switch_school', methods: ['GET'])]
    public function switchSchool(School $school, SchoolContextService $contextService): Response
    {
        // L'utilisateur ne peut basculer que vers un établissement auquel il est rattaché.
        if (!$contextService->isSchoolAllowed($school)) {
            $this->addFlash('error', "Vous n'avez pas accès à cet établissement.");
            return $this->redirectToRoute('app_home');
        }

        $contextService->setCurrentSchool($school);

        $this->addFlash('success', "Vous avez basculé vers l'établissement : {$school->getName()}");

        return $this->redirectToRoute('app_home');
    }

    #[Route('/switch-year/{id}', name: 'switch_year', methods: ['GET'])]
    public function switchYear(SchoolYear $schoolYear, SchoolContextService $contextService): Response
    {
        $contextService->setCurrentSchoolYear($schoolYear);
        
        $this->addFlash('success', "Vous avez basculé vers l'année scolaire : {$schoolYear->getName()}");
        
        return $this->redirectToRoute('app_home');
    }
}

