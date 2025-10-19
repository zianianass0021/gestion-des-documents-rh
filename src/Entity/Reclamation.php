<?php

namespace App\Entity;

use App\Repository\ReclamationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReclamationRepository::class)]
#[ORM\Table(name: 't_reclamation')]
class Reclamation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Employe::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Employe $employe = null;

    #[ORM\ManyToOne(targetEntity: Employe::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Employe $manager = null;

    #[ORM\Column(length: 50)]
    private ?string $typeReclamation = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $commentaire = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $documentPath = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $documentType = null;

    #[ORM\Column(length: 50)]
    private ?string $statut = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateTraitement = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reponseRh = null;

    #[ORM\ManyToOne(targetEntity: Employe::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Employe $traitePar = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->statut = 'en_attente';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmploye(): ?Employe
    {
        return $this->employe;
    }

    public function setEmploye(?Employe $employe): static
    {
        $this->employe = $employe;
        return $this;
    }

    public function getManager(): ?Employe
    {
        return $this->manager;
    }

    public function setManager(?Employe $manager): static
    {
        $this->manager = $manager;
        return $this;
    }

    public function getTypeReclamation(): ?string
    {
        return $this->typeReclamation;
    }

    public function setTypeReclamation(string $typeReclamation): static
    {
        $this->typeReclamation = $typeReclamation;
        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(string $commentaire): static
    {
        $this->commentaire = $commentaire;
        return $this;
    }

    public function getDocumentPath(): ?string
    {
        return $this->documentPath;
    }

    public function setDocumentPath(?string $documentPath): static
    {
        $this->documentPath = $documentPath;
        return $this;
    }

    public function getDocumentType(): ?string
    {
        return $this->documentType;
    }

    public function setDocumentType(?string $documentType): static
    {
        $this->documentType = $documentType;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeInterface $dateCreation): static
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }

    public function getDateTraitement(): ?\DateTimeInterface
    {
        return $this->dateTraitement;
    }

    public function setDateTraitement(?\DateTimeInterface $dateTraitement): static
    {
        $this->dateTraitement = $dateTraitement;
        return $this;
    }

    public function getReponseRh(): ?string
    {
        return $this->reponseRh;
    }

    public function setReponseRh(?string $reponseRh): static
    {
        $this->reponseRh = $reponseRh;
        return $this;
    }

    public function getTraitePar(): ?Employe
    {
        return $this->traitePar;
    }

    public function setTraitePar(?Employe $traitePar): static
    {
        $this->traitePar = $traitePar;
        return $this;
    }

    public function getTypeReclamationLabel(): string
    {
        return match($this->typeReclamation) {
            'assiduite' => 'Problème d\'Assiduité',
            'accident_travail' => 'Accident de Travail',
            default => $this->typeReclamation
        };
    }

    public function getStatutLabel(): string
    {
        return match($this->statut) {
            'en_attente' => 'En Attente',
            'en_cours' => 'En Cours',
            'traitee' => 'Traitée',
            'rejetee' => 'Rejetée',
            default => $this->statut
        };
    }

    public function getStatutBadgeClass(): string
    {
        return match($this->statut) {
            'en_attente' => 'warning',
            'en_cours' => 'info',
            'traitee' => 'success',
            'rejetee' => 'danger',
            default => 'secondary'
        };
    }
}