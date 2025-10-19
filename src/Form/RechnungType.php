<?php

namespace App\Form;

use App\Entity\Dienstleister;
use App\Entity\Rechnung;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RechnungType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dienstleister', EntityType::class, [
                'class' => Dienstleister::class,
                'choice_label' => 'bezeichnung',
                'label' => 'Dienstleister',
                'required' => false,
                'placeholder' => '-- Dienstleister auswählen --',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('d')
                        ->orderBy('d.bezeichnung', 'ASC');
                },
            ])
            ->add('rechnungsnummer', TextType::class, [
                'label' => 'Rechnungsnummer',
                'required' => false,
                'attr' => [
                    'placeholder' => 'z.B. RG-2024-001',
                ],
            ])
            ->add('information', TextType::class, [
                'label' => 'Information',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Beschreibung der Rechnung',
                ],
            ])
            ->add('datumLeistung', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Datum Leistung',
                'required' => false,
                'attr' => [
                    'class' => 'datepicker',
                ],
            ])
            ->add('faelligkeitsdatum', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Fälligkeitsdatum',
                'required' => false,
                'attr' => [
                    'class' => 'datepicker',
                ],
            ])
            ->add('betragMitSteuern', NumberType::class, [
                'label' => 'Betrag mit Steuern (€)',
                'required' => true,
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'step' => '0.01',
                    'placeholder' => '0.00',
                ],
            ])
            ->add('gesamtMwSt', NumberType::class, [
                'label' => 'Gesamt MwSt. (€)',
                'required' => false,
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'step' => '0.01',
                    'placeholder' => '0.00',
                ],
            ])
            ->add('arbeitsFahrtkosten', NumberType::class, [
                'label' => 'Arbeits-/Fahrtkosten (€)',
                'required' => false,
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'step' => '0.01',
                    'placeholder' => '0.00',
                ],
            ])
            ->add('ausstehend', CheckboxType::class, [
                'label' => 'Rechnung ausstehend',
                'required' => false,
                'data' => true, // Default to true for new invoices
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Rechnung::class,
        ]);
    }
}
