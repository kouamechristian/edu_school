<?php

namespace App\Form;

use App\Entity\Period;
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
            ->add('name', TextType::class, [
                'label' => 'Nom de la période',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 1er Trimestre, Semestre 1...',
                ],
            ])
            ->add('code', TextType::class, [
                'label' => 'Code',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Laisser vide pour génération automatique',
                ],
                'help' => 'Code unique pour identifier la période. Généré automatiquement si laissé vide.',
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
