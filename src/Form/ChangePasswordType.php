<?php
// src/Form/ChangePasswordType.php
namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;

class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('currentPassword', PasswordType::class, [
            'required' => true,
            'mapped'   => false,
            'attr'     => ['autocomplete' => 'current-password'],
            'constraints' => [
                new Assert\NotBlank(message: 'Saisissez votre mot de passe actuel'),
                new UserPassword(message: 'Le mot de passe actuel est incorrect'),
            ],
        ]);

        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'mapped' => false,                 // on encode en contrôleur
            'invalid_message' => 'Les mots de passe ne correspondent pas.',
            'first_options'  => [
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Choisissez un mot de passe'),
                    new Assert\Length(min: 8, minMessage: 'Au moins {{ limit }} caractères'),
                ],
            ],
            'second_options' => [
                'attr' => ['autocomplete' => 'new-password'],
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // pas de data_class, on travaille avec l’entité User dans le contrôleur
        ]);
    }
}
