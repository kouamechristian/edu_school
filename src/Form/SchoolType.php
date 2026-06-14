<?php

namespace App\Form;

use App\Entity\School;
use App\Entity\SchoolGroup;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Regex;

class SchoolType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de l\'établissement',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: École Primaire Jean Moulin'],
            ])
            ->add('code', TextType::class, [
                'label' => 'Code établissement',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Laisser vide pour génération automatique'],
                'help' => 'Code unique pour identifier l\'établissement. Généré automatiquement si laissé vide.',
            ])
            ->add('schoolGroup', EntityType::class, [
                'label' => 'Groupe d\'établissements',
                'class' => SchoolGroup::class,
                'choice_label' => 'name',
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez un groupe',
                'required' => false,
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('sg')
                        ->where('sg.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('sg.name', 'ASC');
                },
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type d\'établissement',
                'choices' => [
                    'Maternelle' => 'maternelle',
                    'Primaire' => 'primaire',
                    'Collège' => 'college',
                    'Lycée' => 'lycee',
                    'Université / Grande École' => 'universite',
                ],
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez un type',
            ])
            ->add('director', TextType::class, [
                'label' => 'Nom du directeur/directrice',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: M. Jean DUPONT'],
            ])
            ->add('address', TextareaType::class, [
                'label' => 'Adresse complète',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => '15 Rue de la République, 75001 Paris',
                ],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => '01 23 45 67 89'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'contact@ecole.com'],
            ])
            ->add('logoFile', FileType::class, [
                'label' => 'Logo de l\'établissement',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control', 'accept' => 'image/*'],
                'help' => 'Formats acceptés : JPG, PNG, GIF, SVG, WEBP (max 2 Mo).',
                'constraints' => [
                    new File(
                        maxSize: '2M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'],
                        mimeTypesMessage: 'Veuillez téléverser une image valide (JPG, PNG, GIF, SVG, WEBP).'
                    ),
                ],
            ])
            ->add('cachetDirectionFile', FileType::class, [
                'label' => 'Cachet de la direction',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control', 'accept' => 'image/*'],
                'help' => 'Image du cachet/tampon officiel de la direction (max 2 Mo).',
                'constraints' => [
                    new File(
                        maxSize: '2M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'],
                        mimeTypesMessage: 'Veuillez téléverser une image valide (JPG, PNG, GIF, SVG, WEBP).'
                    ),
                ],
            ])
            ->add('badgeBackgroundColor', TextType::class, [
                'label' => 'Couleur de fond du badge',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: #2563eb (laisser vide pour aucune)',
                    'maxlength' => 20,
                ],
                'help' => 'Couleur du badge de l\'établissement au format hexadécimal. Laisser vide pour aucune couleur.',
                'constraints' => [
                    new Regex(
                        pattern: '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/',
                        message: 'Veuillez saisir une couleur hexadécimale valide (ex: #2563eb).',
                        match: true
                    ),
                ],
            ])
            ->add('isActive', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Actif' => true,
                    'Inactif' => false,
                ],
                'attr' => ['class' => 'form-select'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => School::class,
        ]);
    }
}

