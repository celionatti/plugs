<?php

declare(strict_types=1);

namespace Plugs\Forms\Fields;

use Plugs\Forms\Field;

class SubmitField extends Field
{
    protected string $type = 'submit';

    public function render(): string
    {
        return sprintf(
            '<button type="submit" %s>%s</button>',
            $this->renderAttributes(),
            $this->label
        );
    }
}
