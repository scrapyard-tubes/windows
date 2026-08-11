<?php

namespace ScrapyardIO\Tubes\Windows;

use RuntimeException;

class WindowException extends RuntimeException
{
    public static function notOpen(string $action): self
    {
        return new self("WindowHandler must be open() before {$action}.");
    }

    public static function alreadyOpen(): self
    {
        return new self('WindowHandler is already open.');
    }
}
