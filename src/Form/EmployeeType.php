<?php

namespace App\Form;

use App\Entity\Employee;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class EmployeeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // À la création, un compte utilisateur est généré automatiquement à partir
        // de la fiche employé : seule l'adresse e-mail (identifiant du compte) est demandée.
        if ($options['include_account']) {
            $builder->add('email', EmailType::class, [
                'label' => 'Adresse e-mail (compte utilisateur)',
                'mapped' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'prenom.nom@ecole.com'],
                'help' => 'Un compte utilisateur sera créé automatiquement avec cette adresse. '
                    . 'Un identifiant et un mot de passe temporaire seront générés.',
                'constraints' => [
                    new NotBlank(message: 'L\'adresse e-mail est obligatoire pour créer le compte.'),
                    new Email(message: 'L\'adresse e-mail n\'est pas valide.'),
                ],
            ]);
        }

        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Jean'],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: DUPONT'],
            ])
            ->add('employeeType', ChoiceType::class, [
                'label' => 'Type d\'employé',
                'choices' => [
                    'Enseignant' => 'enseignant',
                    'Personnel' => 'personnel',
                    'Directeur' => 'directeur',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('position', TextType::class, [
                'label' => 'Poste / Fonction',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Professeur de mathématiques'],
            ])
            ->add('department', TextType::class, [
                'label' => 'Département / Service',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Sciences'],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => ['class' => 'form-control', 'maxlength' => 10, 'inputmode' => 'numeric', 'pattern' => '\d{10}', 'placeholder' => '0700000000', 'title' => 'Exactement 10 chiffres'],
            ])
            ->add('gender', ChoiceType::class, [
                'label' => 'Genre',
                'required' => false,
                'placeholder' => 'Non spécifié',
                'choices' => [
                    'Masculin' => 'M',
                    'Féminin' => 'F',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('dateOfBirth', DateType::class, [
                'label' => 'Date de naissance',
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('address', TextareaType::class, [
                'label' => 'Adresse',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 2, 'placeholder' => 'Adresse complète'],
            ])
            ->add('salary', MoneyType::class, [
                'label' => 'Salaire de référence',
                'required' => false,
                'currency' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => '0.00'],
                'help' => 'Salaire indicatif de la fiche employé (le détail figure sur les contrats).',
            ])
            ->add('hireDate', DateType::class, [
                'label' => 'Date d\'embauche',
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('terminationDate', DateType::class, [
                'label' => 'Date de fin de fonction',
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Employé actif',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Employee::class,
            'include_account' => false,
        ]);

        $resolver->setAllowedTypes('include_account', 'bool');
    }
}
