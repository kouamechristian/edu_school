<?php

namespace App\Form;

use App\Entity\Classroom;
use App\Entity\Homework;
use App\Entity\Subject;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class HomeworkType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex : Exercices de fractions n°3'],
            ])
            ->add('classroom', EntityType::class, [
                'label' => 'Classe',
                'class' => Classroom::class,
                'choice_label' => 'name',
                'choices' => $options['classrooms'] ?? [],
                'placeholder' => 'Sélectionnez une classe',
                'attr' => ['class' => 'form-select'],
                'constraints' => [new NotBlank(message: 'La classe est obligatoire.')],
            ])
            ->add('subject', EntityType::class, [
                'label' => 'Matière',
                'class' => Subject::class,
                'choice_label' => 'name',
                'choices' => $options['subjects'] ?? [],
                'placeholder' => 'Sélectionnez une matière (facultatif)',
                'required' => false,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('dueDate', DateType::class, [
                'label' => 'Date de remise',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'constraints' => [new NotBlank(message: 'La date de remise est obligatoire.')],
            ])
            ->add('instructions', TextareaType::class, [
                'label' => 'Consignes',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 6, 'placeholder' => 'Décrivez le travail à faire…'],
            ])
            ->add('attachmentFile', FileType::class, [
                'label' => 'Pièce jointe (facultative)',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'help' => 'PDF, image ou document Word (max 5 Mo).',
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        mimeTypes: [
                            'application/pdf',
                            'image/jpeg', 'image/png', 'image/webp',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        ],
                        mimeTypesMessage: 'Veuillez téléverser un PDF, une image ou un document Word.',
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Homework::class,
            'classrooms' => [],
            'subjects' => [],
        ]);
    }
}
