<?php

namespace App\Form;

use App\Entity\AccountingAccount;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AccountingAccountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'Code',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: REC-CANTINE'],
            ])
            ->add('name', TextType::class, [
                'label' => 'Libellé',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Recettes cantine'],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    'Recette' => AccountingAccount::TYPE_RECETTE,
                    'Dépense' => AccountingAccount::TYPE_DEPENSE,
                ],
                'attr' => ['class' => 'form-select'],
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
            'data_class' => AccountingAccount::class,
        ]);
    }
}
