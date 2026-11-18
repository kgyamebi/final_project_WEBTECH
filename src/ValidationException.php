<?php
declare(strict_types=1);

namespace App;

final class ValidationException extends \RuntimeException
{
    public function __construct(public array $fields, public array $old = [])
    {
        parent::__construct('validation');
    }
}
