<?php

namespace App\Form;

use App\Entity\Weg;
use App\Repository\WegRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AbrechnungGenerateType extends AbstractType
{
    public function __construct(
        private WegRepository $wegRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('weg', EntityType::class, [
                'class' => Weg::class,
                'choice_label' => 'bezeichnung',
                'placeholder' => 'WEG auswählen...',
                'attr' => [
                    'class' => 'block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500',
                ],
                'label' => 'WEG',
                'required' => true,
                'data' => $this->wegRepository->findOneBy([]), // Preselect first WEG
            ])
            ->add('jahr', ChoiceType::class, [
                'choices' => [
                    '2025' => 2025,
                    '2024' => 2024,
                    '2023' => 2023,
                    '2022' => 2022,
                ],
                'attr' => [
                    'class' => 'block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500',
                ],
                'label' => 'Abrechnungsjahr',
                'data' => date('Y'), // Default to current year
            ])
            ->add('format', ChoiceType::class, [
                'choices' => [
                    'PDF' => 'pdf',
                    'TXT' => 'txt',
                    'Beide' => 'both',
                ],
                'expanded' => true,
                'multiple' => false,
                'attr' => [
                    'class' => 'flex flex-wrap gap-4',
                ],
                'label' => 'Format',
                'data' => 'both', // Default to both formats
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false, // Temporarily disable until CSRF system is fixed
            'allow_extra_fields' => true, // Allow dynamic einheiten fields from JavaScript
            'extra_fields_message' => 'This form should not contain extra fields: "{{ extra_fields }}".',
        ]);
    }
}
