<?php
namespace App\Form;

use App\Entity\CashMovement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Entity\ExpenseCategory;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Validator\Constraints as Assert;

class CashExpenseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $options): void
    {
        $b
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
            ->add('category', EntityType::class, [
                'class' => ExpenseCategory::class,
                'choice_label' => 'name',
                'required' => true,
                'label' => 'Catégorie'
            ])
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

    public function configureOptions(OptionsResolver $r): void
    {
        $r->setDefaults([
            'data_class' => CashMovement::class,
        ]);
    }
}
