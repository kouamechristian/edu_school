<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Documentation fonctionnelle et technique de l'application.
 *
 * Chaque rubrique est un template `documentation/sections/<slug>.html.twig`.
 * Le sommaire ci-dessous pilote à la fois le menu latéral et la navigation
 * précédent/suivant : ajouter une rubrique = ajouter une entrée + son template.
 */
#[Route('/documentation')]
class DocumentationController extends AbstractController
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly KernelInterface $kernel,
    ) {
    }

    /**
     * Sommaire : groupes → rubriques (slug, titre, icône, résumé).
     */
    private const SUMMARY = [
        'Prise en main' => [
            ['slug' => 'presentation', 'title' => 'Présentation générale', 'icon' => 'fa-circle-info', 'abstract' => "Ce qu'est EDU-SCHOOL, à qui il s'adresse et de quoi il se compose."],
            ['slug' => 'demarrage', 'title' => 'Démarrage rapide', 'icon' => 'fa-rocket', 'abstract' => "Paramétrer un établissement de zéro, dans le bon ordre."],
            ['slug' => 'roles', 'title' => 'Rôles et permissions', 'icon' => 'fa-user-shield', 'abstract' => "Qui a accès à quoi, et comment les rôles s'héritent."],
        ],
        'Scolarité' => [
            ['slug' => 'eleves', 'title' => 'Gestion des élèves', 'icon' => 'fa-user-graduate', 'abstract' => "Préinscription, inscription, affectation, transfert, exclusion, archives."],
            ['slug' => 'academique', 'title' => 'Académique', 'icon' => 'fa-book', 'abstract' => "Cycles, niveaux, classes, matières, emplois du temps, enseignants."],
            ['slug' => 'notes', 'title' => 'Notes et bulletins', 'icon' => 'fa-clipboard-list', 'abstract' => "Périodes, évaluations, calcul des moyennes et édition des bulletins."],
            ['slug' => 'absences', 'title' => 'Absences et assiduité', 'icon' => 'fa-calendar-check', 'abstract' => "Saisie des absences, types, rapports d'assiduité et élèves à risque."],
        ],
        'Finances' => [
            ['slug' => 'finances', 'title' => 'Gestion financière', 'icon' => 'fa-money-bill-wave', 'abstract' => "Frais, caisse, paiements, dépenses et versements bancaires."],
            ['slug' => 'comptabilite', 'title' => 'Comptabilité', 'icon' => 'fa-book-open-reader', 'abstract' => "Journal, grand livre, plan comptable, rapports et clôtures."],
            ['slug' => 'recouvrement', 'title' => 'Recouvrement', 'icon' => 'fa-hand-holding-dollar', 'abstract' => "Suivi des impayés, relances et arriérés d'années antérieures."],
            ['slug' => 'rh-paie', 'title' => 'RH et paie', 'icon' => 'fa-id-badge', 'abstract' => "Employés, contrats, rubriques de paie, bulletins de salaire."],
        ],
        'Espaces et accès' => [
            ['slug' => 'espaces', 'title' => 'Espaces dédiés', 'icon' => 'fa-users-rectangle', 'abstract' => "Portails parent, élève, enseignant et fondateur."],
            ['slug' => 'mobile', 'title' => 'Application mobile', 'icon' => 'fa-mobile-screen-button', 'abstract' => "ed_photo : photos des élèves par matricule, via l'API REST."],
        ],
        'Référence technique' => [
            ['slug' => 'architecture', 'title' => 'Architecture technique', 'icon' => 'fa-diagram-project', 'abstract' => "Stack, organisation du code, modèle de données, services métier."],
            ['slug' => 'exploitation', 'title' => 'Exploitation', 'icon' => 'fa-screwdriver-wrench', 'abstract' => "Installation, commandes utiles, sauvegardes et déploiement."],
            ['slug' => 'faq', 'title' => 'Questions fréquentes', 'icon' => 'fa-circle-question', 'abstract' => "Les blocages les plus courants et leur résolution."],
        ],
    ];

    #[Route('', name: 'app_documentation')]
    public function index(): Response
    {
        return $this->render('documentation/index.html.twig', [
            'summary' => self::SUMMARY,
        ]);
    }

    /**
     * Index de recherche, consommé par le champ de recherche de la documentation.
     *
     * Le chemin contient « _ » et « . », que la contrainte de `slug` n'accepte pas :
     * aucune collision possible avec la route des rubriques ci-dessous.
     */
    #[Route('/_recherche.json', name: 'app_documentation_search', methods: ['GET'])]
    public function searchIndex(): JsonResponse
    {
        // En dev, l'index est reconstruit à chaque appel : sinon toute correction
        // apportée à une rubrique resterait invisible pour la recherche.
        $index = $this->kernel->isDebug()
            ? $this->buildSearchIndex()
            : $this->cache->get('documentation_search_index', function (ItemInterface $item) {
                $item->expiresAfter(86400);

                return $this->buildSearchIndex();
            });

        return new JsonResponse($index);
    }

    #[Route('/{slug}', name: 'app_documentation_section', requirements: ['slug' => '[a-z0-9\-]+'])]
    public function section(string $slug): Response
    {
        $flat = $this->flatSummary();

        if (!isset($flat[$slug])) {
            throw $this->createNotFoundException(sprintf('Rubrique de documentation « %s » introuvable.', $slug));
        }

        $slugs = array_keys($flat);
        $position = array_search($slug, $slugs, true);

        return $this->render('documentation/section.html.twig', [
            'summary' => self::SUMMARY,
            'section' => $flat[$slug],
            'previous' => $position > 0 ? $flat[$slugs[$position - 1]] : null,
            'next' => $position < count($slugs) - 1 ? $flat[$slugs[$position + 1]] : null,
        ]);
    }

    /**
     * Construit l'index de recherche à partir des rubriques réellement rendues.
     *
     * Chaque rubrique est découpée en fragments délimités par ses titres (h2/h3) :
     * un résultat pointe ainsi vers l'ancre exacte, pas seulement vers la page.
     *
     * @return list<array{slug: string, title: string, group: string, anchor: string, heading: string, level: int, text: string}>
     */
    private function buildSearchIndex(): array
    {
        $entries = [];

        foreach ($this->flatSummary() as $section) {
            $html = $this->renderView('documentation/sections/' . $section['slug'] . '.html.twig');

            $document = new \DOMDocument();
            $previous = libxml_use_internal_errors(true);
            // Le fragment n'a ni <html> ni <head> : on force l'encodage, sinon
            // DOMDocument interprète l'UTF-8 comme du latin-1.
            $document->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            $root = $document->getElementsByTagName('div')->item(0);
            if (!$root) {
                continue;
            }

            // Fragment courant : tout ce qui suit un titre, jusqu'au titre suivant.
            $current = [
                'anchor' => '',
                'heading' => $section['title'],
                'level' => 1,
                'text' => '',
            ];
            $flush = function () use (&$current, &$entries, $section): void {
                $text = trim(preg_replace('/\s+/u', ' ', $current['text']));
                if ($text === '' && $current['anchor'] === '') {
                    return;
                }
                $entries[] = [
                    'slug' => $section['slug'],
                    'title' => $section['title'],
                    'group' => $section['group'],
                    'anchor' => $current['anchor'],
                    'heading' => $current['heading'],
                    'level' => $current['level'],
                    'text' => mb_substr($text, 0, 1200),
                ];
            };

            foreach ($root->childNodes as $node) {
                $tag = strtolower($node->nodeName);

                if ($tag === 'h2' || $tag === 'h3') {
                    $flush();
                    $current = [
                        'anchor' => $node instanceof \DOMElement ? $node->getAttribute('id') : '',
                        'heading' => trim($node->textContent),
                        'level' => $tag === 'h2' ? 2 : 3,
                        'text' => '',
                    ];
                    continue;
                }

                $current['text'] .= ' ' . $node->textContent;
            }

            $flush();
        }

        return $entries;
    }

    /**
     * Aplatit le sommaire en `slug => rubrique` pour la recherche et la navigation.
     *
     * @return array<string, array{slug: string, title: string, icon: string, abstract: string, group: string}>
     */
    private function flatSummary(): array
    {
        $flat = [];

        foreach (self::SUMMARY as $group => $sections) {
            foreach ($sections as $section) {
                $section['group'] = $group;
                $flat[$section['slug']] = $section;
            }
        }

        return $flat;
    }
}
