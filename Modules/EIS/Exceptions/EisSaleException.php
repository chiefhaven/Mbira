<?php

namespace Modules\EIS\Exceptions;

use Exception;

class EisSaleException extends Exception
{
    public const DUPLICATE_INVOICE = 1001;

    public function __construct($message = "An error occurred in EIS Sale module", $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}