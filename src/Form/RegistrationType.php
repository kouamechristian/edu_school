<?php

namespace App\Form;

use App\Entity\Registration;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire d'ÉDITION d'une inscription (table registration) : seuls le statut
 * Redoublant et le statut Boursier sont modifiables. L'élève, l'année, la classe et
 * la préinscription d'origine sont fixés à la création et ne le sont pas ici.
 */
class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('isRepeating', ChoiceType::class, [
                'label' => 'Redoublant',
                'choices' => ['Non' => false, 'Oui' => true],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('boursier', ChoiceType::class, [
                'label' => 'Boursier',
                'choices' => ['Non' => false, 'Oui' => true],
                'attr' => ['class' => 'form-select'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Registration::class,
        ]);
    }
}
