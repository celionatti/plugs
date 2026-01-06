<?php

declare(strict_types=1);

namespace Plugs\Forms\Fields;

use Plugs\Forms\Field;

class TextField extends Field
{
    protected string $type = 'text';

    public function render(): string
    {
        return sprintf(
            '<input type="%s" name="%s" value="%s" %s>',
            $this->type,
            $this->name,
            htmlspecialchars((string) $this->value, ENT_QUOTES, 'UTF-8'),
            $this->renderAttributes()
        );
    }
}
