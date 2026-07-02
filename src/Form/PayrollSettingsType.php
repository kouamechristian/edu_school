<?php

namespace App\Form;

use App\Entity\PayrollSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PayrollSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $pct = ['class' => 'form-control'];
        $builder
            ->add('cnpsEmployeeRate', NumberType::class, ['label' => 'CNPS retraite — salarié (%)', 'scale' => 3, 'attr' => $pct])
            ->add('cnpsEmployerRate', NumberType::class, ['label' => 'CNPS retraite — employeur (%)', 'scale' => 3, 'attr' => $pct])
            ->add('cnpsCeiling', MoneyType::class, ['label' => 'Plafond CNPS (mensuel)', 'currency' => false, 'attr' => $pct])
            ->add('familyBenefitRate', NumberType::class, ['label' => 'Prestations familiales — employeur (%)', 'scale' => 3, 'attr' => $pct])
            ->add('workAccidentRate', NumberType::class, ['label' => 'Accident du travail — employeur (%)', 'scale' => 3, 'attr' => $pct])
            ->add('cmuEmployee', MoneyType::class, ['label' => 'CMU — salarié (forfait)', 'currency' => false, 'attr' => $pct])
            ->add('cmuEmployer', MoneyType::class, ['label' => 'CMU — employeur (forfait)', 'currency' => false, 'attr' => $pct])
            ->add('maxParts', NumberType::class, ['label' => 'Nombre de parts maximum', 'scale' => 1, 'attr' => $pct])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => PayrollSettings::class]);
    }
}
