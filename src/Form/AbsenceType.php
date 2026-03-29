<?php

namespace App\Form;

use App\Entity\Absence;
use App\Entity\AbsenceType as AbsenceTypeEntity;
use App\Entity\Student;
use App\Entity\Period;
use App\Repository\AbsenceTypeRepository;
use App\Repository\StudentRepository;
use App\Repository\PeriodRepository;
use App\Service\SchoolContextService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AbsenceType extends AbstractType
{
    public function __construct(
        private SchoolContextService $contextService
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $currentSchool = $this->contextService->getCurrentSchool();
        
        $builder
            ->add('student', EntityType::class, [
                'class' => Student::class,
                'choice_label' => 'fullName',
                'placeholder' => 'Sélectionner un élève',
                'query_builder' => function (StudentRepository $repository) use ($currentSchool) {
                    return $repository->createQueryBuilder('s')
                        ->andWhere('s.school = :school')
                        ->andWhere('s.isActive = :active')
                        ->setParameter('school', $currentSchool)
                        ->setParameter('active', true)
                        ->orderBy('s.lastName', 'ASC')
                        ->addOrderBy('s.firstName', 'ASC');
                },
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('absenceType', EntityType::class, [
                'class' => AbsenceTypeEntity::class,
                'choice_label' => 'name',
                'placeholder' => 'Sélectionner un type d\'absence',
                'query_builder' => function (AbsenceTypeRepository $repository) use ($currentSchool) {
                    return $repository->createQueryBuilder('at')
                        ->andWhere('at.school = :school')
                        ->andWhere('at.isActive = :active')
                        ->setParameter('school', $currentSchool)
                        ->setParameter('active', true)
                        ->orderBy('at.type', 'ASC')
                        ->addOrderBy('at.name', 'ASC');
                },
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('date', DateType::class, [
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                    'max' => (new \DateTime())->format('Y-m-d'),
                ],
            ])
            ->add('startTime', TimeType::class, [
                'widget' => 'single_text',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('endTime', TimeType::class, [
                'widget' => 'single_text',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('reason', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Motif de l\'absence (optionnel)',
                ],
            ])
            ->add('period', EntityType::class, [
                'class' => Period::class,
                'choice_label' => 'name',
                'placeholder' => 'Sélectionner une période (optionnel)',
                'required' => false,
                'query_builder' => function (PeriodRepository $repository) use ($currentSchool) {
                    $currentYear = $this->contextService->getCurrentSchoolYear();
                    if (!$currentYear) {
                        return $repository->createQueryBuilder('p')->where('1=0');
                    }
                    
                    return $repository->createQueryBuilder('p')
                        ->andWhere('p.school = :school')
                        ->andWhere('p.schoolYear = :schoolYear')
                        ->setParameter('school', $currentSchool)
                        ->setParameter('schoolYear', $currentYear)
                        ->orderBy('p.startDate', 'ASC');
                },
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('notes', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Notes supplémentaires (optionnel)',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Absence::class,
        ]);
    }
}
