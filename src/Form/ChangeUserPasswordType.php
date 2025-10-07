<?php
// src/Form/ChangeUserPasswordType.php
namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;

class ChangeUserPasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $options): void
    {
        $b->add('currentPassword', PasswordType::class, [
            'label' => 'Mot de passe actuel',
            'mapped' => false,
            'attr' => ['autocomplete' => 'current-password'],
            'constraints' => [
                new Assert\NotBlank(message: 'Saisissez votre mot de passe actuel.'),
                new UserPassword(message: 'Mot de passe actuel incorrect.'),
            ],
        ]);

        $b->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'mapped' => false,
            'invalid_message' => 'Les deux mots de passe ne sont pas identiques.',
            'first_options'  => [
                'label' => 'Nouveau mot de passe',
                'attr'  => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Choisissez un mot de passe.'),
                    new Assert\Length(min: 8, minMessage: 'Au moins {{ limit }} caractères.'),
                ],
            ],
            'second_options' => [
                'label' => 'Confirmation',
                'attr'  => ['autocomplete' => 'new-password'],
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $r): void
    {
        $r->setDefaults([]);
    }
}
