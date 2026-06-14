<?php

namespace App\Form;

use App\Entity\Subject;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Attribution des matières enseignées par un enseignant (User).
 */
class TeacherSubjectsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('teachingSubjects', EntityType::class, [
                'label' => 'Matières enseignées',
                'class' => Subject::class,
                // Libellé : nom de la matière + niveau associé entre parenthèses.
                'choice_label' => fn (Subject $subject) => $subject->getLevel()
                    ? sprintf('%s (%s)', $subject->getName(), $subject->getLevel()->getName())
                    : $subject->getName(),
                'choices' => $options['subjects'] ?? [],
                'multiple' => true,
                'expanded' => false,
                'by_reference' => false,
                'attr' => [
                    'class' => 'form-select js-subject-select',
                    'data-placeholder' => 'Rechercher une matière...',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'subjects' => [],
        ]);
    }
}
