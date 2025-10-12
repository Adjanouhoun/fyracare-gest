<?php

namespace App\Form;

use App\Entity\Rdv;
use App\Entity\Prestation;
use App\Repository\PrestationRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

class RdvType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // ⚠️ PAS de champ "client" ici : client est fixé ailleurs
            ->add('client_id', HiddenType::class, [
                'mapped'   => false,
                'required' => true,   // on veut obliger ce champ
            ])
            ->add('prestation', EntityType::class, [
                'class' => Prestation::class,
                'choice_label' => fn (?Prestation $p) =>
                    $p ? $p->getLibelle() . ($p->getPrix() ? ' — ' . $p->getPrix() . ' MRU' : '') : '',
                'query_builder' => fn (PrestationRepository $r) =>
                    $r->createQueryBuilder('p')->orderBy('p.libelle', 'ASC'),
                'placeholder' => 'Sélectionner une prestation',
                'required' => true,
            ])
            ->add('startAt', DateTimeType::class, [
                'widget'   => 'single_text',
                'label'    => 'Début',
                'required' => true,
            ])
            ->add('notes', TextareaType::class, [
                'required' => false,
                'label'    => 'Notes',
                'attr'     => ['rows' => 3],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Rdv::class]);
    }
}
