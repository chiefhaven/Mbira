<?php

namespace Modules\EIS\Exceptions;

use RuntimeException;

class EisSubscriptionRequiredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The active package does not include the EIS module.');
    }
}
