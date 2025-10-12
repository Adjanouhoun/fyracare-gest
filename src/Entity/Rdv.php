<?php
namespace App\Entity;

use App\Repository\RdvRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RdvRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Rdv
{
    public const S_PLANIFIE = 'PLANIFIE';
    public const S_CONFIRME = 'CONFIRME';
    public const S_HONORE   = 'HONORE';
    public const S_ANNULE   = 'ANNULE';
    public const S_ABSENT   = 'ABSENT';

    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'rdvs')] #[ORM\JoinColumn(nullable: false)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Client $client = null;

    #[ORM\ManyToOne(inversedBy: 'rdvs')] #[ORM\JoinColumn(nullable: false)]
    private ?Prestation $prestation = null;

    #[ORM\Column] private ?\DateTimeImmutable $startAt = null;
    #[ORM\Column] private ?\DateTimeImmutable $endAt   = null;

    #[ORM\Column(length: 255)] private ?string $status = self::S_PLANIFIE;

    #[ORM\Column(type: Types::TEXT, nullable: true)] private ?string $notes = null;

    /** @var Collection<int, Payment> */
    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'rdv')]
    private Collection $payments;

    public function __construct() { $this->payments = new ArrayCollection(); }

    #[ORM\PrePersist] #[ORM\PreUpdate]
    public function computeEnd(): void
    {
        if ($this->prestation && $this->startAt) {
            $this->endAt = (clone $this->startAt)->modify('+' . $this->prestation->getDureeMin() . ' minutes');
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getPrestation(): ?Prestation
    {
        return $this->prestation;
    }

    public function setPrestation(?Prestation $prestation): static
    {
        $this->prestation = $prestation;

        return $this;
    }

    public function getStartAt(): ?\DateTimeImmutable
    {
        return $this->startAt;
    }

    public function setStartAt(\DateTimeImmutable $startAt): static
    {
        $this->startAt = $startAt;

        return $this;
    }

    public function getEndAt(): ?\DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(\DateTimeImmutable $endAt): static
    {
        $this->endAt = $endAt;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    /**
     * @return Collection<int, Payment>
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): static
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setRdv($this);
        }

        return $this;
    }

    public function removePayment(Payment $payment): static
    {
        if ($this->payments->removeElement($payment)) {
            // set the owning side to null (unless already changed)
            if ($payment->getRdv() === $this) {
                $payment->setRdv(null);
            }
        }

        return $this;
    }

    public function isPaid(): bool
    {
        $prest = $this->getPrestation();
        if (!$prest) {
            return false;
        }
        return $prest->getId();
    }

    public function __toString(): string
    {
        $client = method_exists($this->getClient(), '__toString') ? (string)$this->getClient() : 'Client';
        $prest  = method_exists($this->getPrestation(), '__toString') ? (string)$this->getPrestation() : 'Prestation';
        $date   = $this->getStartAt() ? $this->getStartAt()->format('d/m/Y H:i') : 'Sans date';

        return sprintf('%s • %s • %s', $client, $prest, $date);
    }
}








