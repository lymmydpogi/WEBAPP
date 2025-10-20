<?php

namespace App\Form;

use App\Entity\Order;
use App\Entity\Services;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class OrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('clientName')
            ->add('clientEmail')
            ->add('orderDate', DateType::class, ['widget' => 'single_text'])
            ->add('status')
            ->add('totalPrice')
            ->add('notes')
            ->add('deliveryDate', DateType::class, ['widget' => 'single_text', 'required' => false])
            ->add('Service', EntityType::class, [
                'class' => Services::class,
                'choice_label' => 'name',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Order::class]);
    }
}
