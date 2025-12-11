<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // NAME
            // ->add('name', TextType::class, [
            //     'required' => false,
            //     'constraints' => [
            //         new Length([
            //             'max' => 255,
            //             'maxMessage' => 'Name cannot exceed {{ limit }} characters',
            //         ]),
            //     ],
            // ])

            // // PHONE
            // ->add('phone', TextType::class, [
            //     'required' => false,
            //     'constraints' => [
            //         new Length([
            //             'max' => 25,
            //             'maxMessage' => 'Phone number cannot exceed {{ limit }} characters',
            //         ]),
            //         new Regex([
            //             'pattern' => '/^\+?[0-9\s\-]*$/',
            //             'message' => 'Phone number can contain only numbers, spaces, + and -',
            //         ]),
            //     ],
            // ])

            // // ADDRESS
            // ->add('address', TextType::class, [
            //     'required' => false,
            //     'constraints' => [
            //         new Length([
            //             'max' => 255,
            //             'maxMessage' => 'Address cannot exceed {{ limit }} characters',
            //         ]),
            //     ],
            // ])

            // EMAIL FIELD
            ->add('email', EmailType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'Email is required']),
                    new Email(['message' => 'Please enter a valid email address']),
                ],
            ])

            // TERMS CHECKBOX
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'constraints' => [
                    new IsTrue([
                        'message' => 'You must agree to the terms to continue.',
                    ]),
                ],
            ])

            // PASSWORD FIELD
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Password cannot be empty',
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Password must be at least {{ limit }} characters long',
                        'max' => 4096,
                    ]),
                    new Regex([
                        'pattern' => '/^(?=.*[A-Za-z])(?=.*\d).+$/',
                        'message' => 'Password must contain at least one letter and one number',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'constraints' => [
                new UniqueEntity([
                    'entityClass' => User::class,
                    'fields' => ['email'],
                    'message' => 'This email is already registered.',
                ])
            ],
        ]);
    }
}
