<?php

namespace App\Form;

use App\Entity\Payment;
use App\Entity\Rdv;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use FOS\CKEditorBundle\Form\Type\CKEditorType;

class PaymentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Rdv|null $rdv */
        $rdv = $options['rdv'] ?? null;
        $defaultAmount = $rdv?->getPrestation()?->getPrix() ?? 0;

        $builder
            // Montant (on met la valeur ici; on le “verrouille” dans Twig via input readonly + champ caché)
            ->add('amount', IntegerType::class, [
                'data' => $defaultAmount,
            ])

            // Méthode (radios => expanded: true)
            ->add('methode', ChoiceType::class, [
                'choices'  => [
                    'Espèces' => Payment::M_ESPECES,
                    'Mobile'  => Payment::M_MOBILE,
                ],
                'expanded' => true,
                'multiple' => false,
                'data'     => Payment::M_ESPECES,
            ])

            // Notes (facultatif)
            ->add('notes', CKEditorType::class, [
                'label' => 'Notes',
                'required' => false,
                // 'config_name' => 'basic',   // (facultatif, utilise default_config sinon)
            ])
            // Date paiement (on met la valeur maintenant; champ masqué en Twig)
            ->add('paidAt', DateTimeType::class, [
                'widget' => 'single_text',
                'data'   => new \DateTimeImmutable(),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Payment::class,
            'rdv'        => null, // on passera l’option depuis le contrôleur
        ]);
    }
}
