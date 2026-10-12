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
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class PaymentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $studentField = [
            'class' => Student::class,
            'label' => 'Élève',
            // Libellé « Matricule — NOM Prénom » : rend la recherche possible aussi bien
            // par matricule que par nom/prénom (le select est recherchable via TomSelect).
            'choice_label' => function (Student $s): string {
                $matricule = $s->getMatriculeInterne() ?: $s->getMatriculeNational();

                return trim(($matricule ? $matricule . ' — ' : '') . $s->getFullName());
            },
            'placeholder' => 'Rechercher un élève (matricule, nom, prénom)…',
            'attr' => [
                'class' => 'form-select js-student-select',
            ],
        ];
        if (\is_array($options['student_choices'])) {
            $studentField['choices'] = $options['student_choices'];
        }

        $builder
            ->add('student', EntityType::class, $studentField)
            ->add('fee', EntityType::class, [
                'class' => Fee::class,
                'label' => 'Frais',
                'choice_label' => 'name',
                // « no-search » : laisse ce select NATIF (l'enhancer TomSelect global de
                // base.html.twig l'ignore), pour que la cascade JS puisse remplacer ses
                // options selon l'élève choisi et que l'affichage se mette à jour.
                'attr' => [
                    'class' => 'form-select no-search'
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
                    'ECOBANK' => 'ecobank',
                    'BANK' => 'bank',
                ],
                'attr' => [
                    'class' => 'form-select'
                ]
            ])

            ->add('reference', TextType::class, [
                'label' => 'Référence',
                'required' => false,
                'help' => 'N° de transaction / bordereau. Laisser vide pour une référence automatique.',
                'constraints' => [new Assert\Length(max: 100, maxMessage: 'La référence ne peut pas dépasser {{ limit }} caractères.')],
                'attr' => ['class' => 'form-control', 'maxlength' => 100, 'placeholder' => 'Ex. N° de transaction ECOBANK'],
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
            'student_choices' => null,
        ]);
        $resolver->setAllowedTypes('student_choices', ['null', 'array']);
    }
}
