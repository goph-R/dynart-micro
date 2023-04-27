<?php

namespace Dynart\Micro;

/**
 * Represents a form
 * @package Dynart\Micro
 */
class Form {

    /**
     * Stores the name of the form
     * @var string
     */
    protected $name = 'form';

    /**
     * Is this form uses CSRF?
     * @var bool
     */
    protected $csrf = true;

    /**
     * Holds the fields
     * @var array
     */
    protected $fields = [];

    /**
     * A list of the required field names
     * @var array
     */
    protected $required = [];

    /**
     * The values of the fields in [name => value] format
     * @var array
     */
    protected $values = [];

    /**
     * The error messages of the fields in [name => message] format
     * @var array
     */
    protected $errors = [];

    /**
     * Validators for the fields in [name => [validator]] format
     * @var Validator[][]
     */
    protected $validators = [];

    /** @var Session */
    protected $session;

    /** @var Request */
    protected $request;

    /**
     * Creates the form with given name and if `$csrf` is true, it will add a CSRF value at the end of the `process()`
     * @param Request $request
     * @param Session $session
     * @param string $name
     * @param bool $csrf
     */
    public function __construct(Request $request, Session $session, string $name = 'form', bool $csrf = true) {
        $this->request = $request;
        $this->session = $session;
        $this->name = $name;
        $this->csrf = $csrf;
    }

    /**
     * If the `$csrf` is true, generates a CSRF field and a CSRF value in the session
     */
    public function generateCsrf() {
        if (!$this->csrf) {
            return;
        }
        try {
            $value = bin2hex(random_bytes(128));
        } catch (\Exception $e) {
            throw new \RuntimeException("Couldn't gather sufficient entropy");
        }
        $this->addFields([$this->csrfName() => ['type' => 'hidden']]);
        $this->setValues([$this->csrfName() => $value]);
        $this->session->set($this->csrfSessionName(), $value);
    }

    /**
     * Returns with the CSRF session name
     * @return string
     */
    public function csrfSessionName() {
        return 'form.'.$this->name.'.csrf';
    }

    /**
     * Returns with the CSRF field name
     * @return string
     */
    public function csrfName() {
        return '_csrf';
    }

    /**
     * Returns true if the CSRF session value equals with the CSRF field value
     * @return bool
     */
    public function validateCsrf() {
        return $this->csrf
            ? $this->session->get($this->csrfSessionName()) == $this->value($this->csrfName())
            : true;
    }

    /**
     * Returns the name of this form
     * @return string
     */
    public function name() {
        return $this->name;
    }

    /**
     * Returns the fields of this form in [name => [field_data]] format
     * @return array
     */
    public function fields() {
        return $this->fields;
    }

    /**
     * Adds fields to the form (merges them with the existing ones)
     * @param array $fields The fields in [name => [field_data]] format
     * @param bool $required Is this field required to be filled out?
     */
    public function addFields(array $fields, $required = true) {
        $this->fields = array_merge($this->fields, $fields);
        if ($required) {
            $this->required = array_merge($this->required, array_keys($fields));
        }
    }

    /**
     * Returns wether a field must be filled or not
     * @param string $name
     * @return bool If true the field must be filled out
     */
    public function required(string $name) {
        return in_array($name, $this->required);
    }

    /**
     * Sets a field to be required or not
     * @param string $name The name of the field
     * @param bool $required Is it required?
     */
    public function setRequired(string $name, bool $required) {
        if ($required) {
            if (!in_array($name, $this->required)) {
                $this->required[] = $name;
            }
        } else {
            $this->required = array_diff($this->required, [$name]);
        }
    }

    /**
     * Adds a validator for a field
     * @param string $name The name of the field
     * @param Validator $validator The validator
     */
    public function addValidator(string $name, Validator $validator) {
        if (!isset($this->validators[$name])) {
            $this->validators[$name] = [];
        }
        $this->validators[$name][] = $validator;
        $validator->setForm($this);
    }

    /**
     * Processes a form if the request method is POST, otherwise just adds the CSRF field if `$csrf` is true
     * @return bool Returns true if the form is valid
     */
    public function process(): bool {
        $result = false;
        if ($this->request->httpMethod() == 'POST') {
            $this->bind();
            $result = $this->validate();
        }
        $this->generateCsrf();
        return $result;
    }

    /**
     * Binds the request values to the field values
     *
     * If the form has a name it will use the `form_name[]` value from the request,
     * otherwise: one field name one request parameter name.
     */
    public function bind(): void {
        if ($this->name) {
            $this->values = $this->request->get($this->name, []);
        } else {
            foreach ($this->fields as $name => $field) {
                $this->values[$name] = $this->request->get($name);
            }
        }
    }

    /**
     * Returns a value for a field
     * @param string $name The name of the field
     * @param bool $escape Should the value to be escaped for a HTML attribute?
     * @return mixed|null|string The value of the field
     */
    public function value(string $name, $escape = false) {
        $value = null;
        if (array_key_exists($name, $this->values)) {
            $value = $this->values[$name];
            if ($escape) {
                $value = htmlspecialchars($value, ENT_QUOTES);
            }
        }
        return $value;
    }

    /**
     * Returns with the values for the fields in [name => value] form
     * @return array
     */
    public function values(): array {
        return $this->values;
    }

    /**
     * Sets the values for the fields (merges them with the existing ones)
     * @param array $values
     */
    public function setValues(array $values): void {
        $this->values = array_merge($this->values, $values);
    }

    /**
     * Adds an error to the form itself
     * @param string $error
     */
    public function addError(string $error): void {
        if (!isset($this->errors['_form'])) {
            $this->errors['_form'] = [];
        }
        $this->errors['_form'][] = $error;
    }

    /**
     * Runs the validators per field if the field is required and sets the errors
     *
     * If one validator fails for a field the other validators will NOT run for that field.
     *
     * @return bool The form validation was successful?
     */
    public function validate(): bool {
        if (!$this->validateCsrf()) {
            $this->addError('CSRF token is invalid.');
        }
        foreach (array_keys($this->fields) as $name) {
            if ($this->required($name) && !$this->value($name)) {
                $this->errors[$name] = 'Required.'; // TODO: Translation
            }
        }
        foreach ($this->validators as $name => $validators) {
            if (isset($this->errors[$name])) {
                continue;
            }
            if (!$this->value($name) && !$this->required($name)) {
                continue;
            }
            foreach ($validators as $validator) {
                if (!$validator->validate($this->value($name))) {
                    $this->errors[$name] = $validator->message();
                    break;
                }
            }
        }
        return empty($this->errors);
    }

    /**
     * Returns an error message for a field
     * @param string $name The field name
     * @return string|null The error message or null
     */
    public function error(string $name) {
        return isset($this->errors[$name]) ? $this->errors[$name] : null;
    }

}