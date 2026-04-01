<?php

namespace App\Form;

use App\Entity\Student;
use App\Entity\Level;
use App\Entity\SchoolYear;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StudentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['class' => 'form-control']
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'attr' => ['class' => 'form-control']
            ])
            ->add('dateOfBirth', DateType::class, [
                'label' => 'Date de naissance',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control']
            ])
            ->add('gender', ChoiceType::class, [
                'label' => 'Genre',
                'choices' => [
                    'Masculin' => 'M',
                    'Féminin' => 'F'
                ],
                'attr' => ['class' => 'form-control']
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('address', TextareaType::class, [
                'label' => 'Adresse',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 3]
            ])
            ->add('studentNumber', TextType::class, [
                'label' => 'Numéro d\'élève',
                'required' => false,
                'attr' => ['class' => 'form-control', 'readonly' => true]
            ])
            ->add('parentName', TextType::class, [
                'label' => 'Nom du parent/tuteur',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('parentPhone', TelType::class, [
                'label' => 'Téléphone du parent',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('parentEmail', EmailType::class, [
                'label' => 'Email du parent',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('emergencyContact', TextType::class, [
                'label' => 'Contact d\'urgence',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('emergencyPhone', TelType::class, [
                'label' => 'Téléphone d\'urgence',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('medicalInfo', TextareaType::class, [
                'label' => 'Informations médicales',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 3]
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 3]
            ])
            ->add('level', EntityType::class, [
                'class' => Level::class,
                'choice_label' => 'name',
                'label' => 'Niveau',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('schoolYear', EntityType::class, [
                'class' => SchoolYear::class,
                'choice_label' => 'name',
                'label' => 'Année scolaire',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Affecté' => 'affecte',
                    'Non affecté' => 'non_affecte',
                ],
                'attr' => ['class' => 'form-control']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Student::class,
        ]);
    }
}
