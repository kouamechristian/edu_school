<?php

namespace App\Controller;

use App\Entity\MobileMoneyConfig;
use App\Entity\School;
use App\Form\MobileMoneyConfigType;
use App\Repository\MobileMoneyConfigRepository;
use App\Repository\SchoolRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Configuration des identifiants Mobile Money (passerelle) par établissement.
 */
#[Route('/admin/mobile-money-config', name: 'admin_mobile_money_config_')]
#[IsGranted('ROLE_ADMIN')]
class MobileMoneyConfigController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(SchoolRepository $schoolRepository, MobileMoneyConfigRepository $configRepository): Response
    {
        $configs = [];
        foreach ($configRepository->findAll() as $config) {
            if ($config->getSchool()) {
                $configs[$config->getSchool()->getId()] = $config;
            }
        }

        return $this->render('admin/mobile_money_config/index.html.twig', [
            'schools' => $schoolRepository->findBy([], ['name' => 'ASC']),
            'configs' => $configs,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        School $school,
        Request $request,
        MobileMoneyConfigRepository $configRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $config = $configRepository->findOneBySchool($school) ?? (new MobileMoneyConfig())->setSchool($school);
        $isNew = $config->getId() === null;

        $form = $this->createForm(MobileMoneyConfigType::class, $config);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Secrets non mappés : mis à jour uniquement si une nouvelle valeur est saisie.
            $apiSecret = $form->get('apiSecret')->getData();
            if ($apiSecret) {
                $config->setApiSecret($apiSecret);
            }
            $webhookSecret = $form->get('webhookSecret')->getData();
            if ($webhookSecret) {
                $config->setWebhookSecret($webhookSecret);
            }

            if ($isNew) {
                $entityManager->persist($config);
            }
            $entityManager->flush();

            $this->addFlash('success', sprintf('Identifiants Mobile Money enregistrés pour « %s ».', $school->getName()));

            return $this->redirectToRoute('admin_mobile_money_config_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/mobile_money_config/edit.html.twig', [
            'school' => $school,
            'config' => $config,
            'form' => $form,
        ]);
    }
}
