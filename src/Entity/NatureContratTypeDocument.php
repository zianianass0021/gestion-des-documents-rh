<?php

namespace App\Entity;

use App\Repository\NatureContratTypeDocumentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NatureContratTypeDocumentRepository::class)]
#[ORM\Table(name: 'p_nature_contrat_type_document')]
class NatureContratTypeDocument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10)]
    private ?string $documentAbbreviation = null;

    #[ORM\Column(length: 100)]
    private ?string $contractType = null;

    #[ORM\Column]
    private ?bool $required = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDocumentAbbreviation(): ?string
    {
        return $this->documentAbbreviation;
    }

    public function setDocumentAbbreviation(string $documentAbbreviation): static
    {
        $this->documentAbbreviation = $documentAbbreviation;

        return $this;
    }

    public function getContractType(): ?string
    {
        return $this->contractType;
    }

    public function setContractType(string $contractType): static
    {
        $this->contractType = $contractType;

        return $this;
    }

    public function isRequired(): ?bool
    {
        return $this->required;
    }

    public function setRequired(bool $required): static
    {
        $this->required = $required;

        return $this;
    }
}