<?php

namespace App\Form;

use App\Entity\SubjectType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SubjectTypeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => 'Libellé',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Obligatoire',
                ],
            ])
            ->add('orderNumber', IntegerType::class, [
                'label' => 'Numéro d\'ordre sur le bulletin',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 1',
                    'min' => 0,
                ],
                'help' => 'Détermine l\'ordre d\'affichage du type sur le bulletin.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SubjectType::class,
        ]);
    }
}
