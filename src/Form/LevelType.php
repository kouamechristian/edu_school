<?php

namespace App\Form;

use App\Entity\Cycle;
use App\Entity\Level;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LevelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du niveau',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: CP, 6ème, Terminale S, Licence 1',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Description du niveau...',
                ],
            ])
            ->add('cycle', EntityType::class, [
                'label' => 'Cycle',
                'class' => Cycle::class,
                'choice_label' => 'libelle',
                'choices' => $options['cycles'] ?? [],
                'required' => false,
                'placeholder' => 'Aucun cycle',
                'attr' => ['class' => 'form-select'],
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
            'data_class' => Level::class,
            'cycles' => [],
        ]);
    }
}

