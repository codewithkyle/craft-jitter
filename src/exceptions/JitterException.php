<?php
/**
 * Jitter plugin for Craft CMS 4.x
 *
 * A just in time image transformation service.
 *
 * @link      https://kyleandrews.dev/
 * @copyright Copyright (c) 2022 Kyle Andrews
 */

namespace codewithkyle\jitter\exceptions;

class JitterException extends \Exception
{
    private $statusCode;
    private $errorMessage;

    public function __construct(int $statusCode, string $message = null)
    {
        $this->statusCode = $statusCode;
        $this->errorMessage = $message;

        parent::__construct($message);
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }
}
