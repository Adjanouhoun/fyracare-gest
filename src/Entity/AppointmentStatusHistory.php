<?php

namespace App\Entity;

use App\Repository\AppointmentStatusHistoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppointmentStatusHistoryRepository::class)]
class AppointmentStatusHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Rdv::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Rdv $rdv = null;

    #[ORM\Column(length: 20)]
    private string $oldStatus;

    #[ORM\Column(length: 20)]
    private string $newStatus;

    #[ORM\Column]
    private \DateTimeImmutable $changedAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $changedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    public function __construct()
    {
        $this->changedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getRdv(): ?Rdv { return $this->rdv; }
    public function setRdv(Rdv $rdv): self { $this->rdv = $rdv; return $this; }
    public function getOldStatus(): string { return $this->oldStatus; }
    public function setOldStatus(string $status): self { $this->oldStatus = $status; return $this; }
    public function getNewStatus(): string { return $this->newStatus; }
    public function setNewStatus(string $status): self { $this->newStatus = $status; return $this; }
    public function getChangedAt(): \DateTimeImmutable { return $this->changedAt; }
    public function setChangedAt(\DateTimeImmutable $date): self { $this->changedAt = $date; return $this; }
    public function getChangedBy(): ?User { return $this->changedBy; }
    public function setChangedBy(?User $user): self { $this->changedBy = $user; return $this; }
    public function getNote(): ?string { return $this->note; }
    public function setNote(?string $note): self { $this->note = $note; return $this; }
}
