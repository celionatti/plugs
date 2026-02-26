<?php

declare(strict_types=1);

namespace Plugs\Security\Rules;

use Plugs\Upload\UploadedFile;

class Dimensions extends AbstractRule
{
    protected array $constraints;
    protected string $message = 'The :attribute has invalid image dimensions.';

    public function __construct($constraints = [])
    {
        // If constraints is a string (e.g., from 'dimensions:width=100'), parse it
        if (is_string($constraints)) {
            $parsed = [];
            foreach (explode(',', $constraints) as $part) {
                if (str_contains($part, '=')) {
                    [$key, $val] = explode('=', $part, 2);
                    $parsed[trim($key)] = trim($val);
                }
            }
            $constraints = $parsed;
        }

        $this->constraints = (array) $constraints;
    }

    public function validate(string $attribute, $value, array $data): bool
    {
        if (!$value instanceof UploadedFile || !$value->isImage()) {
            return false;
        }

        $size = getimagesize($value->getTempPath());
        if (!$size) {
            return false;
        }

        [$width, $height] = $size;

        if (isset($this->constraints['width']) && $width != $this->constraints['width'])
            return false;
        if (isset($this->constraints['height']) && $height != $this->constraints['height'])
            return false;
        if (isset($this->constraints['min_width']) && $width < $this->constraints['min_width'])
            return false;
        if (isset($this->constraints['min_height']) && $height < $this->constraints['min_height'])
            return false;
        if (isset($this->constraints['max_width']) && $width > $this->constraints['max_width'])
            return false;
        if (isset($this->constraints['max_height']) && $height > $this->constraints['max_height'])
            return false;

        return true;
    }
}
