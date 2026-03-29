<?php

namespace App\Form;

use App\Entity\DocumentType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DocumentTypeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du type de document',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Certificat de naissance',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Description du type de document',
                ],
            ])
            ->add('isRequired', CheckboxType::class, [
                'label' => 'Document obligatoire',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Actif',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('maxFileSize', IntegerType::class, [
                'label' => 'Taille maximale (en octets)',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '5242880 (5MB)',
                ],
                'help' => 'Taille maximale en octets. Ex: 5242880 pour 5MB',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DocumentType::class,
        ]);
    }
}
