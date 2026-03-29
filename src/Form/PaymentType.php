<?php

namespace App\Form;

use App\Entity\Payment;
use App\Entity\Student;
use App\Entity\Fee;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaymentType extends AbstractType
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
            ->add('amount', MoneyType::class, [
                'label' => 'Montant payé',
                'currency' => 'XOF',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('paymentDate', DateType::class, [
                'label' => 'Date de paiement',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('paymentMethod', ChoiceType::class, [
                'label' => 'Méthode de paiement',
                'choices' => [
                    'Espèces' => 'espèces',
                    'Chèque' => 'chèque',
                    'Virement' => 'virement',
                    'Carte bancaire' => 'carte',
                    'Mobile Money' => 'mobile_money',
                ],
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'En attente' => 'en_attente',
                    'Payé' => 'payé',
                    'Partiellement payé' => 'partiellement_payé',
                    'Annulé' => 'annulé',
                    'Remboursé' => 'remboursé',
                ],
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('reference', TextType::class, [
                'label' => 'Référence/N° de transaction',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: CHQ001234 ou MTN123456789'
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
            'data_class' => Payment::class,
        ]);
    }
}
