<?php

namespace App\Services\Sales;

class SavedSplitException extends \RuntimeException
{
    public $errorCode;
    public $httpStatus;

    public function __construct($code, $message, $status = 409)
    {
        parent::__construct($message);
        $this->errorCode = $code;
        $this->httpStatus = $status;
    }
}