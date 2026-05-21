<?php

namespace App\Form;

use App\Entity\Order;
use App\Entity\Services;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ClientOrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('service', EntityType::class, [
                'class' => Services::class,
                'choice_label' => 'name',
                'placeholder' => 'Select service',
                'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('s')
                    ->andWhere('s.status = :status')
                    ->setParameter('status', Services::STATUS_ACTIVE)
                    ->orderBy('s.name', 'ASC'),
                'constraints' => [new Assert\NotNull(message: 'Please select a service.')],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Project brief',
                'attr' => ['rows' => 8],
                'constraints' => [
                    new Assert\NotBlank(message: 'Project brief is required.'),
                    new Assert\Length(min: 20, minMessage: 'Please provide at least 20 characters for your project brief.'),
                ],
            ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            /** @var Order|null $order */
            $order = $event->getData();
            if (!$order || !$order->getService()) {
                return;
            }

            $order->recalculateTotalFromService();
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Order::class,
        ]);
    }
}
