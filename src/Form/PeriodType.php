<?php

namespace App\Form;

use App\Entity\Period;
use App\Entity\School;
use App\Entity\SchoolYear;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PeriodType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('school', EntityType::class, [
                'label' => 'Établissement',
                'class' => School::class,
                'choice_label' => 'name',
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez un établissement',
                'disabled' => $options['data']->getId() !== null,
            ])
            ->add('schoolYear', EntityType::class, [
                'label' => 'Année scolaire',
                'class' => SchoolYear::class,
                'choice_label' => 'name',
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez une année scolaire',
            ])
            ->add('name', TextType::class, [
                'label' => 'Nom de la période',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 1er Trimestre, Semestre 1...',
                ],
            ])
            ->add('code', TextType::class, [
                'label' => 'Code',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: T1, S1...',
                ],
                'help' => 'Code unique pour identifier la période',
            ])
            ->add('orderNumber', IntegerType::class, [
                'label' => 'Numéro d\'ordre',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '1, 2, 3...',
                    'min' => 1,
                ],
                'help' => 'Ordre d\'affichage de la période',
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Active',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Period::class,
        ]);
    }
}
