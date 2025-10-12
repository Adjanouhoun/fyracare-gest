<?php

namespace App\Entity;

use App\Repository\CashMovementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CashMovementRepository::class)]
#[ORM\HasLifecycleCallbacks]
class CashMovement
{
    public const IN  = 'IN';
    public const OUT = 'OUT';

    public const SRC_PAYMENT = 'PAYMENT';
    public const SRC_MANUAL  = 'MANUAL';
    public const SRC_CLOSURE = 'CLOSURE';
    public const SRC_EXPENSE = 'EXPENSE'; // <-- ajouté

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // IN ou OUT
    #[ORM\Column(length: 10)]
    private string $type = self::IN;

    #[ORM\Column]
    private int $amount = 0;

    // PAYMENT / MANUAL / CLOSURE / EXPENSE
    #[ORM\Column(length: 20)]
    private string $source = self::SRC_MANUAL;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: ExpenseCategory::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ExpenseCategory $category = null;
  
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\ManyToOne(targetEntity: \App\Entity\User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?\App\Entity\User $updatedBy = null;

    #[ORM\ManyToOne(targetEntity: \App\Entity\User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?\App\Entity\User $deletedBy = null;

    public function __construct()
    {
        // évite "must not be accessed before initialization"
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        if (!$this->createdAt) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }
    #[ORM\PrePersist]
    public function initCreatedAt(): void
    {
        if (!$this->createdAt) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    // ---- getters/setters ----
    public function getId(): ?int { return $this->id; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }

    public function getAmount(): int { return $this->amount; }
    public function setAmount(int $amount): self { $this->amount = $amount; return $this; }

    public function getSource(): string { return $this->source; }
    public function setSource(string $source): self { $this->source = $source; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): self { $this->notes = $notes; return $this; }

    public function getDeletedAt(): ?\DateTimeImmutable { return $this->deletedAt; }
    public function setDeletedAt(?\DateTimeImmutable $dt): self { $this->deletedAt = $dt; return $this; }

    public function isDeleted(): bool { return $this->deletedAt !== null; }

    public function getUpdatedBy(): ?\App\Entity\User { return $this->updatedBy; }
    public function setUpdatedBy(?\App\Entity\User $u): self { $this->updatedBy = $u; return $this; }

    public function getDeletedBy(): ?\App\Entity\User { return $this->deletedBy; }
    public function setDeletedBy(?\App\Entity\User $u): self { $this->deletedBy = $u; return $this; }

    public function getCreatedAt(): \DateTimeImmutable
    {
        // garantie de retour non-null
        return $this->createdAt ??= new \DateTimeImmutable();
    }
    public function setCreatedAt(\DateTimeImmutable $dt): self 
    { 
        $this->createdAt = $dt; return $this; 
    }

    public function getCategory(): ?ExpenseCategory
    {
        return $this->category;
    }

    public function setCategory(?ExpenseCategory $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }
    public function setCreatedBy(?User $user): self
    {
        $this->createdBy = $user;
        return $this;
    }
}
