<?php

namespace App\Form;

use App\Entity\Classroom;
use App\Entity\Course;
use App\Entity\Room;
use App\Entity\Subject;
use App\Entity\TimeSlot;
use App\Entity\User;
use App\Service\SchoolContextService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CourseType extends AbstractType
{
    private SchoolContextService $contextService;

    public function __construct(SchoolContextService $contextService)
    {
        $this->contextService = $contextService;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $currentSchool = $this->contextService->getCurrentSchool();
        $currentSchoolYear = $this->contextService->getCurrentSchoolYear();
        $schoolId = $currentSchool ? $currentSchool->getId() : null;
        $yearId = $currentSchoolYear ? $currentSchoolYear->getId() : null;

        $builder
            ->add('classroom', EntityType::class, [
                'label' => 'Classe',
                'class' => Classroom::class,
                'choice_label' => 'fullName',
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez une classe',
                'query_builder' => function ($repository) use ($schoolId, $yearId) {
                    $qb = $repository->createQueryBuilder('c')
                        ->where('c.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('c.name', 'ASC');
                    
                    // Filtrer par l'établissement courant
                    if ($schoolId) {
                        $qb->andWhere('c.school = :school')
                           ->setParameter('school', $schoolId);
                    }
                    
                    // Filtrer par l'année scolaire courante
                    if ($yearId) {
                        $qb->andWhere('c.schoolYear = :year')
                           ->setParameter('year', $yearId);
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
                'query_builder' => function ($repository) use ($schoolId) {
                    $qb = $repository->createQueryBuilder('s')
                        ->where('s.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('s.name', 'ASC');
                    
                    // Filtrer par l'établissement courant
                    if ($schoolId) {
                        $qb->andWhere('s.school = :school')
                           ->setParameter('school', $schoolId);
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
                'query_builder' => function ($repository) use ($schoolId) {
                    $qb = $repository->createQueryBuilder('u')
                        ->where('u.userType = :type')
                        ->andWhere('u.isActive = :active')
                        ->setParameter('type', 'enseignant')
                        ->setParameter('active', true)
                        ->orderBy('u.lastName', 'ASC');
                    
                    // Filtrer par l'établissement courant
                    if ($schoolId) {
                        $qb->innerJoin('u.schools', 'sc')
                           ->andWhere('sc.id = :school')
                           ->setParameter('school', $schoolId);
                    }
                    
                    return $qb;
                },
            ])
            ->add('dayOfWeek', ChoiceType::class, [
                'label' => 'Jour de la semaine',
                'choices' => [
                    'Lundi' => 'lundi',
                    'Mardi' => 'mardi',
                    'Mercredi' => 'mercredi',
                    'Jeudi' => 'jeudi',
                    'Vendredi' => 'vendredi',
                    'Samedi' => 'samedi',
                    'Dimanche' => 'dimanche',
                ],
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez un jour',
            ])
            ->add('timeSlot', EntityType::class, [
                'label' => 'Plage horaire',
                'class' => TimeSlot::class,
                'choice_label' => function(TimeSlot $timeSlot) {
                    return sprintf('%s (%s)', $timeSlot->getName(), $timeSlot->getTimeRange());
                },
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez une plage horaire',
                'query_builder' => function ($repository) use ($schoolId) {
                    $qb = $repository->createQueryBuilder('t')
                        ->where('t.isActive = :active')
                        ->andWhere('t.type = :type')
                        ->setParameter('active', true)
                        ->setParameter('type', 'cours')
                        ->orderBy('t.orderNumber', 'ASC');
                    
                    // Filtrer par l'établissement courant
                    if ($schoolId) {
                        $qb->andWhere('t.school = :school')
                           ->setParameter('school', $schoolId);
                    }
                    
                    return $qb;
                },
                'help' => 'Sélectionnez une plage horaire de type "Cours"',
            ])
            ->add('room', EntityType::class, [
                'label' => 'Salle',
                'class' => Room::class,
                'choice_label' => 'fullName',
                'required' => false,
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez une salle',
                'query_builder' => function ($repository) use ($schoolId) {
                    $qb = $repository->createQueryBuilder('r')
                        ->where('r.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('r.code', 'ASC');
                    
                    // Filtrer par l'établissement courant
                    if ($schoolId) {
                        $qb->andWhere('r.school = :school')
                           ->setParameter('school', $schoolId);
                    }
                    
                    return $qb;
                },
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Remarques',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Notes, remarques...',
                ],
            ])
            ->add('isActive', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Actif' => true,
                    'Inactif' => false,
                ],
                'attr' => ['class' => 'form-select'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Course::class,
        ]);
    }
}

