<?php

namespace App\Form;

use App\Entity\PreRegistration;
use App\Entity\Level;
use App\Entity\SchoolYear;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Validator\Constraints\NotBlank;
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

class PreRegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Jean',
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'DUPONT',
                ],
            ])
            ->add('matriculeNational', TextType::class, [
                'label' => 'Matricule national',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Numéro de matricule national',
                ],
            ])
            ->add('dateOfBirth', DateType::class, [
                'label' => 'Date de naissance',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('gender', ChoiceType::class, [
                'label' => 'Genre',
                'required' => false,
                'choices' => [
                    'Masculin' => 'M',
                    'Féminin' => 'F',
                ],
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez',
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'maxlength' => 10,
                    'inputmode' => 'numeric',
                    'pattern' => '\d{10}',
                    'placeholder' => '0700000000',
                    'title' => 'Exactement 10 chiffres',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'email@exemple.com',
                ],
            ])
            ->add('address', TextareaType::class, [
                'label' => 'Adresse',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Adresse complète',
                ],
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
            ->add('isRepeating', ChoiceType::class, [
                'label' => 'Doublant',
                'required' => false,
                'choices' => ['Non' => false, 'Oui' => true],
                'expanded' => false,
                'placeholder' => false,
                'attr' => ['class' => 'form-select'],
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
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Nom du parent',
                ],
            ])
            ->add('parentPhone', TelType::class, [
                'label' => 'Téléphone du parent',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'maxlength' => 10,
                    'inputmode' => 'numeric',
                    'pattern' => '\d{10}',
                    'placeholder' => '0700000000',
                    'title' => 'Exactement 10 chiffres',
                ],
            ])
            ->add('parentEmail', EmailType::class, [
                'label' => 'Email du parent',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'parent@exemple.com',
                ],
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
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Nom du contact d\'urgence',
                ],
            ])
            ->add('emergencyPhone', TelType::class, [
                'label' => 'Téléphone d\'urgence',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'maxlength' => 10,
                    'inputmode' => 'numeric',
                    'pattern' => '\d{10}',
                    'placeholder' => '0700000000',
                    'title' => 'Exactement 10 chiffres',
                ],
            ])
            ->add('medicalInfo', TextType::class, [
                'label' => 'Informations médicales',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Allergies, médicaments, etc.',
                ],
            ])
            ->add('requestedLevel', EntityType::class, [
                'label' => 'Niveau demandé',
                'class' => Level::class,
                'choice_label' => 'name',
                'choices' => $options['levels'] ?? [],
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez un niveau',
                'required' => true,
                'constraints' => [new NotBlank(message: 'Le niveau demandé est obligatoire.')],
            ])
            ->add('schoolYear', EntityType::class, [
                'label' => 'Année scolaire',
                'class' => SchoolYear::class,
                'choice_label' => 'name',
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez une année scolaire',
                'required' => true,
                'constraints' => [new NotBlank(message: 'L\'année scolaire est obligatoire.')],
                'query_builder' => fn ($repo) => $repo->createQueryBuilder('sy')->orderBy('sy.startDate', 'DESC'),
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Notes supplémentaires',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PreRegistration::class,
            'levels' => [],
        ]);
    }
}
