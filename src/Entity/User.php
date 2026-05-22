<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Entity\Order;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;

#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_ADMIN')"),
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Put(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ]
)]

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'user')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(length: 25, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 20, options: ["default" => "active"])]
    private ?string $status = 'active';

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isVerified = false;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $verificationToken = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Order::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $orders;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->orders = new ArrayCollection();
    }

    // ──────────────── Getters & Setters ────────────────
    public function getId(): ?int { return $this->id; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }
    public function getUserIdentifier(): string { return (string) $this->email; }

    public function getRoles(): array { return $this->roles; }
    public function setRoles(array $roles): static { $this->roles = $roles; return $this; }
    public function getDisplayRoles(): array { return array_values(array_filter($this->roles, fn($role) => $role !== 'ROLE_USER')); }

    public function getPassword(): ?string { return $this->password; }
    public function setPassword(string $password): static { $this->password = $password; return $this; }
    public function eraseCredentials(): void {}

    public function getName(): ?string
    {
        if ($this->name !== null && trim($this->name) !== '') {
            return $this->name;
        }

        $fullName = trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));

        return $fullName !== '' ? $fullName : null;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getFirstName(): ?string { return $this->firstName; }
    public function setFirstName(?string $firstName): static { $this->firstName = $firstName; return $this; }

    public function getLastName(): ?string { return $this->lastName; }
    public function setLastName(?string $lastName): static { $this->lastName = $lastName; return $this; }

    public function getAvatar(): ?string { return $this->avatar; }
    public function setAvatar(?string $avatar): static { $this->avatar = $avatar; return $this; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): static { $this->phone = $phone; return $this; }

    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $address): static { $this->address = $address; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }

    public function isVerified(): bool { return $this->isVerified; }
    public function setIsVerified(bool $isVerified): static { $this->isVerified = $isVerified; return $this; }

    public function getVerificationToken(): ?string { return $this->verificationToken; }
    public function setVerificationToken(?string $verificationToken): static { $this->verificationToken = $verificationToken; return $this; }

    /** Verified users must not keep a pending token. */
    public function markEmailAsVerified(): static
    {
        $this->isVerified = true;
        $this->verificationToken = null;

        return $this;
    }

    /** Awaiting email verification link click. */
    public function markEmailAsPendingVerification(string $token): static
    {
        $this->isVerified = false;
        $this->verificationToken = $token;

        return $this;
    }

    // ──────────────── Orders ────────────────
    public function getOrders(): Collection { return $this->orders; }
    public function addOrder(Order $order): static { if (!$this->orders->contains($order)) { $this->orders->add($order); $order->setUser($this); } return $this; }
    public function removeOrder(Order $order): static { if ($this->orders->removeElement($order)) { if ($order->getUser() === $this) $order->setUser(null); } return $this; }

    public function isAdmin(): bool { return in_array('ROLE_ADMIN', $this->getRoles(), true); }
    public function isStaff(): bool { return in_array('ROLE_STAFF', $this->getRoles(), true); }

    public function isAccountActive(): bool
    {
        return ($this->status ?? 'active') === 'active';
    }

    /** Client/mobile app user (ROLE_CLIENT or ROLE_USER, not staff/admin). */
    public function isMobileAppUser(): bool
    {
        if ($this->isAdmin() || $this->isStaff()) {
            return false;
        }

        $roles = $this->getRoles();

        return in_array('ROLE_CLIENT', $roles, true) || in_array('ROLE_USER', $roles, true);
    }
}
