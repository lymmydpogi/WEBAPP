<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\User;
use App\Entity\Services;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;

/**
 * One order row = one service + one transaction (notes, status, dates are per order).
 * The same user may order the same service many times; each submission is a new row.
 */
#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')"),
        new GetCollection(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')"),
        new Post(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')"),
        new Put(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')"),
        new Delete(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')"),
    ]
)]

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
class Order
{
    // ───────────── Order Status ─────────────
    public const STATUS_PENDING = 'Pending';
    public const STATUS_APPROVED = 'Approved';
    public const STATUS_IN_PROGRESS = 'In Progress';
    public const STATUS_COMPLETED = 'Completed';
    public const STATUS_CANCELLED = 'Cancelled';
    public const STATUS_REJECTED = 'Rejected';

    /** @deprecated Use STATUS_CANCELLED */
    public const STATUS_CANCELED = self::STATUS_CANCELLED;

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
        self::STATUS_REJECTED,
    ];

    public const ADMIN_STATUSES = self::STATUSES;

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

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $quantity = 1;

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
        $this->quantity = 1;
        $this->paymentMethod = self::PAYMENT_CASH;
        $this->paymentStatus = self::PAYMENT_STATUS_PENDING;
    }

    public function isPending(): bool
    {
        return $this->getStatus() === self::STATUS_PENDING;
    }

    public function canBeModifiedByClient(): bool
    {
        return $this->isPending();
    }

    public static function calculateTotalFromService(?Services $service, int $quantity): float
    {
        if (!$service || $quantity <= 0) {
            return 0.0;
        }

        return round((float) $service->getPrice() * $quantity, 2);
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
        if ($this->status === 'Canceled') {
            return self::STATUS_CANCELLED;
        }

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

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Quantity must be at least 1.');
        }
        $this->quantity = $quantity;
        return $this;
    }

    public function recalculateTotalFromService(): static
    {
        $this->totalPrice = self::calculateTotalFromService($this->service, $this->quantity);
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
