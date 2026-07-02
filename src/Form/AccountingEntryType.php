<?php

namespace App\Form;

use App\Entity\AccountingAccount;
use App\Entity\AccountingEntry;
use App\Entity\School;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Saisie manuelle d'une écriture au journal (recette / dépense ponctuelle non
 * couverte par un paiement ou une dépense de caisse).
 */
class AccountingEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var School|null $school */
        $school = $options['school'];

        $builder
            ->add('entryDate', DateType::class, [
                'label' => 'Date',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('type', ChoiceType::class, [
                'label' => "Type d'écriture",
                'choices' => [
                    'Recette' => AccountingEntry::TYPE_RECETTE,
                    'Dépense' => AccountingEntry::TYPE_DEPENSE,
                    'Versement bancaire' => AccountingEntry::TYPE_VERSEMENT,
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('account', EntityType::class, [
                'label' => 'Compte (poste)',
                'class' => AccountingAccount::class,
                'required' => false,
                'placeholder' => 'Non ventilé',
                'choice_label' => fn (AccountingAccount $a) => $a->getCode() . ' — ' . $a->getName(),
                'attr' => ['class' => 'form-select'],
                'query_builder' => function ($repo) use ($school) {
                    $qb = $repo->createQueryBuilder('a')
                        ->andWhere('a.isActive = true')
                        ->orderBy('a.type', 'ASC')
                        ->addOrderBy('a.code', 'ASC');
                    if ($school) {
                        $qb->andWhere('a.school = :school')->setParameter('school', $school->getId());
                    }
                    return $qb;
                },
            ])
            ->add('amount', MoneyType::class, [
                'label' => 'Montant',
                'currency' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => '0'],
            ])
            ->add('label', TextType::class, [
                'label' => 'Libellé',
                'attr' => ['class' => 'form-control', 'placeholder' => "Objet de l'écriture"],
            ])
            ->add('paymentMethod', ChoiceType::class, [
                'label' => 'Méthode',
                'required' => false,
                'placeholder' => '—',
                'choices' => [
                    'Espèces' => 'espèces',
                    'Chèque' => 'chèque',
                    'Virement' => 'virement',
                    'Carte' => 'carte',
                    'Mobile Money' => 'mobile_money',
                ],
                'attr' => ['class' => 'form-select'],
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
            'data_class' => AccountingEntry::class,
            'school' => null,
        ]);
        $resolver->setAllowedTypes('school', [School::class, 'null']);
    }
}
