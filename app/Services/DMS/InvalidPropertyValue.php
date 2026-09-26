<?php

namespace App\Services\DMS;

use InvalidArgumentException;

/**
 * A single property value that does not fit its definition; the message completes the
 * sentence "<label> ...". Collected into a validation error by EventPropertyValidator.
 */
class InvalidPropertyValue extends InvalidArgumentException
{
}
