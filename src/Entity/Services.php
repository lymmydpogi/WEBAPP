<?php

namespace App\Entity;

use App\Repository\ServicesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\Order;
use App\Entity\User;

#[ORM\Entity(repositoryClass: ServicesRepository::class)]
class Services
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Service name is required.")]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: "Service name must be at least {{ limit }} characters long",
        maxMessage: "Service name cannot exceed {{ limit }} characters"
    )]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "Description is required.")]
    #[Assert\Length(
        min: 10,
        max: 2000,
        minMessage: "Description must be at least {{ limit }} characters long",
        
    )]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: "Price is required.")]
    #[Assert\Positive(message: "Price must be greater than 0.")]
    #[Assert\Range(
        min: 0.01,
        max: 999999.99,
        notInRangeMessage: "Price must be between {{ min }} and {{ max }}"
    )]
    private ?float $price = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: "Status is required.")]
    #[Assert\Choice(
        choices: ['active', 'inactive'],
        message: "Status must be either 'active' or 'inactive'."
    )]
    private string $status = 'active';

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: "Pricing model is required.")]
    #[Assert\Choice(
        choices: ['fixed', 'hourly', 'milestone'],
        message: "Choose a valid pricing model."
    )]
    private ?string $pricingModel = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: "Pricing unit is required.")]
    #[Assert\Choice(
        choices: [
            'project', 'package', 'deliverable', 'hour', 'day', 'week',
            'milestone', 'phase', 'stage'
        ],
        message: "Invalid pricing unit."
    )]
    private ?string $pricingUnit = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotBlank(message: "Delivery time is required.")]
    #[Assert\Positive(message: "Delivery time must be greater than 0.")]
    #[Assert\Range(
        min: 1,
        max: 365,
        notInRangeMessage: "Delivery time must be between {{ min }} and {{ max }} days"
    )]
    private ?int $deliveryTime = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "Category is required.")]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: "Category must be at least {{ limit }} characters long",
        maxMessage: "Category cannot exceed {{ limit }} characters"
    )]
    private ?string $category = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: "Tools used cannot exceed {{ limit }} characters"
    )]
    private ?string $toolsUsed = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(
        max: 50,
        maxMessage: "Revision limit cannot exceed {{ limit }} characters"
    )]
    private ?string $revisionLimit = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $is_active = true;

    #[ORM\OneToMany(mappedBy: 'service', targetEntity: Order::class)]
    private Collection $orders;

    # NEW FIELD — WHO CREATED THIS SERVICE
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    public function __construct()
    {
        $this->orders = new ArrayCollection();
        $this->status = 'active';
        $this->is_active = true;
    }

    // ──────────────── Getters & Setters ────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getPricingModel(): ?string
    {
        return $this->pricingModel;
    }

    public function setPricingModel(string $pricingModel): static
    {
        $this->pricingModel = $pricingModel;
        return $this;
    }

    public function getPricingUnit(): ?string
    {
        return $this->pricingUnit;
    }

    public function setPricingUnit(string $pricingUnit): static
    {
        $this->pricingUnit = $pricingUnit;
        return $this;
    }

    public function getDeliveryTime(): ?int
    {
        return $this->deliveryTime;
    }

    public function setDeliveryTime(int $deliveryTime): static
    {
        $this->deliveryTime = $deliveryTime;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getToolsUsed(): ?string
    {
        return $this->toolsUsed;
    }

    public function setToolsUsed(?string $toolsUsed): static
    {
        $this->toolsUsed = $toolsUsed;
        return $this;
    }

    public function getRevisionLimit(): ?string
    {
        return $this->revisionLimit;
    }

    public function setRevisionLimit(?string $revisionLimit): static
    {
        $this->revisionLimit = $revisionLimit;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->is_active = $isActive;
        return $this;
    }

    // ──────────────── Orders Relationship ────────────────

    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function addOrder(Order $order): static
    {
        if (!$this->orders->contains($order)) {
            $this->orders->add($order);
            $order->setService($this);
        }
        return $this;
    }

    public function removeOrder(Order $order): static
    {
        if ($this->orders->removeElement($order)) {
            if ($order->getService() === $this) {
                $order->setService(null);
            }
        }
        return $this;
    }

    // ──────────────── NEW: Created By ────────────────

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $user): static
    {
        $this->createdBy = $user;
        return $this;
    }
}
