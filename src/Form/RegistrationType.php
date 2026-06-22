<?php

namespace App\Form;

use App\Entity\Classroom;
use App\Entity\Registration;
use App\Service\SchoolContextService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire d'ÉDITION d'une inscription (table registration) : classe, redoublant,
 * boursier et statut. L'élève, l'année et la préinscription d'origine sont fixés à la
 * création (depuis la préinscription validée) et ne sont pas modifiables ici.
 */
class RegistrationType extends AbstractType
{
    public function __construct(private SchoolContextService $contextService)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $schoolId = $this->contextService->getCurrentSchool()?->getId();
        $yearId = $this->contextService->getCurrentSchoolYear()?->getId();

        $builder
            ->add('classroom', EntityType::class, [
                'label' => 'Classe',
                'class' => Classroom::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'Non affecté',
                'attr' => ['class' => 'form-select'],
                'help' => 'Laisser vide pour un élève inscrit mais non encore affecté à une classe.',
                'query_builder' => function ($repository) use ($schoolId, $yearId) {
                    $qb = $repository->createQueryBuilder('c')
                        ->andWhere('c.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('c.name', 'ASC');
                    if ($schoolId) {
                        $qb->andWhere('c.school = :school')->setParameter('school', $schoolId);
                    }
                    if ($yearId) {
                        $qb->andWhere('c.schoolYear = :year')->setParameter('year', $yearId);
                    }

                    return $qb;
                },
            ])
            ->add('isRepeating', ChoiceType::class, [
                'label' => 'Redoublant',
                'choices' => ['Non' => false, 'Oui' => true],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('boursier', ChoiceType::class, [
                'label' => 'Boursier',
                'choices' => ['Non' => false, 'Oui' => true],
                'attr' => ['class' => 'form-select'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Registration::class,
        ]);
    }
}
