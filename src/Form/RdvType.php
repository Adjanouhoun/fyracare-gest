<?php

namespace App\Form;

use App\Entity\Client;
use App\Entity\Prestation;
use App\Entity\Rdv;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RdvType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('client', EntityType::class, [
                'class' => Client::class,
                'choice_label' => 'nometprenom',
                'placeholder' => 'Rechercher un client…',
                'required' => true,
            ])
            ->add('prestation', EntityType::class, [
                'class' => Prestation::class,
                'choice_label' => 'libelle',
                'placeholder' => 'Rechercher une prestation…',
                'required' => true,
            ])
            ->add('startAt', DateTimeType::class, [
                'widget' => 'single_text',
                'input'  => 'datetime_immutable',
                'label'  => 'Début',
                'required' => true,
            ])
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'Planifié' => Rdv::S_PLANIFIE,
                    'Confirmé' => Rdv::S_CONFIRME,
                    'Honoré'   => Rdv::S_HONORE,
                    'Annulé'   => Rdv::S_ANNULE,
                    'Absent'   => Rdv::S_ABSENT,
                ],
                'label' => 'Statut',
            ])
            ->add('notes', TextareaType::class, [
                'required' => false,
                'label' => 'Notes',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Rdv::class,
        ]);
    }
}
