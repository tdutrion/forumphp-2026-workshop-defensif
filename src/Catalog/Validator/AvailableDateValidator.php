<?php

namespace App\Catalog\Validator;

use App\Catalog\CatalogCalendar;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class AvailableDateValidator extends ConstraintValidator
{
    public function __construct(private CatalogCalendar $calendar)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof AvailableDate) {
            throw new UnexpectedTypeException($constraint, AvailableDate::class);
        }
        // A missing date is NotBlank's business.
        if (null === $value) {
            return;
        }
        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        if (!\in_array($value, $this->calendar->availableDates(new \DateTimeImmutable('now', new \DateTimeZone('UTC'))), true)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
