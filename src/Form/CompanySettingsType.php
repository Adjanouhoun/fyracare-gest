<?php

namespace App\Form;

use App\Entity\CompanySettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{EmailType,TelType,TextareaType,FileType,TextType};
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
          ->add('logoFile', FileType::class, [
                'label' => 'Logo (PNG/JPG)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Assert\Image(
                        maxSize: '3M',
                        mimeTypes: ['image/png', 'image/jpeg', 'image/webp'],
                        mimeTypesMessage: 'Formats acceptés : PNG, JPG, WEBP.',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CompanySettings::class]);
    }
}
