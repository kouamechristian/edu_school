<?php

namespace App\Form;

use App\Entity\Fee;
use App\Entity\Level;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FeeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $school = $options['current_school'];

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du frais',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Frais de scolarité'
                ]
            ])
            ->add('amount', MoneyType::class, [
                'label' => 'Montant total',
                'currency' => 'XOF',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'Catégorie',
                'choices' => [
                    'Scolarité' => 'scolarite',
                    'Article' => 'article',
                    'Autre frais' => 'autre_frais',
                ],
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    'Pour tous' => 'pour_tous',
                    'Affecté' => 'affecte',
                    'Non affecté' => 'non_affecte',
                ],
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('frequency', ChoiceType::class, [
                'label' => 'Fréquence',
                'choices' => [
                    'Indéterminé' => 'indetermine',
                    'Mensuel' => 'mensuel',
                    'Trimestriel' => 'trimestriel',
                    'Annuel' => 'annuel',
                ],
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3
                ]
            ])
        ;

        $levelQueryBuilder = $school
            ? function (EntityRepository $er) use ($school) {
                return $er->createQueryBuilder('l')
                    ->where('l.school = :school')
                    ->andWhere('l.isActive = true')
                    ->setParameter('school', $school)
                    ->orderBy('l.orderNumber', 'ASC');
            }
            : null;

        // Un frais peut concerner plusieurs niveaux à la fois (relation ManyToMany).
        // Vide => le frais s'applique à tous les niveaux de l'établissement.
        $builder->add('levels', EntityType::class, [
            'class' => Level::class,
            'label' => 'Niveaux concernés',
            'help' => 'Laissez vide pour appliquer à tous les niveaux. Sélectionnez un ou plusieurs niveaux.',
            'choice_label' => 'name',
            'required' => false,
            'multiple' => true,
            'expanded' => false,
            'by_reference' => false,
            'query_builder' => $levelQueryBuilder,
            'attr' => [
                'class' => 'form-select',
                'size' => 6,
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Fee::class,
            'current_school' => null,
        ]);
    }
}
