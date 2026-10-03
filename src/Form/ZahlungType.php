<?php

namespace App\Form;

use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class ZahlungType extends BaseZahlungType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('datum', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Datum',
                'required' => true,
                'attr' => [
                    'class' => 'datepicker',
                ],
            ])
            ->add('bezeichnung', TextType::class, [
                'label' => 'Bezeichnung',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Beschreibung der Zahlung eingeben',
                ],
            ])
            ->add('betrag', NumberType::class, [
                'label' => 'Betrag (€)',
                'required' => true,
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'step' => '0.01',
                    'placeholder' => '0.00',
                    'data-zahlung-form-target' => 'betrag',
                ],
            ]);

        // Add common fields
        $this->addCommonFields($builder);

        // Add dynamic validation
        $this->addDynamicValidation($builder);
    }
}
