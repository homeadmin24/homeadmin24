<?php

namespace App\Form;

use App\Entity\Dienstleister;
use App\Entity\Kostenkonto;
use App\Entity\Rechnung;
use App\Entity\WegEinheit;
use App\Entity\Zahlung;
use App\Entity\Zahlungskategorie;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class BaseZahlungType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Zahlung::class,
            'csrf_protection' => true,
            'attr' => [
                'novalidate' => 'novalidate', // Enable client-side validation
            ],
        ]);
    }

    /**
     * Add common fields shared between all zahlung forms.
     */
    protected function addCommonFields(FormBuilderInterface $builder): void
    {
        $builder
            ->add('hauptkategorie', EntityType::class, [
                'class' => Zahlungskategorie::class,
                'choice_label' => 'name',
                'label' => 'Hauptkategorie',
                'required' => true,
                'placeholder' => '-- Hauptkategorie auswählen --',
                'attr' => [
                    'data-zahlung-form-target' => 'hauptkategorie',
                ],
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('zk')
                        ->where('zk.isActive = true')
                        ->orderBy('zk.sortOrder', 'ASC')
                        ->addOrderBy('zk.name', 'ASC');
                },
                'choice_attr' => function (Zahlungskategorie $kategorie) {
                    return [
                        'data-is-positive' => $kategorie->isIstPositiverBetrag() ? '1' : '0',
                        'data-allows-zero-amount' => $kategorie->isAllowsZeroAmount() ? '1' : '0',
                        'data-field-config' => json_encode($kategorie->getFieldConfig() ?? []),
                        'data-validation-rules' => json_encode($kategorie->getValidationRules() ?? []),
                        'data-help-text' => $kategorie->getHelpText() ?? '',
                        'data-kostenkonto-filter' => json_encode($kategorie->getFieldConfig()['kostenkonto_filter'] ?? []),
                    ];
                },
            ])
            ->add('kostenkonto', EntityType::class, [
                'class' => Kostenkonto::class,
                'choice_label' => function (Kostenkonto $kostenkonto) {
                    return $kostenkonto->getNummer() . ' - ' . $kostenkonto->getBezeichnung();
                },
                'label' => 'Kostenkonto',
                'required' => false,
                'placeholder' => '-- Kostenkonto auswählen --',
                'attr' => [
                    'data-zahlung-form-target' => 'kostenkonto',
                ],
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('k')
                        ->where('k.isActive = true')
                        ->orderBy('k.nummer', 'ASC');
                },
            ])
            ->add('eigentuemer', EntityType::class, [
                'class' => WegEinheit::class,
                'choice_label' => function (WegEinheit $wegEinheit) {
                    return $wegEinheit->getBezeichnung() . ' - ' . $wegEinheit->getMiteigentuemer();
                },
                'label' => 'Eigentümer',
                'required' => false,
                'placeholder' => '-- Eigentümer auswählen --',
                'attr' => [
                    'data-zahlung-form-target' => 'eigentuemer',
                ],
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('we')
                        ->orderBy('we.bezeichnung', 'ASC');
                },
            ])
            ->add('dienstleister', EntityType::class, [
                'class' => Dienstleister::class,
                'choice_label' => 'bezeichnung',
                'label' => 'Dienstleister',
                'required' => false,
                'placeholder' => '-- Dienstleister auswählen --',
                'attr' => [
                    'data-zahlung-form-target' => 'dienstleister',
                ],
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('d')
                        ->orderBy('d.bezeichnung', 'ASC');
                },
            ])
            ->add('rechnung', EntityType::class, [
                'class' => Rechnung::class,
                'choice_label' => function (Rechnung $rechnung) {
                    return $rechnung->getInformation() .
                        ($rechnung->getRechnungsnummer() ? ' (' . $rechnung->getRechnungsnummer() . ')' : '') .
                        ' - ' . $rechnung->getBetragMitSteuern() . ' €';
                },
                'label' => 'Rechnung',
                'required' => false,
                'placeholder' => '-- Rechnung auswählen --',
                'attr' => [
                    'data-zahlung-form-target' => 'rechnung',
                ],
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('r')
                        ->orderBy('r.id', 'DESC');
                },
            ])
            ->add('gesamtMwSt', NumberType::class, [
                'label' => 'Gesamt MwSt. (€)',
                'required' => false,
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'step' => '0.01',
                    'placeholder' => '0.00',
                    'data-zahlung-form-target' => 'mehrwertsteuer',
                ],
            ])
            ->add('hndAnteil', NumberType::class, [
                'label' => 'HND-Anteil',
                'required' => false,
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'step' => '0.01',
                    'placeholder' => '0.00',
                    'data-zahlung-form-target' => 'hndAnteil',
                ],
            ])
            ->add('abrechnungsjahrZuordnung', IntegerType::class, [
                'label' => 'Abrechnungsjahr',
                'required' => false,
                'attr' => [
                    'min' => 2020,
                    'max' => 2030,
                    'placeholder' => 'z.B. 2024',
                ],
            ])
            ->add('isSimulation', CheckboxType::class, [
                'label' => 'Simulation (Test-Zahlung)',
                'required' => false,
                'help' => 'Markieren Sie diese Option, um diese Zahlung als Simulation zu kennzeichnen. Simulationen werden in separaten Berichten angezeigt.',
                'attr' => [
                    'class' => 'form-check-input',
                ],
            ]);
    }

    /**
     * Add dynamic validation based on hauptkategorie selection
     * Field visibility is now handled by JavaScript using database configuration.
     */
    protected function addDynamicValidation(FormBuilderInterface $builder): void
    {
        // Add form event listener to handle rechnung field based on dienstleister
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $zahlung = $event->getData();
            $form = $event->getForm();

            // If editing and has a dienstleister, filter rechnungen
            if ($zahlung && $zahlung->getDienstleister()) {
                $dienstleister = $zahlung->getDienstleister();

                $form->add('rechnung', EntityType::class, [
                    'class' => Rechnung::class,
                    'choice_label' => function (Rechnung $rechnung) {
                        return $rechnung->getInformation() .
                            ($rechnung->getRechnungsnummer() ? ' (' . $rechnung->getRechnungsnummer() . ')' : '') .
                            ' - ' . $rechnung->getBetragMitSteuern() . ' €';
                    },
                    'label' => 'Rechnung',
                    'required' => false,
                    'placeholder' => '-- Rechnung auswählen --',
                    'attr' => [
                        'data-zahlung-form-target' => 'rechnung',
                    ],
                    'query_builder' => function (EntityRepository $er) use ($dienstleister) {
                        return $er->createQueryBuilder('r')
                            ->where('r.dienstleister = :dienstleister')
                            ->setParameter('dienstleister', $dienstleister)
                            ->orderBy('r.id', 'DESC');
                    },
                ]);
            }
        });

        // Handle form submission
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();

            // If dienstleister is selected, update rechnung field with filtered choices
            if (!empty($data['dienstleister'])) {
                $form->add('rechnung', EntityType::class, [
                    'class' => Rechnung::class,
                    'choice_label' => function (Rechnung $rechnung) {
                        return $rechnung->getInformation() .
                            ($rechnung->getRechnungsnummer() ? ' (' . $rechnung->getRechnungsnummer() . ')' : '') .
                            ' - ' . $rechnung->getBetragMitSteuern() . ' €';
                    },
                    'label' => 'Rechnung',
                    'required' => false,
                    'placeholder' => '-- Rechnung auswählen --',
                    'attr' => [
                        'data-zahlung-form-target' => 'rechnung',
                    ],
                    'query_builder' => function (EntityRepository $er) use ($data) {
                        return $er->createQueryBuilder('r')
                            ->where('r.dienstleister = :dienstleister')
                            ->setParameter('dienstleister', $data['dienstleister'])
                            ->orderBy('r.id', 'DESC');
                    },
                ]);
            }
        });
    }
}
