<?php

namespace App\EventListener;

use App\Entity\Traits\TimestampableTrait;
use App\Entity\Traits\BlameableTrait;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

#[AsEntityListener(event: Events::prePersist, method: 'prePersist')]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate')]
class TraceabilityListener
{
    public function __construct(
        private Security $security
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        // Check if entity uses TimestampableTrait
        if (!$this->usesTrait($entity, TimestampableTrait::class)) {
            return;
        }

        $user = $this->security->getUser();

        // Set createdAt if not already set
        if (method_exists($entity, 'getCreatedAt') && $entity->getCreatedAt() === null) {
            $entity->setCreatedAt(new \DateTime());
        }

        // Set createdBy if entity uses BlameableTrait and user is logged in
        if ($this->usesTrait($entity, BlameableTrait::class)) {
            if (method_exists($entity, 'getCreatedBy') && $entity->getCreatedBy() === null) {
                if ($user) {
                    $entity->setCreatedBy($user);
                }
                // If no user is logged in (e.g., in commands), leave it as null
            }
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        // Check if entity uses TimestampableTrait
        if (!$this->usesTrait($entity, TimestampableTrait::class)) {
            return;
        }

        $user = $this->security->getUser();

        // Set updatedAt
        if (method_exists($entity, 'setUpdatedAt')) {
            $entity->setUpdatedAt(new \DateTime());
        }

        // Set updatedBy if entity uses BlameableTrait and user is logged in
        if ($this->usesTrait($entity, BlameableTrait::class) && $user) {
            if (method_exists($entity, 'setUpdatedBy')) {
                $entity->setUpdatedBy($user);
            }
        }
    }

    private function usesTrait(object $object, string $trait): bool
    {
        $traits = class_uses_recursive(get_class($object));
        return in_array($trait, $traits);
    }
}

