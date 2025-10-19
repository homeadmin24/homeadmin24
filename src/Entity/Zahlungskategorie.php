<?php

namespace App\Entity;

use App\Repository\ZahlungskategorieRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ZahlungskategorieRepository::class)]
class Zahlungskategorie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    /**
     * @phpstan-ignore-next-line
     */
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $beschreibung = null;

    #[ORM\Column(type: 'boolean')]
    private ?bool $istPositiverBetrag = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $fieldConfig = [];

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $validationRules = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $helpText = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $sortOrder = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'boolean')]
    private bool $allowsZeroAmount = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getBeschreibung(): ?string
    {
        return $this->beschreibung;
    }

    public function setBeschreibung(?string $beschreibung): self
    {
        $this->beschreibung = $beschreibung;

        return $this;
    }

    public function getIstPositiverBetrag(): ?bool
    {
        return $this->istPositiverBetrag;
    }

    public function setIstPositiverBetrag(bool $istPositiverBetrag): self
    {
        $this->istPositiverBetrag = $istPositiverBetrag;

        return $this;
    }

    public function isIstPositiverBetrag(): ?bool
    {
        return $this->istPositiverBetrag;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getFieldConfig(): ?array
    {
        return $this->fieldConfig;
    }

    /**
     * @param array<string, mixed>|null $fieldConfig
     */
    public function setFieldConfig(?array $fieldConfig): self
    {
        $this->fieldConfig = $fieldConfig;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getValidationRules(): ?array
    {
        return $this->validationRules;
    }

    /**
     * @param array<string, mixed>|null $validationRules
     */
    public function setValidationRules(?array $validationRules): self
    {
        $this->validationRules = $validationRules;

        return $this;
    }

    public function getHelpText(): ?string
    {
        return $this->helpText;
    }

    public function setHelpText(?string $helpText): self
    {
        $this->helpText = $helpText;

        return $this;
    }

    public function getSortOrder(): ?int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(?int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function isAllowsZeroAmount(): bool
    {
        return $this->allowsZeroAmount;
    }

    public function setAllowsZeroAmount(bool $allowsZeroAmount): self
    {
        $this->allowsZeroAmount = $allowsZeroAmount;

        return $this;
    }
}
