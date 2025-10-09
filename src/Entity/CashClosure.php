<?php
namespace App\Entity;

use App\Repository\CashClosureRepository;
use Doctrine\ORM\Mapping as ORM;

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

    // totaux à l’instant T
    #[ORM\Column] private int $totalIn = 0;
    #[ORM\Column] private int $totalOut = 0;
    #[ORM\Column] private int $balance = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    public function __construct()
    {
        $this->closedAt = new \DateTimeImmutable();
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

    public function getTotalIn(): int { return $this->totalIn; }
    public function setTotalIn(int $v): self { $this->totalIn = $v; return $this; }

    public function getTotalOut(): int { return $this->totalOut; }
    public function setTotalOut(int $v): self { $this->totalOut = $v; return $this; }

    public function getBalance(): int { return $this->balance; }
    public function setBalance(int $v): self { $this->balance = $v; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $n): self { $this->notes = $n; return $this; }
}
