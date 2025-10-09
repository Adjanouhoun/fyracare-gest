<?php
namespace App\Form;

use App\Entity\CashClosure;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CashClosureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $options): void
    {
        $b->add('notes', TextareaType::class, [
            'label' => 'Notes (facultatif)',
            'required' => false,
            'attr' => ['rows' => 3],
        ]);
    }

    public function configureOptions(OptionsResolver $r): void
    {
        $r->setDefaults([
            'data_class' => CashClosure::class,
        ]);
    }
}
