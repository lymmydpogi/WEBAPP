<?php

namespace App\Form;

use App\Entity\Services;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ServicesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class)
            ->add('description', TextareaType::class)
            ->add('price', NumberType::class)
            ->add('status', TextType::class)
            ->add('pricingModel', NumberType::class, [
                'label' => 'Pricing Model (₱)',
            ])
            ->add('pricingUnit', ChoiceType::class, [
                'label' => 'Pricing Unit',
                'choices' => [
                    'Per Minute' => 'per minute',
                    'Per Hour' => 'per hour',
                    'Per Project' => 'per project',
                    'Per Video' => 'per video',
                    'Per Design' => 'per design',
                ],
                'placeholder' => 'Select a unit',
            ])
            ->add('deliveryTime', NumberType::class, [
                'label' => 'Delivery Time (Days)',
            ])
            ->add('category', TextType::class)
            ->add('toolsUsed', TextType::class)
            ->add('revisionLimit', TextType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Services::class,
        ]);
    }
}
