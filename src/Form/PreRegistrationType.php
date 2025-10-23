<?php

namespace App\Form;

use App\Entity\PreRegistration;
use App\Entity\Level;
use App\Entity\SchoolYear;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PreRegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Jean',
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'DUPONT',
                ],
            ])
            ->add('dateOfBirth', DateType::class, [
                'label' => 'Date de naissance',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('gender', ChoiceType::class, [
                'label' => 'Genre',
                'choices' => [
                    'Masculin' => 'M',
                    'Féminin' => 'F',
                ],
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez',
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '06 12 34 56 78',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'email@exemple.com',
                ],
            ])
            ->add('address', TextareaType::class, [
                'label' => 'Adresse',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Adresse complète',
                ],
            ])
            ->add('parentName', TextType::class, [
                'label' => 'Nom du parent/tuteur',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Nom du parent',
                ],
            ])
            ->add('parentPhone', TelType::class, [
                'label' => 'Téléphone du parent',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '06 12 34 56 78',
                ],
            ])
            ->add('parentEmail', EmailType::class, [
                'label' => 'Email du parent',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'parent@exemple.com',
                ],
            ])
            ->add('emergencyContact', TextType::class, [
                'label' => 'Contact d\'urgence',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Nom du contact d\'urgence',
                ],
            ])
            ->add('emergencyPhone', TelType::class, [
                'label' => 'Téléphone d\'urgence',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '06 12 34 56 78',
                ],
            ])
            ->add('medicalInfo', TextType::class, [
                'label' => 'Informations médicales',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Allergies, médicaments, etc.',
                ],
            ])
            ->add('requestedLevel', EntityType::class, [
                'label' => 'Niveau demandé',
                'class' => Level::class,
                'choice_label' => 'name',
                'choices' => $options['levels'] ?? [],
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez un niveau',
                'required' => false,
            ])
            ->add('schoolYear', EntityType::class, [
                'label' => 'Année scolaire',
                'class' => SchoolYear::class,
                'choice_label' => 'name',
                'choices' => $options['school_years'] ?? [],
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez une année',
                'required' => false,
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Notes supplémentaires',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PreRegistration::class,
            'levels' => [],
            'school_years' => [],
        ]);
    }
}
