<?php

namespace App\Form;

use App\Entity\Level;
use App\Entity\School;
use App\Entity\Subject;
use App\Entity\SubjectType as SubjectTypeEntity;
use App\Service\SchoolContextService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

class SubjectType extends AbstractType
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
                'label' => 'Nom de la matière',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Mathématiques',
                ],
            ])
        
            ->add('level', EntityType::class, [
                'label' => 'Niveau',
                'class' => Level::class,
                'choice_label' => 'name',
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez un niveau',
                'required' => true,
                'constraints' => [
                    new NotNull(['message' => 'Le niveau est obligatoire.']),
                ],
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
            ->add('type', EntityType::class, [
                'label' => 'Type de matière',
                'class' => SubjectTypeEntity::class,
                'choice_label' => 'label',
                'placeholder' => 'Sélectionnez un type',
                'attr' => ['class' => 'form-select'],
                'query_builder' => fn ($repository) => $repository->createQueryBuilder('t')
                    ->orderBy('t.orderNumber', 'ASC'),
            ])
            ->add('coefficient', NumberType::class, [
                'label' => 'Coefficient',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 2.5',
                    'step' => '0.5',
                ],
                'help' => 'Coefficient pour le calcul de la moyenne',
            ])
            ->add('hoursPerWeek', IntegerType::class, [
                'label' => 'Heures par semaine',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 4',
                    'min' => 1,
                ],
            ])
            ->add('bulletinOrderNumber', IntegerType::class, [
                'label' => 'Numéro d\'ordre sur le bulletin',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 1',
                    'min' => 0,
                ],
                'help' => 'Ordre d\'affichage de la matière sur le bulletin.',
            ])
            ->add('color', ColorType::class, [
                'label' => 'Couleur (emploi du temps)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control form-control-color',
                ],
                'help' => 'Couleur pour l\'affichage dans l\'emploi du temps',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Programme, objectifs...',
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
            'data_class' => Subject::class,
        ]);
    }
}

