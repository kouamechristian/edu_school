<?php

namespace App\Form;

use App\Entity\Contract;
use App\Entity\Employee;
use App\Entity\School;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContractType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var School|null $school */
        $school = $options['school'];

        $builder
            ->add('employee', EntityType::class, [
                'label' => 'Employé',
                'class' => Employee::class,
                'choice_label' => fn (Employee $e) => $e->getFullName() . ' — ' . $e->getEmployeeTypeLabel(),
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez un employé',
                'query_builder' => function ($repository) use ($school) {
                    $qb = $repository->createQueryBuilder('e')
                        ->orderBy('e.lastName', 'ASC')
                        ->addOrderBy('e.firstName', 'ASC');

                    if ($school) {
                        $qb->join('e.schools', 's')
                            ->andWhere('s.id = :schoolId')
                            ->setParameter('schoolId', $school->getId());
                    }

                    return $qb;
                },
            ])
            ->add('contractType', ChoiceType::class, [
                'label' => 'Type de contrat',
                'choices' => [
                    'CDI' => 'cdi',
                    'CDD' => 'cdd',
                    'Stage' => 'stage',
                    'Prestation de service' => 'prestation',
                    'Intérim' => 'interim',
                    'Vacation' => 'vacation',
                ],
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez un type',
            ])
            ->add('jobTitle', TextType::class, [
                'label' => 'Intitulé du poste',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Professeur de français'],
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Date de fin',
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'help' => 'Laisser vide pour un contrat à durée indéterminée (CDI).',
            ])
            ->add('trialEndDate', DateType::class, [
                'label' => 'Fin de période d\'essai',
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('baseSalary', MoneyType::class, [
                'label' => 'Salaire de base',
                'required' => false,
                'currency' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => '0.00'],
            ])
            ->add('weeklyHours', IntegerType::class, [
                'label' => 'Heures hebdomadaires',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: 35', 'min' => 0],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Brouillon' => 'draft',
                    'En cours' => 'active',
                    'Suspendu' => 'suspended',
                    'Rompu' => 'terminated',
                    'Expiré' => 'expired',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes / Clauses particulières',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 3],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Contract::class,
            'school' => null,
        ]);

        $resolver->setAllowedTypes('school', [School::class, 'null']);
    }
}
