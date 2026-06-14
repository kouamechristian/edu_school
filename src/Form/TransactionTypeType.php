<?php

namespace App\Form;

use App\Entity\TransactionType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TransactionTypeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du type',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Salaire, Loyer, Don reçu...'],
            ])
            ->add('direction', ChoiceType::class, [
                'label' => 'Sens (impact comptable)',
                'placeholder' => 'Sélectionnez le sens',
                'choices' => [
                    'Entrée (argent reçu)' => 'entrée',
                    'Sortie (argent dépensé)' => 'sortie',
                    'Transfert' => 'transfert',
                ],
                'attr' => ['class' => 'form-select'],
                'help' => 'Détermine si ce type augmente ou diminue la trésorerie.',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 2],
            ])
            ->add('isActive', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => ['Actif' => true, 'Inactif' => false],
                'attr' => ['class' => 'form-select'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TransactionType::class,
        ]);
    }
}
