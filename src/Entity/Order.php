<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\User;
use App\Entity\Services;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
class Order
{
    // ───────────── Order Status ─────────────
    public const STATUS_PENDING = 'Pending';
    public const STATUS_COMPLETED = 'Completed';
    public const STATUS_CANCELED = 'Canceled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELED,
    ];

    // ───────────── Payment Method ─────────────
    public const PAYMENT_CASH = 'Cash';
    public const PAYMENT_CREDIT_CARD = 'Credit Card';
    public const PAYMENT_GCASH = 'GCash';
    public const PAYMENT_OTHER = 'Other';

    public const PAYMENT_METHODS = [
        self::PAYMENT_CASH,
        self::PAYMENT_CREDIT_CARD,
        self::PAYMENT_GCASH,
        self::PAYMENT_OTHER,
    ];

    // ───────────── Payment Status ─────────────
    public const PAYMENT_STATUS_PENDING = 'Pending';
    public const PAYMENT_STATUS_COMPLETED = 'Completed';
    public const PAYMENT_STATUS_FAILED = 'Failed';

    public const PAYMENT_STATUSES = [
        self::PAYMENT_STATUS_PENDING,
        self::PAYMENT_STATUS_COMPLETED,
        self::PAYMENT_STATUS_FAILED,
    ];

    // ───────────── Fields ─────────────
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Services::class, inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Services $service = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $clientName = null;

    #[ORM\Column(length: 255)]
    private ?string $clientEmail = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $orderDate = null;

    #[ORM\Column(length: 50)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(nullable: true)]
    private ?float $totalPrice = 0.0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $deliveryDate = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\Column(length: 50)]
    private string $paymentMethod = self::PAYMENT_CASH;

    #[ORM\Column(length: 50)]
    private string $paymentStatus = self::PAYMENT_STATUS_PENDING;

    // ───────────── Constructor ─────────────
    public function __construct()
    {
        $this->orderDate = new \DateTimeImmutable();
        $this->status = self::STATUS_PENDING;
        $this->totalPrice = 0.0;
        $this->paymentMethod = self::PAYMENT_CASH;
        $this->paymentStatus = self::PAYMENT_STATUS_PENDING;
    }

    // ───────────── Getters & Setters ─────────────
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getService(): ?Services
    {
        return $this->service;
    }

    public function setService(?Services $service): static
    {
        $this->service = $service;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        if ($user) {
            $this->clientName = $user->getName();
            $this->clientEmail = $user->getEmail();
        }

        return $this;
    }

    public function getClientName(): ?string
    {
        return $this->clientName;
    }

    public function setClientName(string $clientName): static
    {
        $this->clientName = $clientName;
        return $this;
    }

    public function getClientEmail(): ?string
    {
        return $this->clientEmail;
    }

    public function setClientEmail(string $clientEmail): static
    {
        $this->clientEmail = $clientEmail;
        return $this;
    }

    public function getOrderDate(): ?\DateTimeImmutable
    {
        return $this->orderDate;
    }

    public function setOrderDate(\DateTimeImmutable $orderDate): static
    {
        $this->orderDate = $orderDate;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        if (!in_array($status, self::STATUSES)) {
            throw new \InvalidArgumentException("Invalid order status: $status");
        }
        $this->status = $status;
        return $this;
    }

    public function getTotalPrice(): ?float
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(?float $totalPrice): static
    {
        $this->totalPrice = $totalPrice ?? 0.0;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getDeliveryDate(): ?\DateTime
    {
        return $this->deliveryDate;
    }

    public function setDeliveryDate(?\DateTime $deliveryDate): static
    {
        $this->deliveryDate = $deliveryDate;
        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $user): static
    {
        $this->createdBy = $user;
        return $this;
    }

    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(string $paymentMethod): static
    {
        if (!in_array($paymentMethod, self::PAYMENT_METHODS)) {
            throw new \InvalidArgumentException("Invalid payment method: $paymentMethod");
        }
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function getPaymentStatus(): string
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(string $paymentStatus): static
    {
        if (!in_array($paymentStatus, self::PAYMENT_STATUSES)) {
            throw new \InvalidArgumentException("Invalid payment status: $paymentStatus");
        }
        $this->paymentStatus = $paymentStatus;
        return $this;
    }
}
