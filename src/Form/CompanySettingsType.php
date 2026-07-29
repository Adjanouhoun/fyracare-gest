<?php

namespace App\Form;

use App\Entity\CompanySettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{CheckboxType,EmailType,TelType,TextareaType,FileType,IntegerType};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class CompanySettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $o): void
    {
        $b->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'required' => true,
                'attr' => ['placeholder' => '+222 …'],
            ])
          ->add('email', EmailType::class, [
                'label' => 'E-mail',
                'required' => true,
            ])
          ->add('address', TextareaType::class, [
                'label' => 'Adresse',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
          ->add('confirmationReminderHours', IntegerType::class, [
                'label' => 'Alerter avant le rendez-vous (heures)',
                'help' => '24 heures par défaut. Valeur autorisée : de 1 à 168 heures.',
                'attr' => ['min' => 1, 'max' => 168],
            ])
          ->add('logoInvoiceFile', FileType::class, [
                'label' => 'Nouveau logo des reçus et factures',
                'mapped' => false,
                'required' => false,
                'help' => 'PNG, JPG ou WEBP — 3 Mo maximum. Format horizontal recommandé.',
                'attr' => ['accept' => 'image/png,image/jpeg,image/webp'],
                'constraints' => [
                    new Assert\Image(
                        maxSize: '3M',
                        mimeTypes: ['image/png', 'image/jpeg', 'image/webp'],
                        mimeTypesMessage: 'Formats acceptés : PNG, JPG, WEBP.',
                    ),
                ],
            ])
          ->add('removeLogoInvoice', CheckboxType::class, [
                'label' => 'Retirer le logo actuel des reçus',
                'mapped' => false,
                'required' => false,
            ])
        ->add('logoAppFile', FileType::class, [
                'label' => 'Nouveau logo de l’application',
                'mapped' => false,
                'required' => false,
                'help' => 'PNG, JPG ou WEBP — 3 Mo maximum. Fond transparent recommandé.',
                'attr' => ['accept' => 'image/png,image/jpeg,image/webp'],
                'constraints' => [
                    new Assert\Image(
                        maxSize: '3M',
                        mimeTypes: ['image/png', 'image/jpeg', 'image/webp'],
                        mimeTypesMessage: 'Formats acceptés : PNG, JPG, WEBP.',
                    ),
                ],
            ])
        ->add('removeLogoApp', CheckboxType::class, [
                'label' => 'Retirer le logo actuel de l’application',
                'mapped' => false,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CompanySettings::class]);
    }
}
