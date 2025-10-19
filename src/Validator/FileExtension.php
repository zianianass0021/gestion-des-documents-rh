<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class FileExtension extends Constraint
{
    public $message = 'Le fichier doit avoir une des extensions suivantes : {{ extensions }}.';
    public $sizeMessage = 'Le fichier est trop volumineux ({{ limit }} maximum).';
    public $extensions = [];
    public $maxSize = null;

    public function getDefaultOption(): ?string
    {
        return 'extensions';
    }

    public function getRequiredOptions(): array
    {
        return ['extensions'];
    }
}
