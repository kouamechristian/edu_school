<?php

namespace App\Form;

use App\Entity\Scholarship;
use App\Entity\Student;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ScholarshipType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la bourse',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Bourse d\'excellence'
                ]
            ])
            ->add('student', EntityType::class, [
                'class' => Student::class,
                'label' => 'Élève',
                'choice_label' => 'fullName',
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type de bourse',
                'choices' => [
                    'Montant fixe' => 'montant_fixe',
                    'Pourcentage' => 'pourcentage',
                    'Gratuité totale' => 'gratuité_totale',
                ],
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('amount', MoneyType::class, [
                'label' => 'Montant (si montant fixe)',
                'currency' => 'XOF',
                'required' => false,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('percentage', NumberType::class, [
                'label' => 'Pourcentage (si pourcentage)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'max' => 100,
                    'step' => 0.01
                ]
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Active' => 'active',
                    'Suspendue' => 'suspendue',
                    'Expirée' => 'expirée',
                    'Annulée' => 'annulée',
                ],
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'required' => false,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Description de la bourse'
                ]
            ])
            ->add('conditions', TextareaType::class, [
                'label' => 'Conditions',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Conditions pour bénéficier de la bourse'
                ]
            ])
            ->add('sponsor', TextType::class, [
                'label' => 'Sponsor/Organisme',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Ministère de l\'Éducation'
                ]
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Observations ou commentaires'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Scholarship::class,
        ]);
    }
}
