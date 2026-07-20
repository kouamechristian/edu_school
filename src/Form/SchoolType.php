<?php

namespace App\Form;

use App\Entity\School;
use App\Entity\SchoolGroup;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Regex;

class SchoolType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de l\'établissement',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: École Primaire Jean Moulin'],
            ])
            ->add('code', TextType::class, [
                'label' => 'Code établissement',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Laisser vide pour génération automatique'],
                'help' => 'Code unique pour identifier l\'établissement. Généré automatiquement si laissé vide.',
            ])
            ->add('schoolGroup', EntityType::class, [
                'label' => 'Groupe d\'établissements',
                'class' => SchoolGroup::class,
                'choice_label' => 'name',
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez un groupe',
                'required' => false,
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('sg')
                        ->where('sg.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('sg.name', 'ASC');
                },
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type d\'établissement',
                'choices' => [
                    'PRESCOLAIRE-PRIMAIRE' => 'PRESCOLAIRE-PRIMAIRE',
                    'SECONDAIRE GENERAL' => 'SECONDAIRE GENERAL',
                    'TECHNIQUE ET PROFESSIONNEL' => 'TECHNIQUE ET PROFESSIONNEL',
                    'UNIVERSITE' => 'UNIVERSITE',
                ],
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionnez un type',
            ])
            ->add('director', TextType::class, [
                'label' => 'Nom du directeur/directrice',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: M. Jean DUPONT'],
            ])
            ->add('address', TextareaType::class, [
                'label' => 'Adresse complète',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => '15 Rue de la République, 75001 Paris',
                ],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => ['class' => 'form-control', 'maxlength' => 10, 'inputmode' => 'numeric', 'pattern' => '\d{10}', 'placeholder' => '0700000000', 'title' => 'Exactement 10 chiffres'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'contact@ecole.com'],
            ])
            ->add('logoFile', FileType::class, [
                'label' => 'Logo de l\'établissement',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control', 'accept' => 'image/*'],
                'help' => 'Formats acceptés : JPG, PNG, GIF, SVG, WEBP (max 2 Mo).',
                'constraints' => [
                    new File(
                        maxSize: '2M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'],
                        mimeTypesMessage: 'Veuillez téléverser une image valide (JPG, PNG, GIF, SVG, WEBP).'
                    ),
                ],
            ])
            ->add('cachetDirectionFile', FileType::class, [
                'label' => 'Cachet de la direction',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control', 'accept' => 'image/*'],
                'help' => 'Image du cachet/tampon officiel de la direction (max 2 Mo).',
                'constraints' => [
                    new File(
                        maxSize: '2M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'],
                        mimeTypesMessage: 'Veuillez téléverser une image valide (JPG, PNG, GIF, SVG, WEBP).'
                    ),
                ],
            ])
            ->add('badgeBackgroundColor', TextType::class, [
                'label' => 'Couleur de fond du badge',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: #2563eb (laisser vide pour aucune)',
                    'maxlength' => 20,
                ],
                'help' => 'Couleur du badge de l\'établissement au format hexadécimal. Laisser vide pour aucune couleur.',
                'constraints' => [
                    new Regex(
                        pattern: '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/',
                        message: 'Veuillez saisir une couleur hexadécimale valide (ex: #2563eb).',
                        match: true
                    ),
                ],
            ])
            ->add('sousTutelle', TextType::class, [
                'label' => 'Sous-tutelle',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Ministère de l\'Éducation Nationale'],
                'help' => 'Nom de l\'organisme de tutelle. Laisser vide si aucun.',
            ])
            ->add('isActive', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Actif' => true,
                    'Inactif' => false,
                ],
                'attr' => ['class' => 'form-select'],
            ])

            // ── Paiement en ligne (GeniusPay) ────────────────────────────────
            // Champs NON mappés : l'entité ne contient que la forme chiffrée.
            // Un mapping direct écrirait les clés en clair en base.
            // Le chiffrement est fait par GeniusPayCredentialsUpdater.
            ->add('geniuspayApiKeyPlain', TextType::class, [
                'label' => 'Clé API publique',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'pk_sandbox_… ou pk_live_…', 'autocomplete' => 'off'],
                'help' => 'Fournie par votre tableau de bord GeniusPay. Laisser vide pour ne pas modifier.',
            ])
            ->add('geniuspayApiSecretPlain', PasswordType::class, [
                'label' => 'Clé API secrète',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'sk_sandbox_… ou sk_live_…', 'autocomplete' => 'new-password'],
                'help' => 'Stockée chiffrée et jamais réaffichée. Laisser vide pour ne pas modifier.',
            ])
            ->add('geniuspayWebhookSecretPlain', PasswordType::class, [
                'label' => 'Secret de webhook',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'whsec_…', 'autocomplete' => 'new-password'],
                'help' => 'Indispensable : sans lui, les paiements ne peuvent pas être confirmés automatiquement.',
            ])
            ->add('geniuspayEnabledPlain', ChoiceType::class, [
                'label' => 'Paiement en ligne',
                'mapped' => false,
                'required' => false,
                'choices' => [
                    'Désactivé' => false,
                    'Activé' => true,
                ],
                'data' => $options['geniuspay_enabled'],
                'attr' => ['class' => 'form-select'],
                'help' => 'Active le règlement de la scolarité en ligne depuis l’espace parent.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => School::class,
            // Le champ « activé » n'étant pas mappé, sa valeur initiale doit être
            // fournie par le contrôleur en modification.
            'geniuspay_enabled' => false,
        ]);
        $resolver->setAllowedTypes('geniuspay_enabled', 'bool');
    }
}

