# Form Builder

The Plugs Form Builder allows you to quickly generate complex, accessible HTML forms with full control over themes (Bootstrap, Tailwind, etc.) and validation.

## Basic Usage

You can create a form using the `FormBuilder` class.

```php
use Plugs\Forms\FormBuilder;

$form = (new FormBuilder())
    ->action('/submit')
    ->method('POST')
    ->addField('text', 'name', ['label' => 'Full Name', 'required' => true])
    ->addField('email', 'email', ['label' => 'Email Address'])
    ->addField('password', 'password', ['label' => 'Password'])
    ->addField('submit', 'submit', ['label' => 'Register', 'class' => 'btn-primary']);

echo $form->render();
```

## Form Themes

Plugs supports multiple form themes out of the box. You can set the theme globally or per form instance.

```php
// Use Bootstrap 5 theme
$form->setTheme('bootstrap5');

// Use Tailwind theme
$form->setTheme('tailwind');
```

## Field Types

The Form Builder supports a wide range of field types:

- `text`, `email`, `password`, `number`
- `textarea`
- `select` (with `options` array)
- `checkbox`, `radio`
- `file`
- `date`, `time`, `datetime-local`

### Select Example

```php
$form->addField('select', 'country', [
    'label' => 'Country',
    'options' => [
        'us' => 'United States',
        'ca' => 'Canada',
        'uk' => 'United Kingdom'
    ]
]);
```

## Validation Integration

Form Builder integrates seamlessly with the Plugs validation system. You can pass validation errors to the form, and it will automatically highlight invalid fields.

```php
$form->withErrors($session->get('errors'));
```

## Customizing Fields

You can add custom CSS classes or attributes to any field:

```php
$form->addField('text', 'username', [
    'class' => 'custom-input',
    'attributes' => [
        'placeholder' => 'Enter your username',
        'data-api' => 'check-availability'
    ]
]);
```
