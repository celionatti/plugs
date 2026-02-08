<?php

declare(strict_types=1);

namespace Plugs\Forms\Themes;

use Plugs\Forms\Field;
use Plugs\Forms\ThemeInterface;

class TailwindTheme implements ThemeInterface
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
        $errorClass = $hasErrors ? ' border-red-500 text-red-600 focus:ring-red-500' : ' border-gray-300 focus:ring-blue-500';

        // Add Tailwind classes
        if (!in_array($field->getType(), ['checkbox', 'radio', 'submit', 'button'])) {
            $field->attributes(['class' => trim(($field->getAttributes()['class'] ?? '') . ' w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2' . $errorClass)]);
        } elseif ($field->getType() === 'checkbox' || $field->getType() === 'radio') {
            $field->attributes(['class' => trim(($field->getAttributes()['class'] ?? '') . ' h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500')]);
            /** @phpstan-ignore-next-line */
        } elseif ($field->getType() === 'submit' || $field->getType() === 'button') {
            $field->attributes(['class' => trim(($field->getAttributes()['class'] ?? '') . ' px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2')]);
        }

        $html = '<div class="mb-4">';

        if ($field->getType() !== 'submit' && $field->getType() !== 'button') {
            if ($field->getType() === 'checkbox' || $field->getType() === 'radio') {
                $html .= '<div class="flex items-center">';
                $html .= $field->render();
                $html .= sprintf('<label class="ml-2 block text-sm text-gray-900" for="%s">%s</label>', $id, $field->getLabel());
                $html .= '</div>';
            } else {
                $html .= sprintf('<label class="block text-sm font-medium text-gray-700 mb-1" for="%s">%s%s</label>', $id, $field->getLabel(), $field->isRequired() ? ' <span class="text-red-500">*</span>' : '');
                $html .= $field->render();
            }
        } else {
            $html .= $field->render();
        }

        if ($field->getHelpText()) {
            $html .= sprintf('<p class="mt-1 text-sm text-gray-500">%s</p>', $field->getHelpText());
        }

        if ($hasErrors) {
            foreach ($field->getErrors() as $error) {
                $html .= sprintf('<p class="mt-1 text-sm text-red-600">%s</p>', $error);
            }
        }

        $html .= '</div>';

        return $html;
    }
}
