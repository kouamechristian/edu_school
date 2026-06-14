<?php

namespace App\Form;

use App\Entity\FinancialTransaction;
use App\Entity\TransactionType;
use App\Repository\TransactionTypeRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FinancialTransactionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('transactionType', EntityType::class, [
                'label' => 'Type de transaction',
                'class' => TransactionType::class,
                'choice_label' => fn (TransactionType $t) => $t->getName() . ' (' . $t->getDirectionLabel() . ')',
                'placeholder' => 'Sélectionnez un type',
                'query_builder' => fn (TransactionTypeRepository $r) => $r->createQueryBuilder('t')
                    ->where('t.isActive = :a')->setParameter('a', true)->orderBy('t.name', 'ASC'),
                'attr' => ['class' => 'form-select'],
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'Catégorie',
                'placeholder' => 'Sélectionnez une catégorie',
                'choices' => [
                    'Paiement' => 'paiement',
                    'Remboursement' => 'remboursement',
                    'Bourse' => 'bourse',
                    'Frais' => 'frais',
                    'Autre' => 'autre',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('amount', NumberType::class, [
                'label' => 'Montant',
                'scale' => 2,
                'attr' => ['class' => 'form-control', 'placeholder' => '0.00'],
            ])
            ->add('paymentMethod', ChoiceType::class, [
                'label' => 'Méthode de paiement',
                'placeholder' => 'Sélectionnez une méthode',
                'choices' => [
                    'Espèces' => 'espèces',
                    'Chèque' => 'chèque',
                    'Virement' => 'virement',
                    'Carte bancaire' => 'carte',
                    'Mobile Money' => 'mobile_money',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('transactionDate', DateType::class, [
                'label' => 'Date de transaction',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'En attente' => 'en_attente',
                    'Confirmé' => 'confirmé',
                    'Annulé' => 'annulé',
                    'En erreur' => 'en_erreur',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('reference', TextType::class, [
                'label' => 'Référence',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Référence externe (optionnel)'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 3],
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
            'data_class' => FinancialTransaction::class,
        ]);
    }
}
