<?php

declare(strict_types=1);

namespace Plugs\Forms;

use Plugs\Forms\Themes\BootstrapTheme;
use Plugs\View\ErrorMessage;

class FormBuilder
{
    protected array $fields = [];
    protected string $action = '';
    protected string $method = 'POST';
    protected array $attributes = [];
    protected ThemeInterface $theme;
    protected ?ErrorMessage $errors = null;
    protected array $oldInput = [];

    public function __construct(string $action = '', string $method = 'POST')
    {
        $this->action = $action;
        $this->method = strtoupper($method);
        $this->theme = new BootstrapTheme(); // Default theme
    }

    public static function create(string $action = '', string $method = 'POST'): self
    {
        return new self($action, $method);
    }

    public function action(string $action): self
    {
        $this->action = $action;

        return $this;
    }

    public function method(string $method): self
    {
        $this->method = strtoupper($method);

        return $this;
    }

    public function theme(ThemeInterface $theme): self
    {
        $this->theme = $theme;

        return $this;
    }

    public function getTheme(): ThemeInterface
    {
        return $this->theme;
    }

    public function add(Field $field): self
    {
        $this->fields[$field->getName()] = $field;

        // Populate field with old input or errors if available
        if (isset($this->oldInput[$field->getName()])) {
            $field->value($this->oldInput[$field->getName()]);
        }

        if ($this->errors && $this->errors->has($field->getName())) {
            $field->errors($this->errors->get($field->getName()));
        }

        return $this;
    }

    public function withErrors(?ErrorMessage $errors): self
    {
        $this->errors = $errors;

        if ($errors) {
            foreach ($this->fields as $name => $field) {
                if ($errors->has($name)) {
                    $field->errors($errors->get($name));
                }
            }
        }

        return $this;
    }

    public function withOldInput(array $oldInput): self
    {
        $this->oldInput = $oldInput;

        foreach ($this->fields as $name => $field) {
            if (isset($oldInput[$name])) {
                $field->value($oldInput[$name]);
            }
        }

        return $this;
    }

    public function attr(string $key, $value): self
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    public function attributes(array $attributes): self
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        return $this;
    }

    public function render(): string
    {
        $html = $this->theme->renderFormOpen($this->action, $this->method, $this->attributes);

        // Add CSRF if method is not GET
        if ($this->method !== 'GET') {
            $html .= $this->renderCsrf();
        }

        foreach ($this->fields as $field) {
            $html .= $this->theme->renderField($field);
        }

        $html .= $this->theme->renderFormClose();

        return $html;
    }

    protected function renderCsrf(): string
    {
        // Integration with CSRF protection if available
        if (function_exists('csrf_token') || class_exists('\Plugs\Security\Csrf')) {
            $token = '';
            if (class_exists('\Plugs\Security\Csrf') && isset($_SESSION['csrf_token'])) {
                $token = $_SESSION['csrf_token'];
            }

            return sprintf('<input type="hidden" name="csrf_token" value="%s">', $token);
        }

        return '';
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
