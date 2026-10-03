<?php

namespace App\Form;

use App\Entity\Dienstleister;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DienstleisterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('bezeichnung', TextType::class, [
                'label' => 'Bezeichnung',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Name des Dienstleisters',
                ],
            ])
            ->add('artDienstleister', ChoiceType::class, [
                'label' => 'Art des Dienstleisters',
                'required' => false,
                'placeholder' => '-- Art auswählen --',
                'choices' => [
                    'Hausmeister' => 'Hausmeister',
                    'Reinigungsdienst' => 'Reinigungsdienst',
                    'Gartenpflege' => 'Gartenpflege',
                    'Wartung & Technik' => 'Wartung & Technik',
                    'Versicherung' => 'Versicherung',
                    'Bank' => 'Bank',
                    'Energieversorger' => 'Energieversorger',
                    'Telekommunikation' => 'Telekommunikation',
                    'Abfallentsorgung' => 'Abfallentsorgung',
                    'Sicherheitsdienst' => 'Sicherheitsdienst',
                    'Verwaltung' => 'Verwaltung',
                    'Handwerker' => 'Handwerker',
                    'Sonstiges' => 'Sonstiges',
                ],
            ])
            ->add('vertrag', TextType::class, [
                'label' => 'Vertrag',
                'required' => false,
                'attr' => [
                    'placeholder' => 'z.B. Hausmeistervertrag, Wartungsvertrag',
                ],
            ])
            ->add('datumInkrafttreten', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Datum Inkrafttreten',
                'required' => false,
                'attr' => [
                    'class' => 'datepicker',
                ],
            ])
            ->add('vertragsende', IntegerType::class, [
                'label' => 'Vertragsende (Jahr)',
                'required' => false,
                'attr' => [
                    'min' => 2020,
                    'max' => 2050,
                    'placeholder' => 'z.B. 2025',
                ],
            ])
            ->add('preisProJahr', NumberType::class, [
                'label' => 'Preis pro Jahr (€)',
                'required' => false,
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'step' => '0.01',
                    'placeholder' => '0.00',
                ],
            ])
            ->add('datumUnterzeichnung', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Datum Unterzeichnung',
                'required' => false,
                'attr' => [
                    'class' => 'datepicker',
                ],
            ])
            ->add('kuendigungsfrist', IntegerType::class, [
                'label' => 'Kündigungsfrist (Monate)',
                'required' => false,
                'attr' => [
                    'min' => 0,
                    'max' => 36,
                    'placeholder' => 'z.B. 3',
                ],
            ])
            ->add('vertragsreferenz', TextType::class, [
                'label' => 'Vertragsreferenz',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Referenznummer oder Aktenzeichen',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Dienstleister::class,
        ]);
    }
}
