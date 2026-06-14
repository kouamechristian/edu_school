<?php

namespace App\Form;

use App\Entity\Room;
use App\Entity\School;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RoomType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la salle',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Salle de classe 1',
                ],
            ])
            ->add('code', TextType::class, [
                'label' => 'Code',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Laisser vide pour génération automatique',
                ],
                'help' => 'Code unique pour identifier la salle. Généré automatiquement si laissé vide.',
            ])
            ->add('capacity', IntegerType::class, [
                'label' => 'Capacité',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Nombre de places',
                    'min' => 1,
                ],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type de salle',
                'required' => false,
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez un type',
                'choices' => [
                    'Salle de classe' => 'classroom',
                    'Laboratoire' => 'laboratory',
                    'Salle informatique' => 'computer_room',
                    'Amphithéâtre' => 'amphitheater',
                    'Salle de sport' => 'gym',
                    'Bibliothèque' => 'library',
                    'Salle de réunion' => 'meeting_room',
                    'Salle polyvalente' => 'multipurpose',
                    'Atelier' => 'workshop',
                    'Autre' => 'other',
                ],
            ])
            ->add('building', TextType::class, [
                'label' => 'Bâtiment',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: A, B, Principal...',
                ],
            ])
            ->add('floor', TextType::class, [
                'label' => 'Étage',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: RDC, 1er, 2ème...',
                ],
            ])
            ->add('equipment', TextareaType::class, [
                'label' => 'Équipements',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Liste des équipements disponibles (projecteur, tableaux interactifs, etc.)',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Informations complémentaires sur la salle',
                ],
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
            'data_class' => Room::class,
        ]);
    }
}

