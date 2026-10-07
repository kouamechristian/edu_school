<?php

namespace App\Form;

use App\Entity\School;
use App\Entity\SchoolGroup;
use App\Entity\User;
use App\Security\RoleGrantPolicy;
use App\Service\SchoolContextService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
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
    public function __construct(
        private readonly Security $security,
        private readonly RoleGrantPolicy $roleGrantPolicy,
        private readonly SchoolContextService $context,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;

        // Rôles et établissements proposés : limités à ce que l'utilisateur connecté
        // peut lui-même attribuer (cf. RoleGrantPolicy) et aux établissements qu'il
        // gère. ChoiceType/EntityType rejettent toute valeur soumise hors de ces choix.
        $actor = $this->security->getUser();
        $roleChoices = $actor instanceof User ? $this->roleGrantPolicy->grantableChoices($actor) : [];
        $allowedSchoolIds = $actor instanceof User && RoleGrantPolicy::isRealSuperAdmin($actor)
            ? null
            : array_map(static fn (School $school) => $school->getId(), $this->context->getAvailableSchools());

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
                    'Élève' => 'eleve',
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
                'query_builder' => function ($repository) use ($allowedSchoolIds) {
                    $qb = $repository->createQueryBuilder('s')
                        ->leftJoin('s.schoolGroup', 'sg')
                        ->where('s.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('sg.name', 'ASC')
                        ->addOrderBy('s.name', 'ASC');

                    if ($allowedSchoolIds !== null) {
                        $qb->andWhere('s.id IN (:allowed)')->setParameter('allowed', $allowedSchoolIds ?: [0]);
                    }

                    return $qb;
                },
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Rôles',
                'choices' => $roleChoices,
                'multiple' => true,
                'expanded' => true,
                'attr' => ['class' => 'roles-checkboxes'],
                'help' => 'Sélectionnez un ou plusieurs rôles. Les rôles supérieurs héritent automatiquement des rôles inférieurs.',
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

