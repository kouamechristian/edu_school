<?php

namespace App\Twig;

use App\Entity\School;
use App\Theme\PortalSchoolResolver;
use App\Theme\SchoolTheme;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Traduit le thème d'un établissement en surcharges des variables CSS, injectées
 * dans le <head> après la feuille de style de la charte :
 *  - layout d'administration : variables --es-* / --bs-* (styles.css) ;
 *  - portails élève, parent, enseignant : variables --pp-* (parent-portal.css).
 *
 * Sans personnalisation, rien n'est émis : le rendu reste celui de la charte par
 * défaut. Toutes les valeurs passent par SchoolTheme::resolve() (couleurs hexa
 * strictes, polices et styles en liste blanche) : la sortie est sûre en HTML brut.
 */
final class SchoolThemeExtension extends AbstractExtension
{
    /** Rayons (petit, normal, grand) par style d'arrondi. */
    private const RADII = [
        'square' => ['0.2rem', '0.3rem', '0.4rem'],
        'soft' => ['0.5rem', '0.75rem', '1rem'],
        'rounded' => ['0.8rem', '1.15rem', '1.6rem'],
    ];

    /** Rayons des portails (normal, petit) : leur charte est plus arrondie. */
    private const PORTAL_RADII = [
        'square' => ['6px', '4px'],
        'soft' => ['20px', '13px'],
        'rounded' => ['26px', '18px'],
    ];

    public function __construct(private readonly PortalSchoolResolver $portalSchoolResolver)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('school_theme_head', $this->renderHead(...), ['is_safe' => ['html']]),
            new TwigFunction('portal_school', $this->portalSchoolResolver->resolve(...)),
            new TwigFunction('portal_theme_head', $this->renderPortalHead(...), ['is_safe' => ['html']]),
        ];
    }

    public function renderHead(?School $school): string
    {
        $theme = $school?->getTheme();
        if ($theme === null || $theme->isDefault()) {
            return '';
        }

        return $this->fontLink($theme) . '<style id="school-theme">' . $this->css($theme) . '</style>';
    }

    public function renderPortalHead(?School $school): string
    {
        $theme = $school?->getTheme();
        if ($theme === null || $theme->isDefault()) {
            return '';
        }

        return $this->fontLink($theme) . '<style id="school-theme">' . $this->portalCss($theme) . '</style>';
    }

    /**
     * Surcharges des portails. Leur charte diffère légèrement de l'administration
     * (fond, arrondis) : seuls les réglages effectivement personnalisés sont émis,
     * les autres gardent les valeurs propres aux portails.
     */
    public function portalCss(SchoolTheme $theme): string
    {
        $custom = $theme->toArray();
        $vars = [];

        $primary = $theme->resolve('primaryColor');
        $accent = $theme->resolve('accentColor');
        // Barre de navigation et bandeau portent du texte blanc : une couleur
        // principale claire y est assombrie pour rester lisible.
        $chrome = $this->luminance($primary) > 0.4 ? $this->mix($primary, '#000000', 0.45) : $primary;

        if (isset($custom['primaryColor'])) {
            $deep = $this->mix($chrome, '#000000', 0.25);
            $night = $this->mix($chrome, '#000000', 0.5);

            $vars += [
                '--pp-navy' => $primary,
                '--pp-navy-rgb' => $this->rgb($primary),
                '--pp-navy-deep' => $deep,
                '--pp-navy-night' => $night,
                '--pp-navy-light' => $this->mix($primary, '#ffffff', 0.15),
                '--pp-navbar-bg' => sprintf(
                    'linear-gradient(120deg, rgba(%s, 0.92) 0%%, rgba(%s, 0.92) 70%%, rgba(%s, 0.94) 100%%)',
                    $this->rgb($chrome), $this->rgb($deep), $this->rgb($night),
                ),
            ];
        }

        if (isset($custom['accentColor'])) {
            $vars += [
                '--pp-emerald' => $accent,
                '--pp-emerald-rgb' => $this->rgb($accent),
                '--pp-emerald-dark' => $this->mix($accent, '#000000', 0.2),
                '--pp-grad-primary' => sprintf(
                    'linear-gradient(135deg, %s 0%%, %s 100%%)',
                    $this->mix($accent, '#ffffff', 0.12), $this->mix($accent, '#000000', 0.2),
                ),
            ];
        }

        if (isset($custom['primaryColor']) || isset($custom['accentColor'])) {
            $vars['--pp-grad-hero'] = sprintf(
                'linear-gradient(120deg, %s 0%%, %s 48%%, %s 110%%)',
                $chrome, $this->mix($chrome, '#ffffff', 0.1), $this->mix($chrome, $accent, 0.55),
            );
        }

        if (isset($custom['headingFont']) || isset($custom['bodyFont'])) {
            $vars['--pp-font-heading'] = $this->fontStack($theme->resolve('headingFont'));
            $vars['--pp-font-body'] = $this->fontStack($theme->resolve('bodyFont'));
        }

        if (isset($custom['bodyBackground'])) {
            $vars['--pp-bg'] = $theme->resolve('bodyBackground');
        }

        if (isset($custom['cornerStyle'])) {
            [$vars['--pp-radius'], $vars['--pp-radius-sm']] = self::PORTAL_RADII[$theme->resolve('cornerStyle')];
        }

        return ':root{' . $this->declarations($vars) . '}';
    }

    public function css(SchoolTheme $theme): string
    {
        $primary = $theme->resolve('primaryColor');
        $accent = $theme->resolve('accentColor');
        $sidebar = $theme->resolve('sidebarColor');
        $body = $theme->resolve('bodyBackground');
        [$radiusSm, $radius, $radiusLg] = self::RADII[$theme->resolve('cornerStyle')];

        $primaryDark = $this->mix($primary, '#000000', 0.25);
        $sidebarBg = $theme->resolve('sidebarStyle') === 'flat'
            ? $sidebar
            : sprintf('linear-gradient(190deg, %s 0%%, %s 100%%)', $sidebar, $this->mix($sidebar, '#000000', 0.35));

        $vars = [
            '--primary-color' => $primary,
            '--es-primary' => $primary,
            '--es-primary-rgb' => $this->rgb($primary),
            '--es-primary-dark' => $primaryDark,
            '--es-primary-light' => $this->mix($primary, '#ffffff', 0.15),
            '--bs-primary' => $primary,
            '--bs-primary-rgb' => $this->rgb($primary),
            '--bs-link-color' => $primary,
            '--bs-link-color-rgb' => $this->rgb($primary),
            '--bs-link-hover-color' => $primaryDark,
            '--es-accent' => $accent,
            '--es-accent-rgb' => $this->rgb($accent),
            '--es-accent-dark' => $this->mix($accent, '#000000', 0.2),
            '--es-font-heading' => $this->fontStack($theme->resolve('headingFont')),
            '--es-font-body' => $this->fontStack($theme->resolve('bodyFont')),
            '--es-radius-sm' => $radiusSm,
            '--es-radius' => $radius,
            '--es-radius-lg' => $radiusLg,
            '--bs-border-radius' => $radiusSm,
            '--bs-border-radius-lg' => $radius,
        ];

        // Fond de page et barre latérale : thème clair uniquement, le thème sombre
        // conserve ses propres teintes (styles.css).
        // Au-delà de ~0,4, un texte foncé contraste mieux qu'un texte blanc.
        $isLightSidebar = $this->luminance($sidebar) > 0.4;
        $ink = $isLightSidebar ? '15, 23, 42' : '255, 255, 255';
        $lightVars = [
            '--es-body-bg' => $body,
            '--bs-body-bg' => $body,
            // --es-sidebar-bg sert aussi de fond aux icônes d'en-tête de page (texte
            // blanc) : il suit la couleur principale, la barre latérale a sa variable.
            '--es-sidebar-bg' => sprintf('linear-gradient(185deg, %s 0%%, %s 100%%)', $primary, $primaryDark),
            '--es-sidebar-custom-bg' => $sidebarBg,
            '--es-sidebar-text' => $isLightSidebar ? '#0f172a' : '#ffffff',
            '--es-sidebar-ink-rgb' => $ink,
        ];

        return sprintf(
            ':root{%s}:root:not([data-bs-theme="dark"]){%s}',
            $this->declarations($vars),
            $this->declarations($lightVars),
        );
    }

    private function fontLink(SchoolTheme $theme): string
    {
        $fonts = array_unique([$theme->resolve('headingFont'), $theme->resolve('bodyFont')]);
        // Inter et Poppins sont déjà chargées par le layout.
        $fonts = array_diff($fonts, ['Inter', 'Poppins']);
        if ($fonts === []) {
            return '';
        }

        $families = implode('&', array_map(
            static fn (string $font) => 'family=' . str_replace(' ', '+', $font) . ':wght@400;500;600;700',
            $fonts,
        ));

        return sprintf('<link href="https://fonts.googleapis.com/css2?%s&display=swap" rel="stylesheet">', htmlspecialchars($families, ENT_QUOTES));
    }

    private function fontStack(string $font): string
    {
        return sprintf("'%s', system-ui, -apple-system, sans-serif", $font);
    }

    /** @param array<string, string> $vars */
    private function declarations(array $vars): string
    {
        $css = '';
        foreach ($vars as $name => $value) {
            $css .= $name . ':' . $value . ';';
        }

        return $css;
    }

    /** @return array{int, int, int} */
    private function channels(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    private function rgb(string $hex): string
    {
        return implode(', ', $this->channels($hex));
    }

    /** Mélange $hex avec $with dans la proportion $ratio (0 = inchangé). */
    private function mix(string $hex, string $with, float $ratio): string
    {
        $a = $this->channels($hex);
        $b = $this->channels($with);

        return sprintf('#%02x%02x%02x', ...array_map(
            static fn (int $i) => (int) round($a[$i] + ($b[$i] - $a[$i]) * $ratio),
            [0, 1, 2],
        ));
    }

    /** Luminance relative (WCAG), de 0 (noir) à 1 (blanc). */
    private function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(static function (int $c): float {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, $this->channels($hex));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }
}
