<?php

namespace App\Form;

use App\Entity\School;
use App\Entity\SchoolGroup;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SchoolType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de l\'établissement',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: École Primaire Jean Moulin'],
            ])
            ->add('code', TextType::class, [
                'label' => 'Code établissement',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: EPM001'],
                'help' => 'Code unique pour identifier l\'établissement',
            ])
            ->add('schoolGroup', EntityType::class, [
                'label' => 'Groupe d\'établissements',
                'class' => SchoolGroup::class,
                'choice_label' => 'name',
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez un groupe',
                'required' => false,
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('sg')
                        ->where('sg.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('sg.name', 'ASC');
                },
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type d\'établissement',
                'choices' => [
                    'Maternelle' => 'maternelle',
                    'Primaire' => 'primaire',
                    'Collège' => 'college',
                    'Lycée' => 'lycee',
                    'Université / Grande École' => 'universite',
                ],
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez un type',
            ])
            ->add('director', TextType::class, [
                'label' => 'Nom du directeur/directrice',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: M. Jean DUPONT'],
            ])
            ->add('address', TextareaType::class, [
                'label' => 'Adresse complète',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => '15 Rue de la République, 75001 Paris',
                ],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => '01 23 45 67 89'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'contact@ecole.com'],
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
            'data_class' => School::class,
        ]);
    }
}

