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

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        // garantie de retour non-null
        return $this->createdAt ??= new \DateTimeImmutable();
    }
    public function setCreatedAt(\DateTimeImmutable $dt): self { $this->createdAt = $dt; return $this; }
}
