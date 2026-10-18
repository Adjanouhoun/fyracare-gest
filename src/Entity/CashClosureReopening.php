<?php

namespace App\Entity;

use App\Repository\CashClosureReopeningRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CashClosureReopeningRepository::class)]
class CashClosureReopening
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CashClosure::class, inversedBy: 'reopenings')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CashClosure $closure = null;

    #[ORM\Column]
    private \DateTimeImmutable $reopenedAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $reopenedBy = null;

    #[ORM\Column(type: 'text')]
    private string $reason = '';

    #[ORM\Column]
    private \DateTimeImmutable $previousClosedAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $previousClosedBy = null;

    #[ORM\Column] private int $previousTotalIn = 0;
    #[ORM\Column] private int $previousTotalOut = 0;
    #[ORM\Column] private int $previousBalance = 0;
    #[ORM\Column] private int $previousCountedBalance = 0;
    #[ORM\Column] private int $previousVariance = 0;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $previousNotes = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $previousLateReason = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reclosedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $reclosedBy = null;

    public function __construct()
    {
        $this->reopenedAt = new \DateTimeImmutable();
        $this->previousClosedAt = new \DateTimeImmutable();
    }

    public static function fromClosure(CashClosure $closure, User $author, string $reason): self
    {
        $event = new self();
        $event->closure = $closure;
        $event->reopenedAt = new \DateTimeImmutable();
        $event->reopenedBy = $author;
        $event->reason = $reason;
        $event->previousClosedAt = $closure->getClosedAt();
        $event->previousClosedBy = $closure->getClosedBy();
        $event->previousTotalIn = $closure->getTotalIn();
        $event->previousTotalOut = $closure->getTotalOut();
        $event->previousBalance = $closure->getBalance();
        $event->previousCountedBalance = $closure->getCountedBalance();
        $event->previousVariance = $closure->getVariance();
        $event->previousNotes = $closure->getNotes();
        $event->previousLateReason = $closure->getLateReason();

        return $event;
    }

    public function getId(): ?int { return $this->id; }
    public function getClosure(): ?CashClosure { return $this->closure; }
    public function setClosure(CashClosure $closure): self { $this->closure = $closure; return $this; }
    public function getReopenedAt(): \DateTimeImmutable { return $this->reopenedAt; }
    public function getReopenedBy(): ?User { return $this->reopenedBy; }
    public function getReason(): string { return $this->reason; }
    public function getPreviousClosedAt(): \DateTimeImmutable { return $this->previousClosedAt; }
    public function getPreviousClosedBy(): ?User { return $this->previousClosedBy; }
    public function getPreviousTotalIn(): int { return $this->previousTotalIn; }
    public function getPreviousTotalOut(): int { return $this->previousTotalOut; }
    public function getPreviousBalance(): int { return $this->previousBalance; }
    public function getPreviousCountedBalance(): int { return $this->previousCountedBalance; }
    public function getPreviousVariance(): int { return $this->previousVariance; }
    public function getPreviousNotes(): ?string { return $this->previousNotes; }
    public function getPreviousLateReason(): ?string { return $this->previousLateReason; }
    public function getReclosedAt(): ?\DateTimeImmutable { return $this->reclosedAt; }
    public function getReclosedBy(): ?User { return $this->reclosedBy; }
    public function isReclosed(): bool { return $this->reclosedAt !== null; }

    public function markReclosed(User $author, \DateTimeImmutable $at): self
    {
        $this->reclosedAt = $at;
        $this->reclosedBy = $author;

        return $this;
    }
}
