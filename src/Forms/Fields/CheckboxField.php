<?php

declare(strict_types=1);

namespace Plugs\Forms\Fields;

use Plugs\Forms\Field;

class CheckboxField extends Field
{
    protected string $type = 'checkbox';

    public function render(): string
    {
        $checked = $this->value ? ' checked' : '';
        return sprintf(
            '<input type="checkbox" name="%s" value="1"%s %s>',
            $this->name,
            $checked,
            $this->renderAttributes()
        );
    }
}
