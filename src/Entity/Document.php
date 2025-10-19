<?php

namespace App\Entity;

use App\Repository\DocumentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\Table(name: 'p_document')]
class Document
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $abbreviation = null;

    #[ORM\Column(length: 255)]
    private ?string $libelleComplet = null;

    #[ORM\Column(length: 100)]
    private ?string $typeDocument = null;

    #[ORM\Column(length: 255)]
    private ?string $usage = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $filePath = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $fileType = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $uploadedBy = null;

    #[ORM\ManyToOne(targetEntity: Dossier::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Dossier $dossier = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $statutAjout = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $statutTelechargement = null;

    public function __construct()
    {
        $this->statutAjout = 'non_ajoute';
        $this->statutTelechargement = 'non_telecharge';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAbbreviation(): ?string
    {
        return $this->abbreviation;
    }

    public function setAbbreviation(string $abbreviation): static
    {
        $this->abbreviation = $abbreviation;

        return $this;
    }

    public function getLibelleComplet(): ?string
    {
        return $this->libelleComplet;
    }

    public function setLibelleComplet(string $libelleComplet): static
    {
        $this->libelleComplet = $libelleComplet;

        return $this;
    }

    public function getTypeDocument(): ?string
    {
        return $this->typeDocument;
    }

    public function setTypeDocument(string $typeDocument): static
    {
        $this->typeDocument = $typeDocument;

        return $this;
    }

    public function getUsage(): ?string
    {
        return $this->usage;
    }

    public function setUsage(string $usage): static
    {
        $this->usage = $usage;

        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): static
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function getFileType(): ?string
    {
        return $this->fileType;
    }

    public function setFileType(?string $fileType): static
    {
        $this->fileType = $fileType;

        return $this;
    }

    public function getUploadedBy(): ?string
    {
        return $this->uploadedBy;
    }

    public function setUploadedBy(?string $uploadedBy): static
    {
        $this->uploadedBy = $uploadedBy;

        return $this;
    }

    public function getDossier(): ?Dossier
    {
        return $this->dossier;
    }

    public function setDossier(?Dossier $dossier): static
    {
        $this->dossier = $dossier;

        return $this;
    }

    /**
     * Check if the document has been uploaded
     */
    public function isUploaded(): bool
    {
        if (empty($this->filePath)) {
            return false;
        }
        
        // Check if file exists at the specified path
        $fileExists = file_exists($this->filePath);
        
        // Also check if it's a relative path from public/uploads/
        if (!$fileExists && !str_starts_with($this->filePath, '/')) {
            $publicPath = __DIR__ . '/../../public/uploads/documents/' . $this->filePath;
            $fileExists = file_exists($publicPath);
        }
        
        return $fileExists;
    }

    /**
     * Get the filename from the file path
     */
    public function getFilename(): ?string
    {
        if (!$this->filePath) {
            return null;
        }
        return basename($this->filePath);
    }

    public function getStatutAjout(): ?string
    {
        return $this->statutAjout;
    }

    public function setStatutAjout(?string $statutAjout): static
    {
        $this->statutAjout = $statutAjout;

        return $this;
    }

    public function getStatutTelechargement(): ?string
    {
        return $this->statutTelechargement;
    }

    public function setStatutTelechargement(?string $statutTelechargement): static
    {
        $this->statutTelechargement = $statutTelechargement;

        return $this;
    }

    /**
     * Get upload status as string
     */
    public function getUploadStatus(): string
    {
        return $this->isUploaded() ? 'uploaded' : 'pending';
    }

}