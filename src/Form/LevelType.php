<?php

namespace App\Form;

use App\Entity\Level;
use App\Entity\School;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
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
            ->add('school', EntityType::class, [
                'class' => School::class,
                'label' => 'Établissement',
                'choice_label' => function (School $school) {
                    return $school->getName() . ' (' . $school->getTypeLabel() . ')';
                },
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez un établissement',
                'required' => false,
                'help' => 'La catégorie du niveau sera déduite du type d\'établissement',
            ])
            ->add('orderNumber', IntegerType::class, [
                'label' => 'Ordre d\'affichage',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'placeholder' => '1',
                ],
                'help' => 'Ordre de tri (du plus petit au plus grand)',
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
        ]);
    }
}

