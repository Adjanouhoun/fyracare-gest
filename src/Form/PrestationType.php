<?php

namespace App\Form;

use App\Entity\Prestation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PrestationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle', TextType::class, [
                'label' => 'Libellé',
                'attr'  => ['placeholder' => 'Ex: Coupe & soin']
            ])
            ->add('dureeMin', IntegerType::class, [
                'label' => 'Durée (minutes)',
                'attr'  => ['min' => 0]
            ])
            ->add('prix', IntegerType::class, [
                'label' => 'Prix (FCFA)',
                'attr'  => ['min' => 0]
            ])
            ->add('isActive', CheckboxType::class, [
                'label'    => 'Active',
                'required' => false
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description',
                'required' => false,
                'attr'     => ['rows' => 4]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Prestation::class,
        ]);
    }
}
