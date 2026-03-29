<?php

namespace App\Form;

use App\Entity\Invoice;
use App\Entity\Student;
use App\Entity\Fee;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InvoiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('student', EntityType::class, [
                'class' => Student::class,
                'label' => 'Élève',
                'choice_label' => 'fullName',
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('fee', EntityType::class, [
                'class' => Fee::class,
                'label' => 'Frais',
                'choice_label' => 'name',
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('totalAmount', MoneyType::class, [
                'label' => 'Montant total',
                'currency' => 'XOF',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('paidAmount', MoneyType::class, [
                'label' => 'Montant payé',
                'currency' => 'XOF',
                'required' => false,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('issueDate', DateType::class, [
                'label' => 'Date d\'émission',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('dueDate', DateType::class, [
                'label' => 'Date d\'échéance',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Brouillon' => 'brouillon',
                    'Envoyée' => 'envoyée',
                    'Payée' => 'payée',
                    'Partiellement payée' => 'partiellement_payée',
                    'En retard' => 'en_retard',
                    'Annulée' => 'annulée',
                ],
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('discountPercentage', NumberType::class, [
                'label' => 'Remise en pourcentage (%)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'max' => 100,
                    'step' => 0.01
                ]
            ])
            ->add('discountAmount', MoneyType::class, [
                'label' => 'Montant de remise',
                'currency' => 'XOF',
                'required' => false,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('taxAmount', MoneyType::class, [
                'label' => 'Montant des taxes',
                'currency' => 'XOF',
                'required' => false,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Observations ou commentaires'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Invoice::class,
        ]);
    }
}
