<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/** Pesan aman untuk pengguna; tidak menyertakan nilai sel atau detail exception library. */
class SpreadsheetReadException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
