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
    private ?string $logoPath = null;

    public function getId(): int { return $this->id; }

    public function getPhone(): string { return $this->phone; }
    public function setPhone(string $phone): self { $this->phone = $phone; return $this; }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $address): self { $this->address = $address; return $this; }

    public function getLogoPath(): ?string { return $this->logoPath; }
    public function setLogoPath(?string $logoPath): self { $this->logoPath = $logoPath; return $this; }
}
