<?php

namespace App\Theme;

/**
 * Personnalisation graphique d'un établissement (couleurs, polices, arrondis).
 *
 * Stockée en JSON dans School::$theme. Seules les valeurs qui diffèrent du thème
 * par défaut sont persistées : un établissement non personnalisé suit donc
 * automatiquement les évolutions de la charte par défaut.
 *
 * Les valeurs sont revalidées à la lecture (resolve()) : elles finissent dans une
 * feuille de style, une valeur hors liste blanche ne doit jamais y parvenir, même
 * si la base contient autre chose que ce que le formulaire autorise.
 */
final class SchoolTheme
{
    public const DEFAULTS = [
        'primaryColor' => '#1e3a5f',
        'accentColor' => '#10b981',
        'sidebarColor' => '#1e3a5f',
        'sidebarStyle' => 'gradient',
        'bodyBackground' => '#f4f6f9',
        'headingFont' => 'Poppins',
        'bodyFont' => 'Inter',
        'cornerStyle' => 'soft',
    ];

    /** Polices proposées (toutes disponibles sur Google Fonts). */
    public const FONTS = [
        'Inter', 'Poppins', 'Roboto', 'Open Sans', 'Lato', 'Montserrat',
        'Nunito', 'Raleway', 'Source Sans 3', 'Merriweather', 'Playfair Display',
    ];

    public const SIDEBAR_STYLES = ['gradient', 'flat'];

    public const CORNER_STYLES = ['square', 'soft', 'rounded'];

    private const HEX_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    public ?string $primaryColor = null;
    public ?string $accentColor = null;
    public ?string $sidebarColor = null;
    public ?string $sidebarStyle = null;
    public ?string $bodyBackground = null;
    public ?string $headingFont = null;
    public ?string $bodyFont = null;
    public ?string $cornerStyle = null;

    /**
     * Thème complet : chaque réglage absent ou invalide prend la valeur par défaut
     * (le formulaire affiche ainsi toujours des valeurs effectives).
     */
    public static function fromArray(?array $data): self
    {
        $theme = new self();
        foreach (array_keys(self::DEFAULTS) as $key) {
            $value = $data[$key] ?? null;
            $theme->{$key} = is_string($value) ? $value : null;
            $theme->{$key} = $theme->resolve($key);
        }

        return $theme;
    }

    /**
     * Valeurs valides et différentes du défaut, prêtes à être persistées.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $data = [];
        foreach (self::DEFAULTS as $key => $default) {
            $value = $this->resolve($key);
            if ($value !== $default) {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    public function isDefault(): bool
    {
        return $this->toArray() === [];
    }

    /**
     * Valeur effective d'un réglage : la valeur saisie si elle est valide, sinon
     * celle du thème par défaut.
     */
    public function resolve(string $key): string
    {
        $default = self::DEFAULTS[$key] ?? throw new \InvalidArgumentException(sprintf('Réglage de thème inconnu : %s', $key));
        $value = $this->{$key};

        if ($value === null) {
            return $default;
        }

        $valid = match ($key) {
            'primaryColor', 'accentColor', 'sidebarColor', 'bodyBackground' => preg_match(self::HEX_PATTERN, $value) === 1,
            'headingFont', 'bodyFont' => in_array($value, self::FONTS, true),
            'sidebarStyle' => in_array($value, self::SIDEBAR_STYLES, true),
            'cornerStyle' => in_array($value, self::CORNER_STYLES, true),
        };

        return $valid ? ($value[0] === '#' ? strtolower($value) : $value) : $default;
    }
}
