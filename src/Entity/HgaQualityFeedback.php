<?php

namespace App\Entity;

use App\Repository\HgaQualityFeedbackRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HgaQualityFeedbackRepository::class)]
class HgaQualityFeedback
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Dokument $dokument = null;

    #[ORM\Column]
    private ?int $year = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?WegEinheit $einheit = null;

    #[ORM\Column(length: 20)]
    private ?string $aiProvider = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $aiResult = null;

    #[ORM\Column(length: 50)]
    private ?string $userFeedbackType = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userDescription = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $helpfulRating = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $implemented = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDokument(): ?Dokument
    {
        return $this->dokument;
    }

    public function setDokument(?Dokument $dokument): static
    {
        $this->dokument = $dokument;

        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(int $year): static
    {
        $this->year = $year;

        return $this;
    }

    public function getEinheit(): ?WegEinheit
    {
        return $this->einheit;
    }

    public function setEinheit(?WegEinheit $einheit): static
    {
        $this->einheit = $einheit;

        return $this;
    }

    public function getAiProvider(): ?string
    {
        return $this->aiProvider;
    }

    public function setAiProvider(string $aiProvider): static
    {
        $this->aiProvider = $aiProvider;

        return $this;
    }

    public function getAiResult(): ?array
    {
        return $this->aiResult;
    }

    public function setAiResult(?array $aiResult): static
    {
        $this->aiResult = $aiResult;

        return $this;
    }

    public function getUserFeedbackType(): ?string
    {
        return $this->userFeedbackType;
    }

    public function setUserFeedbackType(string $userFeedbackType): static
    {
        $this->userFeedbackType = $userFeedbackType;

        return $this;
    }

    public function getUserDescription(): ?string
    {
        return $this->userDescription;
    }

    public function setUserDescription(?string $userDescription): static
    {
        $this->userDescription = $userDescription;

        return $this;
    }

    public function getHelpfulRating(): ?bool
    {
        return $this->helpfulRating;
    }

    public function setHelpfulRating(?bool $helpfulRating): static
    {
        $this->helpfulRating = $helpfulRating;

        return $this;
    }

    public function isImplemented(): bool
    {
        return $this->implemented;
    }

    public function setImplemented(bool $implemented): static
    {
        $this->implemented = $implemented;

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
}
