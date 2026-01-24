<?php

declare(strict_types=1);

use Plugs\Forms\Fields\CheckboxField;
use Plugs\Forms\Fields\EmailField;
use Plugs\Forms\Fields\PasswordField;
use Plugs\Forms\Fields\SelectField;
use Plugs\Forms\Fields\SubmitField;
use Plugs\Forms\Fields\TextField;
use Plugs\Forms\FormBuilder;

if (!function_exists('form')) {
    /**
     * Create a new FormBuilder instance.
     *
     * @param string $action
     * @param string $method
     * @return FormBuilder
     */
    function form(string $action = '', string $method = 'POST'): FormBuilder
    {
        return new FormBuilder($action, $method);
    }
}

if (!function_exists('field_text')) {
    function field_text(string $name, ?string $label = null): TextField
    {
        return new TextField($name, $label);
    }
}

if (!function_exists('field_password')) {
    function field_password(string $name, ?string $label = null): PasswordField
    {
        return new PasswordField($name, $label);
    }
}

if (!function_exists('field_email')) {
    function field_email(string $name, ?string $label = null): EmailField
    {
        return new EmailField($name, $label);
    }
}

if (!function_exists('field_select')) {
    function field_select(string $name, array $options = [], ?string $label = null): SelectField
    {
        $field = new SelectField($name, $label);
        $field->options($options);

        return $field;
    }
}

if (!function_exists('field_checkbox')) {
    function field_checkbox(string $name, ?string $label = null): CheckboxField
    {
        return new CheckboxField($name, $label);
    }
}

if (!function_exists('field_submit')) {
    function field_submit(string $label = 'Submit', ?string $name = null): SubmitField
    {
        return new SubmitField($name ?? 'submit', $label);
    }
}
