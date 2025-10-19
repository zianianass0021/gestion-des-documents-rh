<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class FileExtensionValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof FileExtension) {
            throw new UnexpectedTypeException($constraint, FileExtension::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!$value instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            throw new UnexpectedValueException($value, 'UploadedFile');
        }

        $originalName = $value->getClientOriginalName();
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, $constraint->extensions)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ extensions }}', implode(', ', $constraint->extensions))
                ->addViolation();
        }

        // Vérifier la taille si spécifiée
        if ($constraint->maxSize && $value->getSize() > $constraint->maxSize) {
            $this->context->buildViolation($constraint->sizeMessage)
                ->setParameter('{{ limit }}', $this->formatBytes($constraint->maxSize))
                ->addViolation();
        }
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
