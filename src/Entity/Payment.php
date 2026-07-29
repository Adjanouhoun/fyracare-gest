<?php

namespace App\Entity;

use App\Repository\PaymentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
class Payment
{
    public const M_ESPECES='ESPECES'; 
    public const M_MOBILE='MOBILE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;


    #[ORM\ManyToOne(targetEntity: Rdv::class, inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Rdv $rdv = null;

    #[ORM\Column]
    #[Assert\Positive(message: 'Le montant du paiement doit être strictement positif.')]
    private ?int $amount = null;

    #[ORM\Column(length: 255)]
    private ?string $methode = self::M_ESPECES;

    #[ORM\Column]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(length: 255, nullable: true, unique: true)]
    private ?string $receiptNumber = null;

    #[ORM\Column]
    private ?bool $isDeposit = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $receivedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $cancelledBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $cancellationReason = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRdv(): ?Rdv
    {
        return $this->rdv;
    }

    public function setRdv(?Rdv $rdv): static
    {
        $this->rdv = $rdv;

        return $this;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getMethode(): ?string
    {
        return $this->methode;
    }

    public function setMethode(string $methode): static
    {
        $this->methode = $methode;

        return $this;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(\DateTimeImmutable $paidAt): static
    {
        $this->paidAt = $paidAt;

        return $this;
    }

    public function getReceiptNumber(): ?string
    {
        return $this->receiptNumber;
    }

    public function setReceiptNumber(?string $receiptNumber): static
    {
        $this->receiptNumber = $receiptNumber;

        return $this;
    }

    public function isDeposit(): ?bool
    {
        return $this->isDeposit;
    }

    public function setIsDeposit(bool $isDeposit): static
    {
        $this->isDeposit = $isDeposit;

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

    public function getReceivedBy(): ?User
    {
        return $this->receivedBy;
    }

    public function setReceivedBy(?User $receivedBy): static
    {
        $this->receivedBy = $receivedBy;
        return $this;
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
}
