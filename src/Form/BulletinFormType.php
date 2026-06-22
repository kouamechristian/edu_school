<?php

namespace App\Form;

use App\Entity\Bulletin;
use App\Entity\Level;
use App\Entity\Period;
use App\Service\SchoolContextService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire de création d'un bulletin : libellé, base de la moyenne (« moyenne sur »),
 * niveau et période — niveaux et périodes restreints à l'établissement / l'année courants.
 */
class BulletinFormType extends AbstractType
{
    public function __construct(private SchoolContextService $contextService)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $schoolId = $this->contextService->getCurrentSchool()?->getId();
        $yearId = $this->contextService->getCurrentSchoolYear()?->getId();

        $builder
            ->add('libelle', TextType::class, [
                'label' => 'Libellé',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex : Bulletin 1er trimestre'],
            ])
            ->add('moyenneSur', IntegerType::class, [
                'label' => 'Moyenne sur',
                'attr' => ['class' => 'form-control', 'min' => 1, 'max' => 100, 'placeholder' => '20'],
                'help' => 'Base de la moyenne (ex. 20).',
            ])
            ->add('level', EntityType::class, [
                'label' => 'Niveau',
                'class' => Level::class,
                'choice_label' => 'name',
                'placeholder' => 'Sélectionnez un niveau',
                'attr' => ['class' => 'form-select'],
                'query_builder' => fn ($repo) => $repo->createQueryBuilder('l')
                    ->andWhere('l.school = :school')
                    ->setParameter('school', $schoolId)
                    ->orderBy('l.orderNumber', 'ASC')
                    ->addOrderBy('l.name', 'ASC'),
            ])
            ->add('period', EntityType::class, [
                'label' => 'Période',
                'class' => Period::class,
                'choice_label' => 'name',
                'placeholder' => 'Sélectionnez une période',
                'attr' => ['class' => 'form-select'],
                'query_builder' => fn ($repo) => $repo->createQueryBuilder('p')
                    ->andWhere('p.schoolYear = :year')
                    ->setParameter('year', $yearId)
                    ->orderBy('p.name', 'ASC'),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Bulletin::class,
        ]);
    }
}
