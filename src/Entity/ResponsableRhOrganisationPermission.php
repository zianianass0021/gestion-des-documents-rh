<?php

namespace App\Entity;

use App\Repository\ResponsableRhOrganisationPermissionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ResponsableRhOrganisationPermissionRepository::class)]
#[ORM\Table(name: 't_responsable_rh_organisation_permission')]
#[ORM\UniqueConstraint(name: 'unique_permission', columns: ['responsable_id', 'groupement', 'das', 'dossier'])]
class ResponsableRhOrganisationPermission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Employe::class, inversedBy: 'organisationPermissions')]
    #[ORM\JoinColumn(name: 'responsable_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Employe $responsable = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $groupement = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $das = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $dossier = null;

    #[ORM\Column(length: 20)]
    private ?string $permissionType = null; // 'GROUPEMENT', 'DAS', 'DOSSIER'

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getResponsable(): ?Employe
    {
        return $this->responsable;
    }

    public function setResponsable(?Employe $responsable): static
    {
        $this->responsable = $responsable;
        return $this;
    }

    public function getGroupement(): ?string
    {
        return $this->groupement;
    }

    public function setGroupement(?string $groupement): static
    {
        $this->groupement = $groupement;
        return $this;
    }

    public function getDas(): ?string
    {
        return $this->das;
    }

    public function setDas(?string $das): static
    {
        $this->das = $das;
        return $this;
    }

    public function getDossier(): ?string
    {
        return $this->dossier;
    }

    public function setDossier(?string $dossier): static
    {
        $this->dossier = $dossier;
        return $this;
    }

    public function getPermissionType(): ?string
    {
        return $this->permissionType;
    }

    public function setPermissionType(string $permissionType): static
    {
        $this->permissionType = $permissionType;
        return $this;
    }
}

