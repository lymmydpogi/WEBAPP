<?php

namespace App\Form;

use App\Entity\Services;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ServicesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;

        $builder
            ->add('name', TextType::class, [
                'label' => 'Service Name',
                'attr' => ['maxlength' => 255],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Service name is required']),
                    new Assert\Length([
                        'min' => 3,
                        'max' => 255,
                        'minMessage' => 'Service name must be at least {{ limit }} characters long',
                        'maxMessage' => 'Service name cannot be longer than {{ limit }} characters',
                    ]),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['rows' => 6, 'maxlength' => 2000],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Description is required']),
                    new Assert\Length([
                        'min' => 10,
                        'max' => 2000,
                        'minMessage' => 'Description must be at least {{ limit }} characters long',
                        'maxMessage' => 'Description cannot be longer than {{ limit }} characters',
                    ]),
                ],
            ])
            ->add('price', NumberType::class, [
                'label' => 'Price',
                'attr' => ['step' => '0.01', 'min' => '0'],
                'scale' => 2,
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Price is required']),
                    new Assert\Positive(['message' => 'Price must be greater than 0']),
                    new Assert\Range([
                        'min' => 0.01,
                        'max' => 999999.99,
                        'notInRangeMessage' => 'Price must be between {{ min }} and {{ max }}',
                    ]),
                ],
            ]);

        // Status only editable in edit mode
        if ($isEdit) {
            $builder->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Active' => 'active',
                    'Inactive' => 'inactive',
                ],
                'placeholder' => 'Choose Status',
                'expanded' => false,
                'multiple' => false,
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Status is required']),
                    new Assert\Choice([
                        'choices' => ['active', 'inactive'],
                        'message' => 'Please select a valid status',
                    ]),
                ],
            ]);
        }

        $builder
            ->add('pricingModel', ChoiceType::class, [
                'label' => 'Pricing Model',
                'attr' => ['id' => 'services_pricingModel'],
                'choices' => [
                    'Fixed' => 'fixed',
                    'Hourly' => 'hourly',
                    'Milestone-Based' => 'milestone',
                ],
                'placeholder' => 'Select a pricing model',
                'expanded' => false,
                'multiple' => false,
                'attr' => ['id' => 'pricingModel'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Pricing model is required']),
                    new Assert\Choice([
                        'choices' => ['fixed', 'hourly', 'milestone'],
                        'message' => 'Please select a valid pricing model',
                    ]),
                ],
            ])
                ->add('pricingUnit', ChoiceType::class, [
                        'label' => 'Pricing Unit',
                        'placeholder' => 'Select a unit',
                        'attr' => ['id' => 'services_pricingUnit'], 
                        'choices' => [
                            'Per Project' => 'project',
                            'Per Package' => 'package',
                            'Per Deliverable' => 'deliverable',
                            'Per Hour' => 'hour',
                            'Per Day' => 'day',
                            'Per Week' => 'week',
                            'Per Milestone' => 'milestone',
                            'Per Phase' => 'phase',
                            'Per Stage' => 'stage',
                        ],
                        'choice_attr' => function($choice, $key, $value) {
                            return match ($value) {
                                'project', 'package', 'deliverable' => ['data-group' => 'fixed'],
                                'hour', 'day', 'week' => ['data-group' => 'hourly'],
                                'milestone', 'phase', 'stage' => ['data-group' => 'milestone'],
                                default => [],
                            };
                        },
                        'attr' => ['id' => 'pricingUnit'],
                    ])
                ->add('deliveryTime', IntegerType::class, [
                'label' => 'Delivery Time (Days)',
                'attr' => ['min' => '1', 'max' => '365'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Delivery time is required']),
                    new Assert\Positive(['message' => 'Delivery time must be greater than 0']),
                    new Assert\Range([
                        'min' => 1,
                        'max' => 365,
                        'notInRangeMessage' => 'Delivery time must be between {{ min }} and {{ max }} days',
                    ]),
                ],
            ])
            ->add('category', TextType::class, [
                'label' => 'Category',
                'attr' => ['maxlength' => 100],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Category is required']),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'Category must be at least {{ limit }} characters long',
                        'maxMessage' => 'Category cannot be longer than {{ limit }} characters',
                    ]),
                ],
            ])
            ->add('toolsUsed', TextType::class, [
                'label' => 'Tools Used',
                'required' => false,
                'attr' => ['maxlength' => 255],
                'constraints' => [
                    new Assert\Length([
                        'max' => 255,
                        'maxMessage' => 'Tools used cannot be longer than {{ limit }} characters',
                    ]),
                ],
            ])
            ->add('revisionLimit', TextType::class, [
                'label' => 'Revision Limit',
                'required' => false,
                'attr' => ['maxlength' => 50],
                'constraints' => [
                    new Assert\Length([
                        'max' => 50,
                        'maxMessage' => 'Revision limit cannot be longer than {{ limit }} characters',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Services::class,
            'is_edit' => false,
        ]);

        $resolver->setAllowedTypes('is_edit', 'bool');
    }
}
