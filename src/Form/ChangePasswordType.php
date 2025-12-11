<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('new_password', PasswordType::class, [
            'label' => 'New Password',
            'mapped' => false,
            'required' => true,
            'constraints' => [
                new Assert\NotBlank(['message' => 'Password cannot be empty']),
                new Assert\Length([
                    'min' => 6,
                    'minMessage' => 'Password must be at least {{ limit }} characters',
                ]),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([]);
    }
}
