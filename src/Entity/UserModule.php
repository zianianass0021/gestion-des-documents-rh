<?php

namespace App\Entity;

use App\Repository\UserModuleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserModuleRepository::class)]
#[ORM\Table(name: 'user_module')]
#[ORM\UniqueConstraint(name: 'unique_user_module', columns: ['user_id', 'module_id'])]
#[ORM\Index(name: 'idx_user_id', columns: ['user_id'])]
#[ORM\Index(name: 'idx_module_id', columns: ['module_id'])]
class UserModule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Employe::class, inversedBy: 'userModules')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Employe $user = null;

    #[ORM\ManyToOne(targetEntity: Module::class, inversedBy: 'userModules')]
    #[ORM\JoinColumn(name: 'module_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Module $module = null;

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

    public function getUser(): ?Employe
    {
        return $this->user;
    }

    public function setUser(?Employe $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getModule(): ?Module
    {
        return $this->module;
    }

    public function setModule(?Module $module): static
    {
        $this->module = $module;

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

