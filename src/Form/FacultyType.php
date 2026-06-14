<?php

namespace App\Form;

use App\Entity\Cycle;
use App\Entity\Faculty;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FacultyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle', TextType::class, [
                'label' => 'Libellé de la faculté',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Sciences, Lettres, Économie',
                ],
            ])
            ->add('cycle', EntityType::class, [
                'label' => 'Cycle',
                'class' => Cycle::class,
                'choice_label' => 'libelle',
                'choices' => $options['cycles'] ?? [],
                'placeholder' => 'Sélectionnez un cycle',
                'attr' => ['class' => 'form-select'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Faculty::class,
            'cycles' => [],
        ]);
    }
}
