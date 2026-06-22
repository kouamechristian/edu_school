<?php

namespace App\Form;

use App\Entity\Classroom;
use App\Entity\PreRegistration;
use App\Service\SchoolContextService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire de création d'une inscription à partir d'une préinscription validée.
 *
 * Non lié à l'entité Registration : l'élève n'existe pas forcément encore (il est créé
 * ou réutilisé par EnrollmentService à la soumission). Le formulaire retourne donc un
 * tableau (préinscription, classe, redoublant, boursier) exploité par le contrôleur.
 * Le statut de l'inscription n'est pas saisi : il vaut « active » par défaut.
 */
class RegistrationEnrollType extends AbstractType
{
    public function __construct(private SchoolContextService $contextService)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $schoolId = $this->contextService->getCurrentSchool()?->getId();
        $yearId = $this->contextService->getCurrentSchoolYear()?->getId();
        $excludedClassroomIds = $options['excluded_classroom_ids'];
        $remainingByClassroom = $options['remaining_by_classroom'];

        $builder
            ->add('preRegistration', EntityType::class, [
                'label' => 'Préinscription validée',
                'class' => PreRegistration::class,
                'choice_label' => fn (PreRegistration $p) => sprintf(
                    '%s %s%s (%s)',
                    $p->getLastName(),
                    $p->getFirstName(),
                    $p->getRequestedLevel() ? ' — ' . $p->getRequestedLevel()->getName() : '',
                    $p->getTypeLabel()
                ),
                // data-level sert au filtrage JS des classes selon le niveau demandé.
                'choice_attr' => fn (PreRegistration $p) => ['data-level' => (string) ($p->getRequestedLevel()?->getId() ?? '')],
                'placeholder' => 'Sélectionner une préinscription validée…',
                'attr' => ['class' => 'form-select'],
                'query_builder' => function ($repository) use ($schoolId, $yearId) {
                    $qb = $repository->createQueryBuilder('p')
                        ->andWhere('p.status = :validated')
                        ->setParameter('validated', 'validated')
                        ->orderBy('p.lastName', 'ASC')
                        ->addOrderBy('p.firstName', 'ASC');
                    if ($schoolId) {
                        $qb->andWhere('p.school = :school')->setParameter('school', $schoolId);
                    }
                    if ($yearId) {
                        $qb->andWhere('(p.schoolYear = :year OR p.schoolYear IS NULL)')->setParameter('year', $yearId);
                    }

                    return $qb;
                },
            ])
            ->add('classroom', EntityType::class, [
                'label' => 'Classe d\'affectation',
                'class' => Classroom::class,
                // Affiche les places restantes quand la capacité est connue.
                'choice_label' => function (Classroom $c) use ($remainingByClassroom) {
                    if (array_key_exists($c->getId(), $remainingByClassroom)) {
                        $left = $remainingByClassroom[$c->getId()];
                        return sprintf('%s (reste %d place%s)', $c->getName(), $left, $left > 1 ? 's' : '');
                    }

                    return $c->getName();
                },
                'choice_attr' => fn (Classroom $c) => ['data-level' => (string) ($c->getLevel()?->getId() ?? '')],
                'placeholder' => 'Choisir une classe…',
                // Le contenu de ce select est réécrit dynamiquement en JS (filtrage par
                // niveau de la préinscription) ; on le laisse en select natif (no-search)
                // car le widget TomSelect ne reflète pas les <option> ajoutées à la volée.
                'attr' => ['class' => 'form-select no-search'],
                'query_builder' => function ($repository) use ($schoolId, $yearId, $excludedClassroomIds) {
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
                    // Exclut les classes pleines (capacité atteinte).
                    if ($excludedClassroomIds !== []) {
                        $qb->andWhere('c.id NOT IN (:excluded)')->setParameter('excluded', $excludedClassroomIds);
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
            // Formulaire non lié à une entité : retourne un tableau de valeurs.
            'data_class' => null,
            // Classes pleines à exclure du choix + places restantes par classe.
            'excluded_classroom_ids' => [],
            'remaining_by_classroom' => [],
        ]);
        $resolver->setAllowedTypes('excluded_classroom_ids', 'array');
        $resolver->setAllowedTypes('remaining_by_classroom', 'array');
    }
}
