<?php

namespace App\Form;

use App\Entity\Depense;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire de saisie d'une dépense (la caisse, l'établissement, le numéro et
 * l'utilisateur sont renseignés par le contrôleur).
 */
class DepenseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle', TextType::class, [
                'label' => 'Motif de la dépense',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex. Salaire enseignant, Loyer…'],
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'Catégorie',
                'placeholder' => 'Sélectionnez une catégorie',
                'choices' => array_flip(Depense::CATEGORIES),
                'attr' => ['class' => 'form-select'],
            ])
            ->add('amount', NumberType::class, [
                'label' => 'Montant',
                'scale' => 2,
                'attr' => ['class' => 'form-control', 'placeholder' => '0.00'],
            ])
            ->add('beneficiary', TextType::class, [
                'label' => 'Bénéficiaire',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'À qui la dépense est versée (optionnel)'],
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
                'attr' => ['class' => 'form-select'],
            ])
            ->add('depenseDate', DateType::class, [
                'label' => 'Date de la dépense',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('reference', TextType::class, [
                'label' => 'Référence',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Pièce justificative (optionnel)'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 2],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 2],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Depense::class,
        ]);
    }
}
