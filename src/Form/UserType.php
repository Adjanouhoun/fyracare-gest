<?php
// src/Form/UserType.php
namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $options): void
    {
        $mode = $options['mode']; // 'create' | 'edit'

        $b
            ->add('fullname', TextType::class, ['label' => 'Nom complet'])
            ->add('email', EmailType::class, ['label' => 'Email'])
            ->add('fonction', TextType::class, ['required' => true, 'label' => 'Fonction'])
            ->add('roles', ChoiceType::class, [
                'label' => 'Rôles',
                'multiple' => true,
                'expanded' => false,
                'choices' => [
                    'Réception et caisse' => 'ROLE_RECEPTION_CAISSE',
                    'Manager' => 'ROLE_MANAGER',
                    'Administrateur' => 'ROLE_ADMIN',
                ],
                'constraints' => [
                    new Count(
                        min: 1,
                        max: 1,
                        minMessage: 'Sélectionnez un rôle.',
                        maxMessage: 'Un utilisateur ne doit avoir qu’un seul rôle principal.',
                    ),
                ],
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'Compte actif',
                'help' => 'Un compte inactif est conservé dans l’historique mais ne peut pas se connecter.',
                'mapped' => false,
                'required' => false,
                'data' => $options['data'] instanceof User ? $options['data']->isActive() : true,
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
