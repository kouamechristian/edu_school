<?php

namespace App\Form;

use App\Entity\Student;
use App\Entity\StudentDropout;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StudentDropoutType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('student', EntityType::class, [
                'label' => 'Élève',
                'class' => Student::class,
                'choices' => $options['students'],
                'choice_label' => fn (Student $s) => trim($s->getLastName() . ' ' . $s->getFirstName())
                    . ($s->getMatriculeInterne() ? ' — ' . $s->getMatriculeInterne() : ''),
                'placeholder' => 'Sélectionnez un élève',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('dropoutDate', DateType::class, [
                'label' => 'Date de l\'abandon',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('reason', TextareaType::class, [
                'label' => 'Motif de l\'abandon',
                'attr' => ['class' => 'form-control', 'rows' => 4, 'placeholder' => 'Précisez le motif de l\'abandon...'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => StudentDropout::class,
            'students' => [],
        ]);
    }
}
