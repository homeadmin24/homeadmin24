<?php

namespace App\Entity;

use App\Repository\MonatsSaldoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MonatsSaldoRepository::class)]
#[ORM\Table(name: 'monats_saldo')]
#[ORM\UniqueConstraint(name: 'unique_weg_month', columns: ['weg_id', 'balance_month'])]
class MonatsSaldo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Weg::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Weg $weg = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $balanceMonth = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $openingBalance = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $closingBalance = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $transactionSum = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $transactionCount = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWeg(): ?Weg
    {
        return $this->weg;
    }

    public function setWeg(?Weg $weg): static
    {
        $this->weg = $weg;

        return $this;
    }

    public function getBalanceMonth(): ?\DateTimeInterface
    {
        return $this->balanceMonth;
    }

    public function setBalanceMonth(\DateTimeInterface $balanceMonth): static
    {
        $this->balanceMonth = $balanceMonth;

        return $this;
    }

    public function getOpeningBalance(): ?string
    {
        return $this->openingBalance;
    }

    public function setOpeningBalance(string $openingBalance): static
    {
        $this->openingBalance = $openingBalance;

        return $this;
    }

    public function getClosingBalance(): ?string
    {
        return $this->closingBalance;
    }

    public function setClosingBalance(string $closingBalance): static
    {
        $this->closingBalance = $closingBalance;

        return $this;
    }

    public function getTransactionSum(): ?string
    {
        return $this->transactionSum;
    }

    public function setTransactionSum(string $transactionSum): static
    {
        $this->transactionSum = $transactionSum;

        return $this;
    }

    public function getTransactionCount(): ?int
    {
        return $this->transactionCount;
    }

    public function setTransactionCount(int $transactionCount): static
    {
        $this->transactionCount = $transactionCount;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getBalanceChange(): float
    {
        return (float) $this->closingBalance - (float) $this->openingBalance;
    }

    public function getFormattedBalanceMonth(): string
    {
        return $this->balanceMonth?->format('M Y') ?? '';
    }

    public function isPositiveChange(): bool
    {
        return $this->getBalanceChange() > 0;
    }
}
