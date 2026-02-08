<?php

declare(strict_types=1);

namespace Plugs\Forms\Themes;

use Plugs\Forms\Field;
use Plugs\Forms\ThemeInterface;

class BootstrapTheme implements ThemeInterface
{
    public function renderFormOpen(string $action, string $method, array $attributes): string
    {
        $attrs = [];
        foreach ($attributes as $key => $value) {
            $attrs[] = sprintf('%s="%s"', $key, htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'));
        }
        $attrsString = !empty($attrs) ? ' ' . implode(' ', $attrs) : '';

        return sprintf('<form action="%s" method="%s"%s>', $action, $method, $attrsString);
    }

    public function renderFormClose(): string
    {
        return '</form>';
    }

    public function renderField(Field $field): string
    {
        $id = $field->getAttributes()['id'] ?? 'field_' . $field->getName();
        $hasErrors = $field->hasErrors();
        $errorClass = $hasErrors ? ' is-invalid' : '';

        // Add form-control class to most fields
        if (!in_array($field->getType(), ['checkbox', 'radio', 'submit', 'button'])) {
            $field->attributes(['class' => trim(($field->getAttributes()['class'] ?? '') . ' form-control' . $errorClass)]);
        } elseif ($field->getType() === 'checkbox' || $field->getType() === 'radio') {
            $field->attributes(['class' => trim(($field->getAttributes()['class'] ?? '') . ' form-check-input' . $errorClass)]);
            /** @phpstan-ignore-next-line */
        } elseif ($field->getType() === 'submit' || $field->getType() === 'button') {
            $field->attributes(['class' => trim(($field->getAttributes()['class'] ?? '') . ' btn btn-primary')]);
        }

        $html = '<div class="mb-3">';

        if ($field->getType() !== 'submit' && $field->getType() !== 'button') {
            if ($field->getType() === 'checkbox' || $field->getType() === 'radio') {
                $html = '<div class="mb-3 form-check">';
                $html .= $field->render();
                $html .= sprintf('<label class="form-check-label" for="%s">%s</label>', $id, $field->getLabel());
            } else {
                $html .= sprintf('<label class="form-label" for="%s">%s%s</label>', $id, $field->getLabel(), $field->isRequired() ? ' <span class="text-danger">*</span>' : '');
                $html .= $field->render();
            }
        } else {
            $html .= $field->render();
        }

        if ($field->getHelpText()) {
            $html .= sprintf('<div class="form-text">%s</div>', $field->getHelpText());
        }

        if ($hasErrors) {
            foreach ($field->getErrors() as $error) {
                $html .= sprintf('<div class="invalid-feedback">%s</div>', $error);
            }
        }

        $html .= '</div>';

        return $html;
    }
}
