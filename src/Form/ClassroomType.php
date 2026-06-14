<?php

namespace App\Form;

use App\Entity\Classroom;
use App\Entity\Faculty;
use App\Entity\Level;
use App\Entity\Room;
use App\Entity\Round;
use App\Entity\School;
use App\Entity\SchoolYear;
use App\Entity\User;
use App\Service\SchoolContextService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClassroomType extends AbstractType
{
    private SchoolContextService $contextService;

    public function __construct(SchoolContextService $contextService)
    {
        $this->contextService = $contextService;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Récupérer l'établissement sur lequel l'user a basculé
        $currentSchool = $this->contextService->getCurrentSchool();
        $schoolId = $currentSchool ? $currentSchool->getId() : null;

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la classe',
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('level', EntityType::class, [
                'label' => 'Niveau',
                'class' => Level::class,
                'choice_label' => 'name',
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez un niveau',
                'query_builder' => function ($repository) use ($schoolId) {
                    $qb = $repository->createQueryBuilder('l')
                        ->where('l.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('l.orderNumber', 'ASC');
                    
                    // Filtrer UNIQUEMENT par l'établissement sur lequel l'user a basculé
                    if ($schoolId) {
                        $qb->andWhere('l.school = :school')
                           ->setParameter('school', $schoolId);
                    }
                    
                    return $qb;
                },
            ])
            ->add('faculty', EntityType::class, [
                'label' => 'Filière',
                'class' => Faculty::class,
                'choice_label' => 'libelle',
                'required' => false,
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Aucune filière',
                'query_builder' => function ($repository) use ($schoolId) {
                    $qb = $repository->createQueryBuilder('f')->orderBy('f.libelle', 'ASC');
                    if ($schoolId) {
                        $qb->andWhere('f.school = :school')->setParameter('school', $schoolId);
                    }

                    return $qb;
                },
            ])
            ->add('round', EntityType::class, [
                'label' => 'Série',
                'class' => Round::class,
                'choice_label' => 'libelle',
                'required' => false,
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Aucune série',
                'query_builder' => function ($repository) use ($schoolId) {
                    $qb = $repository->createQueryBuilder('r')->orderBy('r.libelle', 'ASC');
                    if ($schoolId) {
                        $qb->andWhere('r.school = :school')->setParameter('school', $schoolId);
                    }

                    return $qb;
                },
            ])
            ->add('capacity', IntegerType::class, [
                'label' => 'Capacité maximale',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 30',
                    'min' => 1,
                ],
                'help' => 'Nombre maximum d\'élèves',
            ])
            ->add('mainTeacher', EntityType::class, [
                'label' => 'Professeur principal',
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
                    
                    // Filtrer UNIQUEMENT par l'établissement sur lequel l'user a basculé
                    if ($schoolId) {
                        $qb->innerJoin('u.schools', 's')
                           ->andWhere('s.id = :school')
                           ->setParameter('school', $schoolId);
                    }
                    
                    return $qb;
                },
            ])
            ->add('room', EntityType::class, [
                'label' => 'Salle de classe',
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
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Informations complémentaires...',
                ],
            ])
            ->add('isActive', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Active' => true,
                    'Inactive' => false,
                ],
                'attr' => ['class' => 'form-select'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Classroom::class,
        ]);
    }
}

