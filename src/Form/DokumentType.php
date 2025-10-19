<?php

namespace App\Form;

use App\Entity\Dienstleister;
use App\Entity\Dokument;
use App\Entity\Rechnung;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class DokumentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('kategorie', ChoiceType::class, [
                'label' => 'Kategorie',
                'choices' => [
                    'Rechnungen' => 'rechnungen',
                    'Kontoauszüge' => 'bank-statements',
                    'Verträge' => 'vertraege',
                    'Protokolle' => 'protokolle',
                    'Sonstiges' => 'uploads',
                ],
                'placeholder' => 'Kategorie wählen',
                'required' => false,
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500',
                ],
            ])
            ->add('beschreibung', TextareaType::class, [
                'label' => 'Beschreibung',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500',
                ],
            ])
            ->add('rechnung', EntityType::class, [
                'class' => Rechnung::class,
                'choice_label' => function (Rechnung $rechnung): string {
                    return \sprintf('#%d - %s (%s)',
                        $rechnung->getId(),
                        $rechnung->getInformation(),
                        $rechnung->getDienstleister() ? $rechnung->getDienstleister()->getBezeichnung() : 'Kein Dienstleister'
                    );
                },
                'label' => 'Rechnung (optional)',
                'placeholder' => 'Rechnung auswählen',
                'required' => false,
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500',
                ],
            ])
            ->add('dienstleister', EntityType::class, [
                'class' => Dienstleister::class,
                'choice_label' => 'bezeichnung',
                'label' => 'Dienstleister (optional)',
                'placeholder' => 'Dienstleister auswählen',
                'required' => false,
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500',
                ],
            ]);

        if (!$options['edit_mode']) {
            $builder->add('datei', FileType::class, [
                'label' => 'Datei hochladen',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new File([
                        'maxSize' => '10M',
                        'mimeTypes' => [
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'text/csv',
                            'text/plain',
                            'image/jpeg',
                            'image/png',
                        ],
                        'mimeTypesMessage' => 'Bitte laden Sie eine gültige Datei hoch (PDF, Word, Excel, CSV, Bild oder Text)',
                        'maxSizeMessage' => 'Die Datei ist zu groß ({{ size }} {{ suffix }}). Maximal erlaubt sind {{ limit }} {{ suffix }}.',
                    ]),
                ],
                'attr' => [
                    'class' => 'block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400',
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Dokument::class,
            'edit_mode' => false,
        ]);
    }
}
