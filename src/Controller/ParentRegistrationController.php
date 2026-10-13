<?php

namespace App\Controller;

use App\Service\ParentRegistrationException;
use App\Service\ParentRegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Auto-inscription d'un parent (accès public).
 *
 * Un parent qui n'a pas encore de compte le crée depuis la page de connexion en
 * fournissant son identité et le matricule national de son enfant. Les règles
 * (validation, rattachement à l'élève et à l'établissement) sont portées par
 * {@see ParentRegistrationService}, partagé avec l'application mobile.
 */
class ParentRegistrationController extends AbstractController
{
    #[Route('/parent/inscription', name: 'parent_register', methods: ['GET', 'POST'])]
    public function register(Request $request, ParentRegistrationService $registration): Response
    {
        // Un utilisateur déjà connecté n'a pas à s'inscrire.
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        // Valeurs ressaisies en cas d'erreur (jamais le mot de passe).
        $old = [
            'first_name' => '',
            'last_name' => '',
            'phone' => '',
            'matricule_national' => '',
        ];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('parent_register', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

                return $this->redirectToRoute('parent_register');
            }

            $old = [
                'first_name' => trim((string) $request->request->get('first_name')),
                'last_name' => trim((string) $request->request->get('last_name')),
                'phone' => trim((string) $request->request->get('phone')),
                'matricule_national' => trim((string) $request->request->get('matricule_national')),
            ];

            try {
                $result = $registration->register(
                    $old['first_name'],
                    $old['last_name'],
                    $old['phone'],
                    (string) $request->request->get('password'),
                    $old['matricule_national'],
                );

                $this->addFlash('success', sprintf(
                    'Votre compte a été créé et relié à %s. Connectez-vous avec votre numéro de téléphone et votre mot de passe.',
                    $result['child']->getFullName()
                ));

                // Pré-remplit l'identifiant sur la page de connexion parent.
                return $this->redirectToRoute('parent_login', ['username' => $old['phone']]);
            } catch (ParentRegistrationException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('security/parent_register.html.twig', [
            'old' => $old,
        ]);
    }
}
