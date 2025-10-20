<?php

namespace App\Entity;

use App\Repository\ServicesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\Order;

#[ORM\Entity(repositoryClass: ServicesRepository::class)]
class Services
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Service name cannot be empty.")]
    #[Assert\Length(max: 255, maxMessage: "Service name cannot exceed {{ limit }} characters.")]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "Description is required.")]
    private ?string $description = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: "Price is required.")]
    #[Assert\Positive(message: "Price must be a positive number.")]
    private ?float $price = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Status is required.")]
    private ?string $status = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: "Pricing model is required.")]
    #[Assert\Positive(message: "Pricing model must be a positive number.")]
    private ?float $pricingModel = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Choice(
        choices: ['per minute', 'per hour', 'per project', 'per video', 'per design'],
        message: "Invalid pricing unit. Choose a valid option."
    )]
    private ?string $pricingUnit = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: "Delivery time is required.")]
    #[Assert\Positive(message: "Delivery time must be a positive number.")]
    private ?float $deliveryTime = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Category is required.")]
    private ?string $category = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Tools used field cannot be empty.")]
    private ?string $toolsUsed = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "Revision limit is required.")]
    private ?string $revisionLimit = null;

    #[ORM\Column(type: 'boolean')]
    private ?bool $isActive = true;

    #[ORM\OneToMany(mappedBy: 'service', targetEntity: Order::class)]
    private Collection $orders;

    public function __construct()
    {
        $this->orders = new ArrayCollection();
    }

    // ──────────────── Active Status ────────────────
    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getPricingModel(): ?float
    {
        return $this->pricingModel;
    }

    public function setPricingModel(float $pricingModel): static
    {
        $this->pricingModel = $pricingModel;
        return $this;
    }

    public function getPricingUnit(): ?string
    {
        return $this->pricingUnit;
    }

    public function setPricingUnit(?string $pricingUnit): static
    {
        $this->pricingUnit = $pricingUnit;
        return $this;
    }

    public function getDeliveryTime(): ?float
    {
        return $this->deliveryTime;
    }

    public function setDeliveryTime(float $deliveryTime): static
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

    public function setToolsUsed(string $toolsUsed): static
    {
        $this->toolsUsed = $toolsUsed;
        return $this;
    }

    public function getRevisionLimit(): ?string
    {
        return $this->revisionLimit;
    }

    public function setRevisionLimit(string $revisionLimit): static
    {
        $this->revisionLimit = $revisionLimit;
        return $this;
    }

    // ──────────────── Orders Relationship ────────────────
    /**
     * @return Collection<int, Order>
     */
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
}
