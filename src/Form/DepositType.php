<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{
    MoneyType, ChoiceType, TextType, TextareaType
};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class DepositType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $o)
    {
        $b
            // MONTANT — obligatoire, > 0
            ->add('amount', MoneyType::class, [
                'currency'    => 'MRU',
                'scale'       => 0,
                'label'       => 'Montant',
                'required'    => true,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le montant est obligatoire.'),
                    new Assert\Type(type: 'numeric', message: 'Le montant doit être numérique.'),
                    new Assert\GreaterThan(value: 0, message: 'Le montant doit être strictement positif.'),
                ],
            ])

            // SOURCE DES FONDS — obligatoire
            ->add('source', ChoiceType::class, [
                'choices'     => [
                    'Espèces'       => 'Cash',
                    'Banque'        => 'Bank',
                    'Mobile Money'  => 'Mobile',
                    'Autre'         => 'Other',
                ],
                'placeholder' => '— Sélectionner —',
                'label'       => 'Source des fonds',
                'required'    => true,
                'constraints' => [
                    new Assert\NotBlank(message: 'La source des fonds est obligatoire.'),
                ],
            ])

            // NOTES — obligatoire
            ->add('notes', TextareaType::class, [
                'required'    => true,
                'label'       => 'Notes',
                'attr'        => ['rows' => 3],
                'constraints' => [
                    new Assert\NotBlank(message: 'Les notes sont obligatoires.'),
                    new Assert\Length(max: 2000, maxMessage: '2000 caractères maximum.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        // formulaire non mappé directement à l’entité : on récupère un array
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
