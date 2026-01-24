<?php

declare(strict_types=1);

namespace Plugs\Forms;

interface ThemeInterface
{
    /**
     * Render a form field.
     *
     * @param Field $field
     * @return string
     */
    public function renderField(Field $field): string;

    /**
     * Render the opening form tag.
     *
     * @param string $action
     * @param string $method
     * @param array $attributes
     * @return string
     */
    public function renderFormOpen(string $action, string $method, array $attributes): string;

    /**
     * Render the closing form tag.
     *
     * @return string
     */
    public function renderFormClose(): string;
}
