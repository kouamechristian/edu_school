<?php

namespace App\Form;

use App\Entity\Student;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

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
                'required' => false,
                'placeholder' => 'Sélectionnez',
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
            ->add('matriculeInterne', TextType::class, [
                'label' => 'Matricule interne',
                'required' => false,
                'attr' => ['class' => 'form-control', 'readonly' => true],
                'help' => 'Généré automatiquement par le système.',
            ])
            ->add('matriculeNational', TextType::class, [
                'label' => 'Matricule national',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Numéro de matricule national'],
            ])
            ->add('placeOfBirth', TextType::class, [
                'label' => 'Lieu de naissance',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ville / commune de naissance'],
            ])
            ->add('nationality', TextType::class, [
                'label' => 'Nationalité',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Ivoirienne'],
            ])
            ->add('birthCertificateNumber', TextType::class, [
                'label' => 'Numéro extrait de naissance',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'N° de l\'extrait de naissance'],
            ])
            ->add('cmuNumber', TextType::class, [
                'label' => 'Numéro CMU',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'N° CMU'],
            ])
            ->add('lastSchoolAttended', TextType::class, [
                'label' => 'Dernière école fréquentée',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Nom de la dernière école'],
            ])
            ->add('photoFile', FileType::class, [
                'label' => 'Photo de l\'élève',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control', 'accept' => 'image/*'],
                'help' => 'JPG, PNG ou WEBP (max 2 Mo).',
                'constraints' => [
                    new File(
                        maxSize: '2M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                        mimeTypesMessage: 'Veuillez téléverser une image valide (JPG, PNG, WEBP, GIF).'
                    ),
                ],
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
            ->add('parentFunction', TextType::class, [
                'label' => 'Fonction du parent/tuteur',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Commerçant, Enseignant...'],
            ])
            ->add('parentAddress', TextareaType::class, [
                'label' => 'Domicile du parent/tuteur',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 2, 'placeholder' => 'Domicile du parent/tuteur'],
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
            ]);
        // Niveau / classe / statut / redoublant relèvent de l'inscription
        // (Registration) et se gèrent via l'inscription, le transfert et le bouton
        // de statut — plus sur la fiche élève (référentiel).
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Student::class,
        ]);
    }
}
