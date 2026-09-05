<?php

namespace App\Exceptions;

use RuntimeException;

class SecurityException extends RuntimeException
{
    // Thrown when an authoritative security or multi-tenant boundary invariant is violated
}
