<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Centre d'aide « comment faire », module par module.
 *
 * Page publique et autonome (comme la documentation) : chaque module regroupe
 * des tâches concrètes décrites en étapes, illustrées d'une capture d'écran.
 *
 * Les captures vivent dans `public/images/support/<slug>-<n>.png`. Tant qu'un
 * fichier n'existe pas, la vue affiche un emplacement réservé : déposer l'image
 * suffit à la faire apparaître, sans toucher au code (cf. `existsScreenshot()`).
 */
#[Route('/support')]
class SupportController extends AbstractController
{
    /**
     * Modules → tâches. Chaque tâche : titre, étapes, astuce facultative et
     * base du nom de capture (le fichier attendu est `<screenshot>.png`).
     */
    private const MODULES = [
        'eleves' => [
            'title' => 'Gestion des élèves',
            'icon' => 'fa-user-graduate',
            'abstract' => "Inscrire, affecter, transférer et suivre les élèves.",
            'tasks' => [
                [
                    'title' => 'Inscrire un nouvel élève',
                    'screenshot' => 'eleves-1',
                    'steps' => [
                        'Ouvrez <em>Élèves → Préinscriptions</em> puis cliquez sur <strong>Nouvelle</strong>.',
                        'Choisissez « nouvel élève » et renseignez l\'identité (nom, date de naissance, sexe, parents).',
                        'Sélectionnez le niveau demandé et joignez les pièces exigées.',
                        'Enregistrez : la demande passe au statut <em>en attente de validation</em>.',
                    ],
                    'tip' => "Pour un ancien élève, choisissez « ancien élève » et recherchez-le par matricule : sa fiche est réutilisée sans doublon.",
                ],
                [
                    'title' => 'Valider une préinscription',
                    'screenshot' => 'eleves-2',
                    'steps' => [
                        'Dans <em>Élèves → Préinscriptions</em>, ouvrez la demande à traiter.',
                        'Vérifiez le dossier et les pièces jointes.',
                        'Cliquez sur <strong>Valider</strong>.',
                        'La validation crée l\'inscription, génère le matricule et rattache les frais du niveau. Le parent est notifié.',
                    ],
                    'tip' => "La validation est irréversible côté frais : contrôlez le niveau avant de valider, c'est lui qui détermine les montants dus.",
                ],
                [
                    'title' => 'Affecter un élève à une classe',
                    'screenshot' => 'eleves-3',
                    'steps' => [
                        'Ouvrez <em>Élèves → Affectation dans une classe</em>.',
                        'Les inscrits sans classe apparaissent dans la liste.',
                        'Sélectionnez la classe puis cochez les élèves à y placer.',
                        'Validez : l\'affectation peut se faire en masse.',
                    ],
                    'tip' => "Un élève non affecté n'apparaît pas dans les bulletins de classe : pensez à l'affecter avant l'édition des bulletins.",
                ],
            ],
        ],
        'notes' => [
            'title' => 'Notes et bulletins',
            'icon' => 'fa-clipboard-list',
            'abstract' => "Saisir les notes, valider, publier et éditer les bulletins.",
            'tasks' => [
                [
                    'title' => 'Créer une évaluation et saisir les notes',
                    'screenshot' => 'notes-1',
                    'steps' => [
                        'Ouvrez <em>Notes &amp; Évaluations → Évaluations</em> puis <strong>Nouvelle</strong>.',
                        'Choisissez la classe, la matière, la période, le type et la note maximale.',
                        'Enregistrez, puis ouvrez la grille de saisie.',
                        'Saisissez la note de chaque élève et enregistrez.',
                    ],
                    'tip' => "Une évaluation restée en brouillon n'entre dans aucun calcul de moyenne.",
                ],
                [
                    'title' => 'Valider puis publier les notes',
                    'screenshot' => 'notes-2',
                    'steps' => [
                        'Depuis la liste des évaluations, ouvrez celle à traiter.',
                        'Contrôlez les notes puis cliquez sur <strong>Valider</strong> : elles entrent dans les moyennes administratives.',
                        'Quand tout est prêt pour les familles, cliquez sur <strong>Publier</strong>.',
                        'Les parents et élèves voient alors les notes publiées.',
                    ],
                    'tip' => "Administration = notes validées ; parents = notes publiées. Un écart signifie simplement que la publication n'a pas encore été faite.",
                ],
                [
                    'title' => 'Éditer un bulletin',
                    'screenshot' => 'notes-3',
                    'steps' => [
                        'Ouvrez <em>Notes &amp; Évaluations → Bulletins</em>.',
                        'Créez le bulletin (niveau, période, base de notation) ou ouvrez-en un existant.',
                        'La liste des élèves du niveau s\'affiche avec leurs moyennes recalculées.',
                        'Générez les PDF, individuellement ou pour toute la classe.',
                    ],
                    'tip' => "Régularisez les absences avant l'édition : leur nombre est repris sur le bulletin.",
                ],
            ],
        ],
        'finances' => [
            'title' => 'Gestion financière',
            'icon' => 'fa-money-bill-wave',
            'abstract' => "Ouvrir la caisse, encaisser, annuler, verser en banque.",
            'tasks' => [
                [
                    'title' => 'Ouvrir sa caisse',
                    'screenshot' => 'finances-1',
                    'steps' => [
                        'Ouvrez <em>Finances → Ma caisse</em>.',
                        'Saisissez le fonds de caisse initial.',
                        'Validez l\'ouverture : les encaissements de la journée s\'y rattacheront.',
                    ],
                    'tip' => "Aucun paiement n'est possible sans caisse ouverte. Si le bouton d'encaissement manque, c'est presque toujours la cause.",
                ],
                [
                    'title' => 'Encaisser un paiement',
                    'screenshot' => 'finances-2',
                    'steps' => [
                        'Ouvrez <em>Finances → Paiements</em> et recherchez l\'élève par nom ou matricule.',
                        'Sa situation s\'affiche : frais dus, réglés, solde restant.',
                        'Choisissez le frais, le montant et le mode de paiement.',
                        'Validez : le reçu numéroté est généré en PDF.',
                    ],
                    'tip' => "Les paiements partiels sont acceptés : le solde reste dû et apparaît dans le module Recouvrement.",
                ],
                [
                    'title' => 'Annuler un paiement erroné',
                    'screenshot' => 'finances-3',
                    'steps' => [
                        'Ouvrez le paiement concerné depuis la liste.',
                        'Cliquez sur <strong>Annuler</strong> et indiquez le motif.',
                        'Le paiement bascule dans <em>Paiements annulés</em> et l\'écriture comptable est contre-passée.',
                    ],
                    'tip' => "On n'efface jamais un paiement : on l'annule. La trace reste consultable. Action réservée aux administrateurs.",
                ],
            ],
        ],
        'recouvrement' => [
            'title' => 'Recouvrement',
            'icon' => 'fa-hand-holding-dollar',
            'abstract' => "Suivre les impayés, relancer, gérer les arriérés.",
            'tasks' => [
                [
                    'title' => 'Relancer les impayés',
                    'screenshot' => 'recouvrement-1',
                    'steps' => [
                        'Ouvrez <em>Recouvrement → Recouvrement</em>.',
                        'Filtrez par classe ou par niveau pour cibler.',
                        'Sélectionnez les élèves concernés et déclenchez la relance.',
                        'Les parents sont notifiés ; l\'historique évite les doublons.',
                    ],
                    'tip' => "Vérifiez bourses et exonérations avant de relancer, pour éviter les relances injustifiées.",
                ],
                [
                    'title' => 'Importer les arriérés antérieurs',
                    'screenshot' => 'recouvrement-2',
                    'steps' => [
                        'Ouvrez <em>Recouvrement → Arriérés</em> et téléchargez le modèle Excel.',
                        'Renseignez une ligne par élève : matricule, année, libellé, montant dû.',
                        'Déposez le fichier : un rapport liste les lignes valides et les anomalies.',
                        'Validez pour créer les arriérés.',
                    ],
                    'tip' => "Les arriérés sont payables (Mobile Money compris) mais restent exclus du chiffre d'affaires de l'année en cours.",
                ],
            ],
        ],
        'paiement-en-ligne' => [
            'title' => 'Paiement en ligne (GeniusPay)',
            'icon' => 'fa-credit-card',
            'abstract' => "Activer le règlement en ligne et payer côté parent.",
            'tasks' => [
                [
                    'title' => 'Activer le paiement en ligne d\'un établissement',
                    'screenshot' => 'paiement-en-ligne-1',
                    'steps' => [
                        'Ouvrez <em>Administration → Établissements</em> puis l\'établissement.',
                        'Cliquez sur <strong>Paiement en ligne</strong>.',
                        'Saisissez la clé publique, la clé secrète et le secret de webhook (fournis par GeniusPay).',
                        'Cochez <strong>Activer</strong> et enregistrez.',
                    ],
                    'tip' => "Déclarez chez GeniusPay l'URL de webhook affichée à l'écran, terminée par /webhook/geniuspay. Une URL en localhost ne fonctionnera jamais.",
                ],
                [
                    'title' => 'Payer la scolarité (côté parent)',
                    'screenshot' => 'paiement-en-ligne-2',
                    'steps' => [
                        'Dans l\'espace parent, ouvrez <em>Finances</em> de l\'enfant.',
                        'Choisissez le frais à régler et le montant.',
                        'Cliquez sur <strong>Payer en ligne</strong> : vous êtes redirigé vers la page sécurisée GeniusPay.',
                        'Choisissez Wave, Orange, MTN ou carte, puis confirmez.',
                    ],
                    'tip' => "Le solde est crédité automatiquement après confirmation. Un paiement « en cours de validation » se débloque seul.",
                ],
            ],
        ],
        'rh-paie' => [
            'title' => 'RH et paie',
            'icon' => 'fa-id-badge',
            'abstract' => "Employés, contrats et bulletins de salaire.",
            'tasks' => [
                [
                    'title' => 'Générer la paie d\'un mois',
                    'screenshot' => 'rh-paie-1',
                    'steps' => [
                        'Ouvrez <em>Ressources Humaines → Paie</em> et créez la période (mois).',
                        'Lancez la génération : un bulletin par employé sous contrat actif est produit.',
                        'Contrôlez chaque bulletin ; ajoutez les rubriques exceptionnelles (primes, avances).',
                        'Validez la période, puis éditez les PDF et le livre de paie.',
                    ],
                    'tip' => "Vérifiez les taux CNPS/ITS dans les paramètres de paie au début de chaque exercice.",
                ],
            ],
        ],
        'absences' => [
            'title' => 'Absences',
            'icon' => 'fa-calendar-check',
            'abstract' => "Saisir, justifier et suivre l'assiduité.",
            'tasks' => [
                [
                    'title' => 'Saisir des absences',
                    'screenshot' => 'absences-1',
                    'steps' => [
                        'Ouvrez <em>Gestion → Absences → Nouvelle absence</em>.',
                        'Choisissez la classe, la date et le créneau éventuel.',
                        'Cochez les élèves absents et indiquez le type.',
                        'Enregistrez : les parents concernés sont notifiés.',
                    ],
                    'tip' => "Créez vos types d'absence avant de saisir : le type est obligatoire.",
                ],
            ],
        ],
        'espaces' => [
            'title' => 'Espaces parent et élève',
            'icon' => 'fa-users-rectangle',
            'abstract' => "Connexion et usage des portails dédiés.",
            'tasks' => [
                [
                    'title' => 'Créer un compte parent',
                    'screenshot' => 'espaces-1',
                    'steps' => [
                        'Le parent se rend sur la page publique <code>/parent/inscription</code>.',
                        'Il saisit le matricule national de son enfant.',
                        'L\'application retrouve l\'élève et le rattache au nouveau compte.',
                        'La connexion se fait ensuite par numéro de téléphone.',
                    ],
                    'tip' => "L'administration peut aussi créer les comptes parents depuis Administration → Comptes Parents.",
                ],
            ],
        ],
    ];

    #[Route('', name: 'app_support')]
    public function index(): Response
    {
        return $this->render('support/index.html.twig', [
            'modules' => $this->summary(),
        ]);
    }

    #[Route('/{slug}', name: 'app_support_module', requirements: ['slug' => '[a-z0-9\-]+'])]
    public function module(string $slug): Response
    {
        if (!isset(self::MODULES[$slug])) {
            throw $this->createNotFoundException(sprintf('Module d\'aide « %s » introuvable.', $slug));
        }

        $slugs = array_keys(self::MODULES);
        $position = array_search($slug, $slugs, true);

        return $this->render('support/module.html.twig', [
            'modules' => $this->summary(),
            'module' => $this->resolveModule($slug),
            'previous' => $position > 0 ? $this->summaryEntry($slugs[$position - 1]) : null,
            'next' => $position < count($slugs) - 1 ? $this->summaryEntry($slugs[$position + 1]) : null,
        ]);
    }

    /**
     * Sommaire léger (sans les tâches) pour le menu et la grille d'accueil.
     *
     * @return array<string, array{slug: string, title: string, icon: string, abstract: string, task_count: int}>
     */
    private function summary(): array
    {
        $summary = [];
        foreach (self::MODULES as $slug => $module) {
            $summary[$slug] = $this->summaryEntry($slug);
        }

        return $summary;
    }

    /**
     * @return array{slug: string, title: string, icon: string, abstract: string, task_count: int}
     */
    private function summaryEntry(string $slug): array
    {
        $module = self::MODULES[$slug];

        return [
            'slug' => $slug,
            'title' => $module['title'],
            'icon' => $module['icon'],
            'abstract' => $module['abstract'],
            'task_count' => count($module['tasks']),
        ];
    }

    /**
     * Module complet, chaque tâche enrichie de l'URL de capture et d'un drapeau
     * indiquant si le fichier existe réellement.
     */
    private function resolveModule(string $slug): array
    {
        $module = self::MODULES[$slug];
        $module['slug'] = $slug;

        foreach ($module['tasks'] as $index => $task) {
            $file = 'images/support/' . $task['screenshot'] . '.png';
            $module['tasks'][$index]['screenshot_url'] = $file;
            $module['tasks'][$index]['screenshot_exists'] = $this->existsScreenshot($file);
        }

        return $module;
    }

    private function existsScreenshot(string $relativePath): bool
    {
        return is_file($this->getParameter('kernel.project_dir') . '/public/' . $relativePath);
    }
}
