<?php

namespace App\Form;

use App\Entity\Subject;
use App\Entity\SubjectEquivalent;
use App\Service\SchoolContextService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire d'une matière équivalente : numéro d'ordre, code, libellé et la liste des
 * matières rattachées (sélection multiple, filtrée par l'établissement courant).
 * L'établissement n'est pas saisi : il est posé par le contrôleur.
 */
class SubjectEquivalentType extends AbstractType
{
    public function __construct(private SchoolContextService $contextService)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $schoolId = $this->contextService->getCurrentSchool()?->getId();

        $builder
            ->add('code', TextType::class, [
                'label' => 'Code',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Code de l\'équivalence'],
            ])
            ->add('libelle', TextType::class, [
                'label' => 'Libellé',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Libellé de l\'équivalence'],
            ])
            ->add('subjectParent', ChoiceType::class, [
                'label' => 'Matière',
                'required' => false,
                'placeholder' => 'Sélectionnez une matière',
                'choices' => array_combine(SubjectEquivalent::SUBJECTS, SubjectEquivalent::SUBJECTS),
                'attr' => ['class' => 'form-select'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SubjectEquivalent::class,
        ]);
    }
}
