<?php

declare(strict_types=1);

namespace Plugs\Forms\Fields;

use Plugs\Forms\Field;

class SelectField extends Field
{
    protected string $type = 'select';

    public function render(): string
    {
        $html = sprintf('<select name="%s" %s>', $this->name, $this->renderAttributes());

        foreach ($this->options as $value => $label) {
            $selected = (string) $this->value === (string) $value ? ' selected' : '';
            $html .= sprintf('<option value="%s"%s>%s</option>', $value, $selected, $label);
        }

        $html .= '</select>';

        return $html;
    }
}
