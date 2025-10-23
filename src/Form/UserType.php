<?php

namespace App\Form;

use App\Entity\School;
use App\Entity\SchoolGroup;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;

        $builder
            ->add('username', TextType::class, [
                'label' => 'Nom d\'utilisateur',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: jdupont',
                ],
                'help' => 'Utilisé pour la connexion (minimum 3 caractères)',
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'email@exemple.com',
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => $isEdit ? 'Nouveau mot de passe' : 'Mot de passe',
                'mapped' => false,
                'required' => !$isEdit,
                'attr' => [
                    'class' => 'form-control',
                    'autocomplete' => 'new-password',
                ],
                'constraints' => $isEdit ? [] : [
                    new NotBlank([
                        'message' => 'Veuillez entrer un mot de passe',
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Votre mot de passe doit contenir au moins {{ limit }} caractères',
                        'max' => 4096,
                    ]),
                ],
                'help' => $isEdit ? 'Laissez vide pour conserver le mot de passe actuel' : 'Minimum 6 caractères',
            ])
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Jean',
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'DUPONT',
                ],
            ])
            ->add('userType', ChoiceType::class, [
                'label' => 'Type d\'utilisateur',
                'choices' => [
                    'Administrateur' => 'admin',
                    'Directeur' => 'directeur',
                    'Enseignant' => 'enseignant',
                    'Personnel' => 'personnel',
                    'Parent' => 'parent',
                ],
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez un type',
                'required' => false,
            ])
            ->add('schoolGroup', EntityType::class, [
                'label' => 'Groupe d\'établissements',
                'class' => SchoolGroup::class,
                'choice_label' => 'name',
                'attr' => ['class' => 'form-select', 'id' => 'user_schoolGroup'],
                'placeholder' => 'Sélectionnez un groupe',
                'required' => false,
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('sg')
                        ->where('sg.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('sg.name', 'ASC');
                },
                'help' => 'Sélectionnez d\'abord un groupe pour filtrer les établissements',
            ])
            ->add('schools', EntityType::class, [
                'label' => 'Établissement(s)',
                'class' => School::class,
                'choice_label' => 'name',
                'choice_attr' => function(School $school) {
                    return [
                        'data-group-id' => $school->getSchoolGroup() ? $school->getSchoolGroup()->getId() : '',
                    ];
                },
                'multiple' => true,
                'expanded' => false,
                'attr' => ['class' => 'form-select', 'size' => 5, 'id' => 'user_schools'],
                'required' => false,
                'help' => 'Sélectionnez un ou plusieurs établissements (Ctrl+Clic pour sélection multiple)',
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('s')
                        ->leftJoin('s.schoolGroup', 'sg')
                        ->where('s.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('sg.name', 'ASC')
                        ->addOrderBy('s.name', 'ASC');
                },
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Rôles',
                'choices' => [
                    'Utilisateur (ROLE_USER)' => 'ROLE_USER',
                    'Saisie (ROLE_SAISIE)' => 'ROLE_SAISIE',
                    'Impression (ROLE_IMPRESSION)' => 'ROLE_IMPRESSION',
                    'Modification (ROLE_MODIFICATION)' => 'ROLE_MODIFICATION',
                    'Validation (ROLE_VALIDATION)' => 'ROLE_VALIDATION',
                    'Administrateur (ROLE_ADMIN)' => 'ROLE_ADMIN',
                    'Super Admin (ROLE_SUPER_ADMIN)' => 'ROLE_SUPER_ADMIN',
                ],
                'multiple' => true,
                'expanded' => true,
                'attr' => ['class' => 'roles-checkboxes'],
                'help' => 'Sélectionnez un ou plusieurs rôles',
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '06 12 34 56 78',
                ],
            ])
            ->add('dateOfBirth', DateType::class, [
                'label' => 'Date de naissance',
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('gender', ChoiceType::class, [
                'label' => 'Genre',
                'choices' => [
                    'Masculin' => 'M',
                    'Féminin' => 'F',
                ],
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez',
                'required' => false,
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
            ->add('isActive', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Actif' => true,
                    'Inactif' => false,
                ],
                'attr' => ['class' => 'form-select'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
        ]);
    }
}

