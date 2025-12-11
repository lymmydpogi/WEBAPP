<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Validator\Constraints as Assert;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];
        $isProfile = $options['is_profile'];

        // ===============================
        // Basic fields
        // ===============================
        $builder
            ->add('name', TextType::class, [
                'label' => 'Full Name',
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Full name is required']),
                    new Assert\Length([
                        'max' => 255,
                        'maxMessage' => 'Full name cannot exceed {{ limit }} characters',
                    ]),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Email is required']),
                    new Assert\Email(['message' => 'Invalid email address']),
                    new Assert\Length([
                        'max' => 180,
                        'maxMessage' => 'Email cannot exceed {{ limit }} characters',
                    ]),
                ],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Phone Number',
                'required' => false,
                'constraints' => [
                    new Assert\Length([
                        'max' => 25,
                        'maxMessage' => 'Phone number cannot exceed {{ limit }} characters',
                    ]),
                    new Assert\Regex([
                        'pattern' => '/^[0-9+\s-]*$/',
                        'message' => 'Phone can contain only numbers, "+", spaces, and "-"',
                    ]),
                ],
            ])
            ->add('address', TextType::class, [
                'label' => 'Address',
                'required' => false,
                'constraints' => [
                    new Assert\Length([
                        'max' => 255,
                        'maxMessage' => 'Address cannot exceed {{ limit }} characters',
                    ]),
                ],
            ]);

        // ===============================
        // Password Logic
        // ===============================

        // NEW user → require password
        if (!$isEdit && !$isProfile) {
            $builder->add('password', PasswordType::class, [
                'label' => 'Password',
                'required' => true,
                'mapped' => false,
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Password is required']),
                    new Assert\Length([
                        'min' => 6,
                        'minMessage' => 'Password must be at least {{ limit }} characters',
                        'max' => 255,
                    ]),
                ],
            ]);
        }

        // EDIT user → OPTIONAL newPassword
        if ($isEdit || $isProfile) {
    $builder->add('new_password', PasswordType::class, [
        'label' => 'New Password',
        'mapped' => false,
        'required' => false,
        'attr' => [
            'placeholder' => 'Leave blank to keep current password'
        ],
        'constraints' => [
            new Assert\Length([
                'min' => 6,
                'minMessage' => 'Password must be at least {{ limit }} characters',
                'max' => 255,
            ]),
        ],
    ]);
}

        // ===============================
        // Status (edit only)
        // ===============================
        if ($isEdit && !$isProfile) {
            $builder->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Active' => 'active',
                    'Suspended' => 'suspended',
                ],
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Status is required']),
                ],
            ]);
        }

        // ===============================
        // Roles (admin only)
        // ===============================
        if (!$isProfile) {
            $builder->add('roles', ChoiceType::class, [
                'label' => 'Role',
                'choices' => [
                    'Client' => 'ROLE_CLIENT',
                    'Staff' => 'ROLE_STAFF',
                    'Manager' => 'ROLE_MANAGER',
                ],
                'multiple' => false,
                'expanded' => false,
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Role is required']),
                ],
            ]);

            // Transform roles array <-> string
            $builder->get('roles')->addModelTransformer(
                new CallbackTransformer(
                    function ($rolesArray) {
                        return $rolesArray[0] ?? null;
                    },
                    function ($rolesString) {
                        return $rolesString ? [$rolesString] : [];  
                    }
                )
            );
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
            'is_profile' => false,
        ]);

        $resolver->setAllowedTypes('is_edit', 'bool');
        $resolver->setAllowedTypes('is_profile', 'bool');
    }
}
