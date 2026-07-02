<?php

namespace App\Form;

use App\Entity\SalaryComponent;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SalaryComponentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'Code',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: TRANSPORT'],
            ])
            ->add('name', TextType::class, [
                'label' => 'Libellé',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Prime de transport'],
            ])
            ->add('direction', ChoiceType::class, [
                'label' => 'Sens',
                'choices' => ['Gain' => SalaryComponent::DIRECTION_GAIN, 'Retenue' => SalaryComponent::DIRECTION_RETENUE],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('calcMode', ChoiceType::class, [
                'label' => 'Mode de calcul',
                'choices' => ['Montant fixe' => SalaryComponent::MODE_FIXED, "Pourcentage d'une base" => SalaryComponent::MODE_PERCENT],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('base', ChoiceType::class, [
                'label' => 'Base (si pourcentage)',
                'choices' => ['Salaire de base' => SalaryComponent::BASE_SALARY, 'Brut' => SalaryComponent::BASE_GROSS],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('amount', MoneyType::class, [
                'label' => 'Montant fixe',
                'required' => false,
                'currency' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => '0'],
            ])
            ->add('rate', NumberType::class, [
                'label' => 'Taux (%)',
                'required' => false,
                'scale' => 3,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: 10'],
            ])
            ->add('taxable', ChoiceType::class, [
                'label' => 'Imposable (ITS)',
                'choices' => ['Oui' => true, 'Non' => false],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('cnpsSubject', ChoiceType::class, [
                'label' => 'Soumis CNPS',
                'choices' => ['Oui' => true, 'Non' => false],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('sortOrder', IntegerType::class, [
                'label' => 'Ordre',
                'attr' => ['class' => 'form-control'],
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
        $resolver->setDefaults(['data_class' => SalaryComponent::class]);
    }
}
