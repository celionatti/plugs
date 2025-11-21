<?php

use Plugs\Security\Validator;

// Simple validation
$validator = new Validator($data, $rules);
$validator->validate();
return $validator->errors(); // Returns ErrorMessage

// With custom messages
$validator = new Validator($data, $rules, $messages, $attributes);
if ($validator->fails()) {
    return $view->render('form', ['errors' => $validator->errors()]);
}

// Mix with manual validation
$errors = $validator->errors();
$errors->add('custom_field', 'Custom error message');