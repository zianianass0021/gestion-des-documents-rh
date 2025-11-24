<?php

namespace App\Entity\Traits;

use App\Entity\Employe;
use Doctrine\ORM\Mapping as ORM;

trait BlameableTrait
{
    #[ORM\ManyToOne(targetEntity: Employe::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Employe $createdBy = null;

    #[ORM\ManyToOne(targetEntity: Employe::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Employe $updatedBy = null;

    #[ORM\ManyToOne(targetEntity: Employe::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Employe $disabledBy = null;

    public function getCreatedBy(): ?Employe
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?Employe $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getUpdatedBy(): ?Employe
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?Employe $updatedBy): static
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }

    public function getDisabledBy(): ?Employe
    {
        return $this->disabledBy;
    }

    public function setDisabledBy(?Employe $disabledBy): static
    {
        $this->disabledBy = $disabledBy;
        return $this;
    }
}

