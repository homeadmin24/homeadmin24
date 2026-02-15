<?php

namespace App\Form;

use App\Entity\Weg;
use App\Entity\WegKontostand;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WegKontostandType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('weg', EntityType::class, [
                'class' => Weg::class,
                'choice_label' => 'bezeichnung',
                'label' => 'WEG',
                'required' => true,
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5',
                ],
            ])
            ->add('year', IntegerType::class, [
                'label' => 'Abrechnungsjahr',
                'required' => true,
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5',
                    'min' => 2020,
                    'max' => 2030,
                ],
            ])
            ->add('bankkontoTyp', ChoiceType::class, [
                'label' => 'Bankkonto-Typ',
                'choices' => [
                    'Hausgeld' => 'hausgeld',
                    'Rücklage' => 'ruecklage',
                ],
                'required' => true,
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5',
                ],
            ])
            ->add('stichtagStart', DateType::class, [
                'label' => 'Stichtag Start',
                'widget' => 'single_text',
                'required' => true,
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5',
                ],
                'help' => 'Anfang des Abrechnungsjahres (z.B. 01.01.)',
            ])
            ->add('saldoStart', MoneyType::class, [
                'label' => 'Saldo am Start',
                'currency' => 'EUR',
                'required' => true,
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5',
                ],
                'help' => 'Kontostand zu Beginn des Abrechnungsjahres',
            ])
            ->add('stichtagEndPeriode', DateType::class, [
                'label' => 'Periodenende',
                'widget' => 'single_text',
                'required' => false,
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5',
                ],
                'help' => 'Ende des Abrechnungszeitraums (z.B. 30.12.)',
            ])
            ->add('saldoEndPeriode', MoneyType::class, [
                'label' => 'Saldo am Periodenende',
                'currency' => 'EUR',
                'required' => false,
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5',
                ],
                'help' => 'Kontostand am Periodenende (BGH-Stichtag)',
            ])
            ->add('stichtagEnd', DateType::class, [
                'label' => 'Stichtag Bankauszug',
                'widget' => 'single_text',
                'required' => true,
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5',
                ],
                'help' => 'Datum des Bankauszugs (kann über Jahresgrenze gehen)',
            ])
            ->add('saldoEnd', MoneyType::class, [
                'label' => 'Saldo am Stichtag Bankauszug',
                'currency' => 'EUR',
                'required' => true,
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5',
                ],
                'help' => 'Tatsächlicher Kontostand laut Bankauszug',
            ])
            ->add('bemerkung', TextareaType::class, [
                'label' => 'Bemerkung (optional)',
                'required' => false,
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5',
                    'rows' => 3,
                ],
                'help' => 'z.B. Erklärung für Abweichungen, Besonderheiten',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WegKontostand::class,
        ]);
    }
}
