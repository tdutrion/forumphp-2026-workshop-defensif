<?php

namespace App\Catalog\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * A local day ('Y-m-d') that can still be planned (see CatalogCalendar), like the choices of the plan form.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class AvailableDate extends Constraint
{
    public string $message = 'The value you selected is not a valid choice.';
}
