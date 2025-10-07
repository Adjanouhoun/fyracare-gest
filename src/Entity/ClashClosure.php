<?php

namespace App\Entity;

use App\Repository\ClashClosureRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClashClosureRepository::class)]
class ClashClosure
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $day = null;

    #[ORM\Column]
    private ?int $totalCash = 0;

    #[ORM\Column]
    private ?int $totalMobile = 0;

    #[ORM\Column]
    private ?int $grandTotal = 0;

    #[ORM\Column(type: Types::ARRAY, nullable: true)]
    private ?array $meta = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDay(): ?\DateTimeImmutable
    {
        return $this->day;
    }

    public function setDay(\DateTimeImmutable $day): static
    {
        $this->day = $day;

        return $this;
    }

    public function getTotalCash(): ?int
    {
        return $this->totalCash;
    }

    public function setTotalCash(int $totalCash): static
    {
        $this->totalCash = $totalCash;

        return $this;
    }

    public function getTotalMobile(): ?int
    {
        return $this->totalMobile;
    }

    public function setTotalMobile(int $totalMobile): static
    {
        $this->totalMobile = $totalMobile;

        return $this;
    }

    public function getGrandTotal(): ?int
    {
        return $this->grandTotal;
    }

    public function setGrandTotal(int $grandTotal): static
    {
        $this->grandTotal = $grandTotal;

        return $this;
    }

    public function getMeta(): ?array
    {
        return $this->meta;
    }

    public function setMeta(?array $meta): static
    {
        $this->meta = $meta;

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
}
