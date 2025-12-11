<?php

namespace App\Entity;

use App\Repository\ActivityLogRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\User;

#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
#[ORM\Table(name: "activity_logs")]
class ActivityLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // The user who performed the action
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    // Role of the user (Admin, Staff, etc.)
    #[ORM\Column(length: 50)]
    private ?string $role = null;

    // Action type (LOGIN, LOGOUT, CREATE_USER, DELETE_USER, etc.)
    #[ORM\Column(length: 50)]
    private ?string $action = null;

    // Optional details about the action
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $actionDetails = null;

    // Related entity type (User, Order, Service, etc.)
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $targetEntity = null;

    // Related entity ID (optional)
    #[ORM\Column(nullable: true)]
    private ?int $targetEntityId = null;

    // Timestamp
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    // Optional description or additional info
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;


    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // ──────────────── Getters & Setters ────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        $roles = $user->getRoles();
        $this->role = $roles[0] ?? null; // Only store the primary role
        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function getActionDetails(): ?string
    {
        return $this->actionDetails;
    }

    public function setActionDetails(?string $details): static
    {
        $this->actionDetails = $details;
        return $this;
    }

    public function getTargetEntity(): ?string
    {
        return $this->targetEntity;
    }

    public function setTargetEntity(?string $entity): static
    {
        $this->targetEntity = $entity;
        return $this;
    }

    public function getTargetEntityId(): ?int
    {
        return $this->targetEntityId;
    }

    public function setTargetEntityId(?int $id): static
    {
        $this->targetEntityId = $id;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    // ──────────────── Helper Methods ────────────────

    // Get the user ID
    public function getUserId(): ?int
    {
        return $this->user?->getId();
    }

    // Get the user email/identifier
    public function getUserEmail(): ?string
    {
        return $this->user?->getUserIdentifier();
    }
}
