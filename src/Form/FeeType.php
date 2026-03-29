<?php

namespace App\Form;

use App\Entity\Fee;
use App\Entity\School;
use App\Entity\Level;
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

class FeeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du frais',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Frais de scolarité'
                ]
            ])
            ->add('code', TextType::class, [
                'label' => 'Code',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: FRAIS-SCOL-001'
                ]
            ])
            ->add('school', EntityType::class, [
                'class' => School::class,
                'label' => 'Établissement',
                'choice_label' => 'name',
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('level', EntityType::class, [
                'class' => Level::class,
                'label' => 'Niveau (optionnel)',
                'choice_label' => 'name',
                'required' => false,
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('amount', MoneyType::class, [
                'label' => 'Montant',
                'currency' => 'XOF',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    'Obligatoire' => 'obligatoire',
                    'Optionnel' => 'optionnel',
                    'Pénalité' => 'pénalité',
                ],
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('frequency', ChoiceType::class, [
                'label' => 'Fréquence',
                'choices' => [
                    'Unique' => 'unique',
                    'Mensuel' => 'mensuel',
                    'Trimestriel' => 'trimestriel',
                    'Annuel' => 'annuel',
                ],
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'required' => false,
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
            ->add('dueDate', DateType::class, [
                'label' => 'Date d\'échéance',
                'widget' => 'single_text',
                'required' => false,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('discountPercentage', NumberType::class, [
                'label' => 'Remise en pourcentage (%)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'max' => 100,
                    'step' => 0.01
                ]
            ])
            ->add('discountAmount', MoneyType::class, [
                'label' => 'Montant de remise',
                'currency' => 'XOF',
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
                    'rows' => 3
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Fee::class,
        ]);
    }
}
