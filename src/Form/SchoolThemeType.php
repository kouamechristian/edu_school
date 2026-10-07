<?php

namespace App\Form;

use App\Theme\SchoolTheme;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Sous-formulaire « Personnalisation du design » d'un établissement.
 */
class SchoolThemeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $colors = [
            'primaryColor' => ['Couleur principale', 'Boutons, liens, titres et éléments de marque.'],
            'accentColor' => ['Couleur d\'accent', 'Menu actif, surlignages et éléments de mise en avant.'],
            'sidebarColor' => ['Couleur de la barre latérale', 'Le texte du menu passe automatiquement en foncé sur une couleur claire.'],
            'bodyBackground' => ['Fond des pages', 'Couleur de fond derrière les cartes (thème clair).'],
        ];

        foreach ($colors as $name => [$label, $help]) {
            $builder->add($name, ColorType::class, [
                'label' => $label,
                'help' => $help,
                'required' => false,
                'empty_data' => SchoolTheme::DEFAULTS[$name],
                'attr' => ['class' => 'w-100', 'data-theme-key' => $name],
                'constraints' => [
                    new Regex(pattern: '/^#[0-9a-fA-F]{6}$/', message: 'Couleur hexadécimale invalide (ex : #1e3a5f).'),
                ],
            ]);
        }

        $fonts = array_combine(SchoolTheme::FONTS, SchoolTheme::FONTS);

        $builder
            ->add('sidebarStyle', ChoiceType::class, [
                'label' => 'Style de la barre latérale',
                'choices' => ['Dégradé' => 'gradient', 'Couleur unie' => 'flat'],
                'empty_data' => SchoolTheme::DEFAULTS['sidebarStyle'],
                'attr' => ['class' => 'form-select', 'data-theme-key' => 'sidebarStyle'],
                'constraints' => [new Choice(choices: SchoolTheme::SIDEBAR_STYLES)],
            ])
            ->add('headingFont', ChoiceType::class, [
                'label' => 'Police des titres',
                'choices' => $fonts,
                'empty_data' => SchoolTheme::DEFAULTS['headingFont'],
                'attr' => ['class' => 'form-select', 'data-theme-key' => 'headingFont'],
                'constraints' => [new Choice(choices: SchoolTheme::FONTS)],
            ])
            ->add('bodyFont', ChoiceType::class, [
                'label' => 'Police du texte',
                'choices' => $fonts,
                'empty_data' => SchoolTheme::DEFAULTS['bodyFont'],
                'attr' => ['class' => 'form-select', 'data-theme-key' => 'bodyFont'],
                'constraints' => [new Choice(choices: SchoolTheme::FONTS)],
            ])
            ->add('cornerStyle', ChoiceType::class, [
                'label' => 'Arrondi des coins',
                'choices' => ['Carré' => 'square', 'Léger' => 'soft', 'Arrondi' => 'rounded'],
                'empty_data' => SchoolTheme::DEFAULTS['cornerStyle'],
                'attr' => ['class' => 'form-select', 'data-theme-key' => 'cornerStyle'],
                'constraints' => [new Choice(choices: SchoolTheme::CORNER_STYLES)],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SchoolTheme::class,
            // Le thème est un objet valeur reconstruit à chaque lecture : il faut
            // rappeler School::setTheme() pour que les modifications soient prises.
            'by_reference' => false,
            'label' => false,
        ]);
    }
}
