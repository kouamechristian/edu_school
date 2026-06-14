<?php

namespace App\Form;

use App\Entity\Classroom;
use App\Entity\Evaluation;
use App\Entity\Period;
use App\Entity\Subject;
use App\Entity\User;
use App\Repository\CourseRepository;
use App\Service\SchoolContextService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EvaluationType extends AbstractType
{
    public function __construct(
        private SchoolContextService $contextService,
        private Security $security,
        private CourseRepository $courseRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $currentSchool = $this->contextService->getCurrentSchool();
        $currentYear = $this->contextService->getCurrentSchoolYear();
        $schoolId = $currentSchool ? $currentSchool->getId() : null;
        $yearId = $currentYear ? $currentYear->getId() : null;

        // Restriction enseignant : un enseignant « simple » (sans rôle directeur)
        // ne voit que SES classes, SES matières, et lui-même comme enseignant.
        $user = $this->security->getUser();
        $restrictToTeacher = $user instanceof User
            && $this->security->isGranted('ROLE_ENSEIGNANT')
            && !$this->security->isGranted('ROLE_DIRECTEUR');

        $teacherClassroomIds = [];
        $teacherSubjectIds = [];
        if ($restrictToTeacher) {
            foreach ($this->courseRepository->findByTeacher($user->getId()) as $course) {
                if ($course->getClassroom()) {
                    $teacherClassroomIds[$course->getClassroom()->getId()] = true;
                }
                if ($course->getSubject()) {
                    $teacherSubjectIds[$course->getSubject()->getId()] = true;
                }
            }
            foreach ($user->getTeachingSubjects() as $subject) {
                $teacherSubjectIds[$subject->getId()] = true;
            }
            $teacherClassroomIds = array_keys($teacherClassroomIds) ?: [0];
            $teacherSubjectIds = array_keys($teacherSubjectIds) ?: [0];
        }

        $builder
            ->add('classroom', EntityType::class, [
                'label' => 'Classe',
                'class' => Classroom::class,
                'choice_label' => 'fullName',
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez une classe',
                'query_builder' => function ($repository) use ($schoolId, $yearId, $restrictToTeacher, $teacherClassroomIds) {
                    $qb = $repository->createQueryBuilder('c')
                        ->where('c.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('c.name', 'ASC');

                    if ($schoolId) {
                        $qb->andWhere('c.school = :school')
                           ->setParameter('school', $schoolId);
                    }

                    if ($yearId) {
                        $qb->andWhere('c.schoolYear = :year')
                           ->setParameter('year', $yearId);
                    }

                    if ($restrictToTeacher) {
                        $qb->andWhere('c.id IN (:teacherClassrooms)')
                           ->setParameter('teacherClassrooms', $teacherClassroomIds);
                    }

                    return $qb;
                },
            ])
            ->add('subject', EntityType::class, [
                'label' => 'Matière',
                'class' => Subject::class,
                'choice_label' => 'name',
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez une matière',
                'query_builder' => function ($repository) use ($schoolId, $restrictToTeacher, $teacherSubjectIds) {
                    $qb = $repository->createQueryBuilder('s')
                        ->where('s.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('s.name', 'ASC');

                    if ($schoolId) {
                        $qb->andWhere('s.school = :school')
                           ->setParameter('school', $schoolId);
                    }

                    if ($restrictToTeacher) {
                        $qb->andWhere('s.id IN (:teacherSubjects)')
                           ->setParameter('teacherSubjects', $teacherSubjectIds);
                    }

                    return $qb;
                },
            ])
            ->add('period', EntityType::class, [
                'label' => 'Période',
                'class' => Period::class,
                'choice_label' => 'name',
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez une période',
                'query_builder' => function ($repository) use ($schoolId, $yearId) {
                    $qb = $repository->createQueryBuilder('p')
                        ->where('p.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('p.orderNumber', 'ASC');
                    
                    if ($schoolId) {
                        $qb->andWhere('p.school = :school')
                           ->setParameter('school', $schoolId);
                    }
                    
                    if ($yearId) {
                        $qb->andWhere('p.schoolYear = :year')
                           ->setParameter('year', $yearId);
                    }
                    
                    return $qb;
                },
            ])
            ->add('teacher', EntityType::class, [
                'label' => 'Enseignant',
                'class' => User::class,
                'choice_label' => 'fullName',
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez un enseignant',
                'required' => false,
                'query_builder' => function ($repository) use ($schoolId, $restrictToTeacher, $user) {
                    $qb = $repository->createQueryBuilder('u')
                        ->where('u.userType = :type')
                        ->andWhere('u.isActive = :active')
                        ->setParameter('type', 'enseignant')
                        ->setParameter('active', true)
                        ->orderBy('u.lastName', 'ASC');

                    // Enseignant « simple » : il ne peut se choisir que lui-même.
                    if ($restrictToTeacher) {
                        $qb->andWhere('u.id = :self')->setParameter('self', $user->getId());

                        return $qb;
                    }

                    if ($schoolId) {
                        $qb->innerJoin('u.schools', 's')
                           ->andWhere('s.id = :school')
                           ->setParameter('school', $schoolId);
                    }

                    return $qb;
                },
            ])
            ->add('name', TextType::class, [
                'label' => 'Nom de l\'évaluation',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Contrôle chapitre 1, Examen blanc...',
                ],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type d\'évaluation',
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez un type',
                'choices' => [
                    'Contrôle continu' => 'controle_continu',
                    'Devoir surveillé' => 'devoir_surveille',
                    'Devoir maison' => 'devoir_maison',
                    'Examen' => 'examen',
                    'Oral' => 'oral',
                    'Pratique' => 'pratique',
                    'Projet' => 'projet',
                ],
            ])
            ->add('date', DateType::class, [
                'label' => 'Date de l\'évaluation',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('maxGrade', NumberType::class, [
                'label' => 'Note maximale',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '20',
                    'step' => '0.25',
                ],
                'help' => 'Note maximale (généralement 20)',
            ])
            ->add('coefficient', NumberType::class, [
                'label' => 'Coefficient',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '1',
                    'step' => '0.5',
                ],
                'help' => 'Coefficient de l\'évaluation',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Détails sur l\'évaluation...',
                ],
            ])
            ->add('isPublished', CheckboxType::class, [
                'label' => 'Publier les résultats',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
                'help' => 'Les élèves pourront voir leurs notes',
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Active',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Evaluation::class,
        ]);
    }
}

