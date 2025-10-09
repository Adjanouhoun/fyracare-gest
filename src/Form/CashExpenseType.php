<?php
namespace App\Form;

use App\Entity\CashMovement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CashExpenseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $options): void
    {
        $b
            ->add('amount', IntegerType::class, [
                'label' => 'Montant (MRU)',
                'attr'  => ['min' => 0],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Motif / Notes',
                'required' => true,
                'attr' => ['rows' => 3],
            ]);
    }

    public function configureOptions(OptionsResolver $r): void
    {
        $r->setDefaults([
            'data_class' => CashMovement::class,
        ]);
    }
}
