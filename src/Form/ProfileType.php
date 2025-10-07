<?php
// src/Form/ProfileType.php
namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $options): void
    {
        $b
            ->add('fullname', TextType::class, ['label' => 'Nom complet'])
            ->add('email', EmailType::class, ['label' => 'Email'])
            ->add('fonction', TextType::class, ['required' => false, 'label' => 'Fonction']);
    }

    public function configureOptions(OptionsResolver $r): void
    {
        $r->setDefaults(['data_class' => User::class]);
    }
}
