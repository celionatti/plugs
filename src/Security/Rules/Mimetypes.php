<?php

declare(strict_types=1);

namespace Plugs\Security\Rules;

use Plugs\Upload\UploadedFile;

class Mimetypes extends AbstractRule
{
    protected array $allowedMimes;
    protected string $message = 'The :attribute must be a file of type: :values.';

    public function __construct(array $allowedMimes = [])
    {
        $this->allowedMimes = $allowedMimes;
    }

    public function validate(string $attribute, $value, array $data): bool
    {
        if (!$value instanceof UploadedFile) {
            return false;
        }

        $actualMime = $value->getMimeType();

        return in_array($actualMime, $this->allowedMimes);
    }

    public function message(): string
    {
        return str_replace(':values', implode(', ', $this->allowedMimes), parent::message());
    }
}
