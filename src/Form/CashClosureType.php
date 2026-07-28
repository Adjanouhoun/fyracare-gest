<?php
namespace App\Form;

use App\Entity\CashClosure;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CashClosureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $options): void
    {
        $b->add('countedBalance', IntegerType::class, [
            'label' => 'Solde réellement compté (MRU)',
            'required' => true,
            'attr' => ['min' => 0],
        ])->add('notes', TextareaType::class, [
            'label' => 'Justification de l’écart',
            'required' => false,
            'attr' => ['rows' => 3, 'minlength' => 5, 'maxlength' => 2000],
        ])->add('lateReason', TextareaType::class, [
            'label' => 'Motif de la clôture tardive',
            'required' => false,
            'attr' => ['rows' => 2, 'minlength' => 5, 'maxlength' => 1000],
        ]);
    }

    public function configureOptions(OptionsResolver $r): void
    {
        $r->setDefaults([
            'data_class' => CashClosure::class,
        ]);
    }
}
