<?php

declare(strict_types=1);

namespace Plugs\Forms;

use Plugs\View\ComponentAttributes;

abstract class Field
{
    protected string $type;
    protected string $name;
    protected ?string $label = null;
    protected $value = null;
    protected array $attributes = [];
    protected array $options = [];
    protected ?string $helpText = null;
    protected bool $required = false;
    protected array $errors = [];

    public function __construct(string $name, ?string $label = null)
    {
        $this->name = $name;
        $this->label = $label ?? ucwords(str_replace(['_', '-'], ' ', $name));
    }

    public static function make(string $name, ?string $label = null): static
    {
        return new static($name, $label);
    }

    public function type(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function label(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function value($value): self
    {
        $this->value = $value;
        return $this;
    }

    public function getValue()
    {
        return $this->value;
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

    public function getAttributes(): array
    {
        $attrs = $this->attributes;
        if ($this->required) {
            $attrs['required'] = true;
        }
        return $attrs;
    }

    public function renderAttributes(): string
    {
        return (string) new ComponentAttributes($this->getAttributes());
    }

    public function helpText(string $helpText): self
    {
        $this->helpText = $helpText;
        return $this;
    }

    public function getHelpText(): ?string
    {
        return $this->helpText;
    }

    public function required(bool $required = true): self
    {
        $this->required = $required;
        return $this;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function error(string $error): self
    {
        $this->errors[] = $error;
        return $this;
    }

    public function errors(array $errors): self
    {
        $this->errors = array_merge($this->errors, $errors);
        return $this;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function options(array $options): self
    {
        $this->options = $options;
        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    abstract public function render(): string;
}
