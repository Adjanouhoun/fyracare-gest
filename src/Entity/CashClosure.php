<?php
namespace App\Entity;

use App\Repository\CashClosureRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CashClosureRepository::class)]
#[ORM\HasLifecycleCallbacks]
class CashClosure
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // date/heure de clôture
    #[ORM\Column]
    private ?\DateTimeImmutable $closedAt = null;

    #[ORM\Column(type: 'date_immutable', unique: true)]
    private ?\DateTimeImmutable $closureDate = null;

    // totaux à l’instant T
    #[ORM\Column] private int $totalIn = 0;
    #[ORM\Column] private int $totalOut = 0;
    #[ORM\Column] private int $balance = 0;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $countedBalance = 0;

    #[ORM\Column]
    private int $variance = 0;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $closedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $lateReason = null;

    /** @var Collection<int, CashClosureReopening> */
    #[ORM\OneToMany(mappedBy: 'closure', targetEntity: CashClosureReopening::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['reopenedAt' => 'DESC'])]
    private Collection $reopenings;

    public function __construct()
    {
        $this->closedAt = new \DateTimeImmutable();
        $this->closureDate = new \DateTimeImmutable('today');
        $this->reopenings = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        if (!$this->closedAt) {
            $this->closedAt = new \DateTimeImmutable();
        }
    }

    // getters/setters
    public function getId(): ?int { return $this->id; }

    public function getClosedAt(): \DateTimeImmutable { return $this->closedAt ??= new \DateTimeImmutable(); }
    public function setClosedAt(\DateTimeImmutable $dt): self { $this->closedAt = $dt; return $this; }

    public function getClosureDate(): \DateTimeImmutable { return $this->closureDate ??= new \DateTimeImmutable('today'); }
    public function setClosureDate(\DateTimeImmutable $date): self { $this->closureDate = $date->setTime(0, 0); return $this; }

    public function getTotalIn(): int { return $this->totalIn; }
    public function setTotalIn(int $v): self { $this->totalIn = $v; return $this; }

    public function getTotalOut(): int { return $this->totalOut; }
    public function setTotalOut(int $v): self { $this->totalOut = $v; return $this; }

    public function getBalance(): int { return $this->balance; }
    public function setBalance(int $v): self { $this->balance = $v; return $this; }

    public function getCountedBalance(): int { return $this->countedBalance; }
    public function setCountedBalance(int $value): self { $this->countedBalance = $value; return $this; }

    public function getVariance(): int { return $this->variance; }
    public function setVariance(int $value): self { $this->variance = $value; return $this; }

    public function getClosedBy(): ?User { return $this->closedBy; }
    public function setClosedBy(?User $user): self { $this->closedBy = $user; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $n): self { $this->notes = $n; return $this; }

    public function getLateReason(): ?string { return $this->lateReason; }
    public function setLateReason(?string $reason): self { $this->lateReason = $reason; return $this; }

    /** @return Collection<int, CashClosureReopening> */
    public function getReopenings(): Collection { return $this->reopenings; }

    public function addReopening(CashClosureReopening $reopening): self
    {
        if (!$this->reopenings->contains($reopening)) {
            $this->reopenings->add($reopening);
            $reopening->setClosure($this);
        }

        return $this;
    }

    public function getActiveReopening(): ?CashClosureReopening
    {
        foreach ($this->reopenings as $reopening) {
            if (!$reopening->isReclosed()) {
                return $reopening;
            }
        }

        return null;
    }

    public function isReopened(): bool { return $this->getActiveReopening() !== null; }

    public function isLate(): bool
    {
        return $this->closureDate?->format('Y-m-d') !== $this->closedAt?->format('Y-m-d');
    }
}
