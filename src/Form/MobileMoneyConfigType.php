<?php

namespace App\Form;

use App\Entity\MobileMoneyConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MobileMoneyConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('apiKey', TextType::class, [
                'label' => 'Clé API (publique)',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'pk_...'],
            ])
            // Secrets : non mappés, jamais pré-affichés ; mis à jour seulement si saisis.
            ->add('apiSecret', PasswordType::class, [
                'label' => 'Clé secrète',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => '•••• (laisser vide pour conserver)', 'autocomplete' => 'new-password'],
            ])
            ->add('webhookSecret', PasswordType::class, [
                'label' => 'Secret webhook (whsec_)',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => '•••• (laisser vide pour conserver)', 'autocomplete' => 'new-password'],
            ])
            ->add('baseUrl', UrlType::class, [
                'label' => 'URL de base de l\'API',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'https://geniuspay.ci/api/v1/merchant'],
                'help' => 'Laisser vide pour utiliser l\'URL globale par défaut.',
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Configuration active',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MobileMoneyConfig::class,
        ]);
    }
}
