<?php

namespace codewithkyle\jitter\exceptions;

class JitterException extends \Exception
{
    private $statusCode;
    private $message;

    public function __construct(int $statusCode, string $message = null)
    {
        $this->statusCode = $statusCode;
        $this->message = $message;

        parent::__construct($message);
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function getMessage()
    {
        return $this->message;
    }
}
