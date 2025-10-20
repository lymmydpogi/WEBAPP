<?php

namespace App\Form;

use App\Entity\Client;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use libphonenumber\PhoneNumberUtil;

class ClientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Name cannot be empty.',
                    ]),
                ],
            ])
            ->add('email', TextType::class, [
                'required' => true,
                'attr' => ['placeholder' => 'example@email.com'],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Email cannot be empty.',
                    ]),
                    new Assert\Email([
                        'message' => 'The email "{{ value }}" is not valid.',
                    ]),
                ],
            ])
            ->add('phone', TextType::class, [
                'required' => false,
                'data' => '+63', // Default value
                'attr' => ['placeholder' => '+63XXXXXXXXXX'],
                'constraints' => [
                    new Callback(function ($value, ExecutionContextInterface $context) {
                        if (!$value) {
                            return; // skip empty optional field
                        }

                        $phoneUtil = PhoneNumberUtil::getInstance();
                        try {
                            $number = $phoneUtil->parse($value, 'PH');
                            if (!$phoneUtil->isValidNumber($number)) {
                                $context->buildViolation('Invalid phone number.')
                                    ->addViolation();
                            }
                        } catch (\libphonenumber\NumberParseException $e) {
                            $context->buildViolation('Invalid phone number format.')
                                ->addViolation();
                        }
                    }),
                ],
            ])
            ->add('address', TextType::class, ['required' => false])
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'Active' => 'active',
                    'Suspended' => 'suspended',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Client::class,
        ]);
    }
}
