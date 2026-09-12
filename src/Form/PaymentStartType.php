<?php

namespace App\Form;

use App\Entity\Student;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Étape 1 de l'encaissement : l'élève et le montant versé par le parent.
 * La répartition du montant sur les frais se fait à l'étape suivante (imputation).
 */
class PaymentStartType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('student', EntityType::class, [
                'class' => Student::class,
                'label' => 'Élève',
                'choices' => $options['student_choices'],
                // « Matricule — NOM Prénom » : recherche par matricule comme par nom (TomSelect).
                'choice_label' => function (Student $s): string {
                    $matricule = $s->getMatriculeInterne() ?: $s->getMatriculeNational();

                    return trim(($matricule ? $matricule . ' — ' : '') . $s->getFullName());
                },
                'placeholder' => 'Rechercher un élève (matricule, nom, prénom)…',
                'constraints' => [new Assert\NotBlank(message: 'L\'élève est obligatoire')],
                'attr' => ['class' => 'form-select js-student-select'],
            ])
            ->add('amount', NumberType::class, [
                'label' => 'Montant versé (F CFA)',
                'html5' => true,
                'scale' => 0,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le montant est obligatoire'),
                    new Assert\Positive(message: 'Le montant doit être positif'),
                ],
                'attr' => ['class' => 'form-control form-control-lg fw-bold', 'min' => 1, 'step' => 1, 'placeholder' => 'Ex. 50000'],
            ])
            ->add('paymentDate', DateType::class, [
                'label' => 'Date de paiement',
                'widget' => 'single_text',
                'constraints' => [new Assert\NotBlank(message: 'La date de paiement est obligatoire')],
                'attr' => ['class' => 'form-control'],
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
                'constraints' => [new Assert\NotBlank(message: 'La méthode de paiement est obligatoire')],
                'attr' => ['class' => 'form-select no-search'],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 2, 'placeholder' => 'Observations ou commentaires'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'student_choices' => [],
        ]);
        $resolver->setAllowedTypes('student_choices', 'array');
    }
}
