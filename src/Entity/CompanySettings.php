<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class CompanySettings
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    // on gère un singleton: id=1
    private int $id = 1;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    private string $phone = '';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $address = null;

    // chemin relatif du logo (ex: uploads/logo.png)
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logoInvoicePath = null;

    // chemin relatif du logo (ex: uploads/logo.png)
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logoAppPath = null;

    #[ORM\Column]
    #[Assert\Range(min: 1, max: 168)]
    private int $confirmationReminderHours = 24;

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $cashClosureEnforcementStartDate = null;

    public function getId(): int { return $this->id; }

    public function getPhone(): string { return $this->phone; }
    public function setPhone(string $phone): self { $this->phone = $phone; return $this; }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $address): self { $this->address = $address; return $this; }

    public function getLogoInvoicePath(): ?string { return $this->logoInvoicePath; }
    public function setLogoInvoicePath(?string $logoInvoicePath): self { $this->logoInvoicePath = $logoInvoicePath; return $this; }

    public function getLogoAppPath(): ?string { return $this->logoAppPath; }
    public function setLogoAppPath(?string $logoAppPath): self { $this->logoAppPath = $logoAppPath; return $this; }

    public function getConfirmationReminderHours(): int { return $this->confirmationReminderHours; }
    public function setConfirmationReminderHours(int $hours): self
    {
        $this->confirmationReminderHours = $hours;
        return $this;
    }

    public function getCashClosureEnforcementStartDate(): \DateTimeImmutable
    {
        return $this->cashClosureEnforcementStartDate ??= new \DateTimeImmutable('today');
    }

    public function setCashClosureEnforcementStartDate(\DateTimeImmutable $date): self
    {
        $this->cashClosureEnforcementStartDate = $date->setTime(0, 0);
        return $this;
    }
}
