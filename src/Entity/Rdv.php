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

    // Statuts de paiement
    public const PS_NON_PAYE = 'NON_PAYE';
    public const PS_PARTIEL  = 'PARTIEL';
    public const PS_PAYE     = 'PAYE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'rdvs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Client $client = null;

    #[ORM\ManyToOne(inversedBy: 'rdvs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?Prestation $prestation = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $startAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $endAt = null;

    #[ORM\Column(length: 255)]
    private ?string $status = self::S_PLANIFIE;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 255)]
    private ?string $paymentStatus = self::PS_NON_PAYE;

    #[ORM\Column]
    private ?int $priceAtBooking = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $cancelledBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $cancellationReason = null;

    /** @var Collection<int, Payment> */
    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'rdv')]
    private Collection $payments;

    public function __construct()
    {
        $this->payments = new ArrayCollection();
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

    public function getPriceAtBooking(): int
    {
        return $this->priceAtBooking ?? (int) ($this->prestation?->getPrix() ?? 0);
    }

    public function setPriceAtBooking(int $priceAtBooking): static
    {
        if ($priceAtBooking < 0) {
            throw new \InvalidArgumentException('Le tarif du rendez-vous ne peut pas être négatif.');
        }

        $this->priceAtBooking = $priceAtBooking;
        return $this;
    }

    #[ORM\PrePersist]
    public function snapshotPrice(): void
    {
        if ($this->priceAtBooking === null) {
            $this->priceAtBooking = (int) ($this->prestation?->getPrix() ?? 0);
        }
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

    public function setEndAt(?\DateTimeImmutable $endAt): static
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

    public function setNotes(?string $notes): static
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
            if ($payment->getRdv() === $this) {
                $payment->setRdv(null);
            }
        }
        return $this;
    }

    public function getPaymentStatus(): ?string
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(string $paymentStatus): static
    {
        $this->paymentStatus = $paymentStatus;
        return $this;
    }

    /**
     * Calcule le montant total payé pour ce rendez-vous
     */
    public function getTotalPaid(): int
    {
        $total = 0;
        foreach ($this->payments as $payment) {
            if (!$payment->isCancelled()) {
                $total += $payment->getAmount();
            }
        }
        return $total;
    }

    /**
     * Calcule le montant restant à payer
     */
    public function getRemainingAmount(): int
    {
        return max(0, $this->getPriceAtBooking() - $this->getTotalPaid());
    }

    /**
     * Calcule le prix total de la prestation
     */
    public function getTotalAmount(): int
    {
        return $this->getPriceAtBooking();
    }

    /**
     * Vérifie si le paiement est complet
     */
    public function isFullyPaid(): bool
    {
        return $this->getRemainingAmount() === 0 && $this->getTotalPaid() > 0;
    }

    /**
     * Vérifie si le paiement est partiel
     */
    public function isPartiallyPaid(): bool
    {
        $totalPaid = $this->getTotalPaid();
        return $totalPaid > 0 && $totalPaid < $this->getTotalAmount();
    }

    /**
     * Met à jour automatiquement le statut de paiement
     */
    public function updatePaymentStatus(): void
    {
        if ($this->isFullyPaid()) {
            $this->paymentStatus = self::PS_PAYE;
        } elseif ($this->isPartiallyPaid()) {
            $this->paymentStatus = self::PS_PARTIEL;
        } else {
            $this->paymentStatus = self::PS_NON_PAYE;
        }
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTimeImmutable $cancelledAt): static
    {
        $this->cancelledAt = $cancelledAt;
        return $this;
    }

    public function getCancelledBy(): ?User
    {
        return $this->cancelledBy;
    }

    public function setCancelledBy(?User $cancelledBy): static
    {
        $this->cancelledBy = $cancelledBy;
        return $this;
    }

    public function getCancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function setCancellationReason(?string $cancellationReason): static
    {
        $this->cancellationReason = $cancellationReason;
        return $this;
    }

    public function isCancelled(): bool
    {
        return $this->cancelledAt !== null;
    }

    public function __toString(): string
    {
        $client = method_exists($this->getClient(), '__toString') ? (string)$this->getClient() : 'Client';
        $prest  = method_exists($this->getPrestation(), '__toString') ? (string)$this->getPrestation() : 'Prestation';
        $date   = $this->getStartAt() ? $this->getStartAt()->format('d/m/Y H:i') : 'Sans date';

        return sprintf('%s • %s • %s', $client, $prest, $date);
    }
}
