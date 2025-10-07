<?php
// src/Form/UserType.php
namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $options): void
    {
        $mode = $options['mode']; // 'create' | 'edit'

        $b
            ->add('fullname', TextType::class, ['label' => 'Nom complet'])
            ->add('email', EmailType::class, ['label' => 'Email'])
            ->add('fonction', TextType::class, ['required' => false, 'label' => 'Fonction'])
            ->add('roles', ChoiceType::class, [
                'label' => 'Rôles',
                'multiple' => true,
                'expanded' => false,
                'choices' => [
                    'Utilisateur' => 'ROLE_USER',
                    'Administrateur' => 'ROLE_ADMIN',
                ],
            ]);

        if ($mode === 'create') {
            $b->add('plainPassword', PasswordType::class, [
                'label' => 'Mot de passe',
                'mapped' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $r): void
    {
        $r->setDefaults([
            'data_class' => User::class,
            'mode' => 'edit', // par défaut
        ]);
    }
}
