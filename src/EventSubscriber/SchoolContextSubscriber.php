<?php

namespace App\EventSubscriber;

use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class SchoolContextSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private SchoolContextService $contextService,
        private Environment $twig,
        private EntityManagerInterface $entityManager
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $currentSchool = $this->contextService->getCurrentSchool();
        $currentSchoolYear = $this->contextService->getCurrentSchoolYear();

        // Activer le filtre Doctrine si un établissement est sélectionné
        if ($currentSchool) {
            $filter = $this->entityManager->getFilters()->enable('school_filter');
            $filter->setParameter('school_id', $currentSchool->getId());
        }

        // Injecter les variables globales dans Twig
        $this->twig->addGlobal('current_school', $currentSchool);
        $this->twig->addGlobal('current_school_year', $currentSchoolYear);
        $this->twig->addGlobal('available_schools', $this->contextService->getAvailableSchools());
        $this->twig->addGlobal('available_school_years', $this->contextService->getAvailableSchoolYears());
    }
}


