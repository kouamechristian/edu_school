<?php

namespace App\Form;

use App\Entity\SchoolGroup;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SchoolGroupType extends AbstractType
{
    public function __construct(private readonly Security $security)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du groupe',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Groupe Enseignement Fondamental',
                ],
            ])
            ->add('code', TextType::class, [
                'label' => 'Code du groupe',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Laisser vide pour génération automatique',
                ],
                'help' => 'Code unique pour identifier le groupe. Généré automatiquement si laissé vide.',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Description du groupe d\'établissements...',
                ],
            ])
        ;

        // Désactiver un groupe est réservé au super-administrateur (cf. SchoolGroupController).
        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            $builder->add('isActive', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Actif' => true,
                    'Inactif' => false,
                ],
                'attr' => ['class' => 'form-select'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SchoolGroup::class,
        ]);
    }
}

