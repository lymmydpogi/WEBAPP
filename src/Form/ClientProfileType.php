<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ClientProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'First name',
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'First name is required.']),
                    new Length(['max' => 100]),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last name',
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Last name is required.']),
                    new Length(['max' => 100]),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'disabled' => true,
                'required' => false,
            ])
            ->add('avatarFile', FileType::class, [
                'label' => 'Profile picture',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Image([
                        'maxSize' => '3M',
                        'mimeTypesMessage' => 'Please upload a valid image file (JPG, PNG, WEBP, GIF).',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
