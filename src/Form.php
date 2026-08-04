<?php

namespace Dynart\Micro;

/**
 * Represents a form
 * @package Dynart\Micro
 */
class Form {

    /** The translation namespace used for the built in messages */
    const TRANSLATION_NAMESPACE = 'micro';

    /** The translation id of the "required field" message */
    const MESSAGE_REQUIRED = 'form_required';

    /** The translation id of the "invalid CSRF token" message */
    const MESSAGE_CSRF_INVALID = 'form_csrf_invalid';

    /** The message for a required field if there is no translation for it */
    const DEFAULT_MESSAGE_REQUIRED = 'Required.';

    /** The message for an invalid CSRF token if there is no translation for it */
    const DEFAULT_MESSAGE_CSRF_INVALID = 'CSRF token is invalid.';

    /**
     * The partials the form renders itself with
     *
     * Namespaced to the framework's own views, so they resolve wherever the package sits. A
     * theme overrides any of them by putting its own copy under `<theme>/micro/`.
     */
    const VIEW_ERRORS = View::NAMESPACE_MICRO.':form-errors';
    const VIEW_FIELD = View::NAMESPACE_MICRO.':form-field';
    const VIEW_INPUT = View::NAMESPACE_MICRO.':form-input';

    /**
     * Stores the name of the form
     */
    protected string $name = 'form';

    /**
     * Is this form uses CSRF?
     */
    protected bool $csrf = true;

    /**
     * Holds the fields
     */
    protected array $fields = [];

    /**
     * A list of the required field names
     */
    protected array $required = [];

    /**
     * The values of the fields in [name => value] format
     */
    protected array $values = [];

    /**
     * The error messages of the fields in [name => message] format
     */
    protected array $errors = [];

    /**
     * The error messages of the form itself as a list of strings
     */
    protected array $formErrors = [];

    /**
     * Validators for the fields in [name => [validator1, validator2]] format
     */
    protected array $validators = [];

    protected SessionInterface $session;

    protected RequestInterface $request;

    /**
     * Used for the built in messages, English defaults are used when it is null
     */
    protected ?TranslationInterface $translation = null;

    /**
     * Creates the form with given name and `$csrf` value
     *
     * @param RequestInterface $request The HTTP request
     * @param SessionInterface $session The session used for the CSRF check
     * @param string $name The name of the form, can be an empty string (usually for filter forms)
     * @param bool $csrf Is the form should use a CSRF field and validate it on `process()`?
     */
    public function __construct(RequestInterface $request, SessionInterface $session, string $name = 'form', bool $csrf = true) {
        $this->request = $request;
        $this->session = $session;
        $this->name = $name;
        $this->csrf = $csrf;
    }

    /**
     * Sets the translation used for the built in messages
     *
     * When it is not set, the English defaults are used. The translation ids are looked up in the
     * `micro` namespace, so the application has to call `Translation::add('micro', $folder)` for
     * the messages to be translated.
     */
    public function setTranslation(?TranslationInterface $translation): void {
        $this->translation = $translation;
    }

    /**
     * Returns with a translated built in message or with the given default
     *
     * `Translation::get()` returns with `#namespace:id#` when the text can not be found,
     * in that case the `$default` is returned.
     */
    protected function message(string $id, string $default): string {
        if ($this->translation === null) {
            return $default;
        }
        $fullId = self::TRANSLATION_NAMESPACE.':'.$id;
        $result = $this->translation->get($fullId);
        return $result === '#'.$fullId.'#' ? $default : $result;
    }

    /**
     * If the `$csrf` is true, generates a CSRF field and a CSRF value in the session
     * @throws MicroException If it couldn't gather sufficient entropy for random_bytes
     */
    public function generateCsrf(): void {
        if (!$this->csrf) {
            return;
        }
        try {
            $value = bin2hex(random_bytes(128));
        } catch (\Exception $e) {
            throw new MicroException("Couldn't gather sufficient entropy");
        }
        $this->addFields([$this->csrfName() => ['type' => 'hidden']]);
        $this->addValues([$this->csrfName() => $value]);
        $this->session->set($this->csrfSessionName(), $value);
    }

    /**
     * Returns with the CSRF session name
     */
    public function csrfSessionName(): string {
        return 'form.'.$this->name.'.csrf';
    }

    /**
     * Returns with the CSRF field name
     */
    public function csrfName(): string {
        return '_csrf';
    }

    /**
     * Returns true if the CSRF session value equals with the CSRF field value
     */
    public function validateCsrf(): bool {
        return !$this->csrf || $this->session->get($this->csrfSessionName()) == $this->value($this->csrfName());
    }

    /**
     * Returns the name of this form
     */
    public function name(): string {
        return $this->name;
    }

    /**
     * Sets the name of this form
     *
     * The name is part of the CSRF session name and the HTML input names, so it should be set
     * before `generateCsrf()` or any rendering happens.
     */
    public function setName(string $name): void {
        $this->name = $name;
    }

    /**
     * Is this form using a CSRF field?
     */
    public function csrf(): bool {
        return $this->csrf;
    }

    /**
     * Sets whether this form should use a CSRF field
     */
    public function setCsrf(bool $csrf): void {
        $this->csrf = $csrf;
    }

    /**
     * Returns with the HTML input name for a field
     *
     * When the form has a name, the fields are grouped into an array, so PHP parses them into
     * `$_REQUEST[form_name][field_name]`, which is what `bind()` reads back.
     */
    public function inputName(string $name): string {
        return $this->name ? $this->name.'['.$name.']' : $name;
    }

    /**
     * Returns with the HTML id for a field
     */
    public function inputId(string $name): string {
        return $this->name ? $this->name.'_'.$name : $name;
    }

    /**
     * Returns the fields of this form in [name => [field_data]] format
     */
    public function fields(): array {
        return $this->fields;
    }

    /**
     * Adds fields to the form (merges them with the existing ones)
     *
     * A field can override the `$required` parameter with a `required` key in its own data, so
     * mixed batches can be added in one call:
     *
     * <pre>
     * $form->addFields([
     *     'email' => ['type' => 'text'],
     *     'notes' => ['type' => 'text', 'required' => false]
     * ]);
     * </pre>
     *
     * @param array $fields The fields in [name => [field_data]] format
     * @param bool $required The default for the fields that don't declare it themselves
     */
    public function addFields(array $fields, bool $required = true): void {
        foreach ($fields as $name => $field) {
            $this->setRequired($name, array_key_exists('required', $field) ? (bool)$field['required'] : $required);
        }
        $this->fields = array_merge($this->fields, $fields);
    }

    /**
     * Returns if a field must be filled or not
     * @param string $name
     * @return bool
     */
    public function required(string $name): bool {
        return in_array($name, $this->required);
    }

    /**
     * Sets a field to be required or not
     *
     * @param string $name The name of the field
     * @param bool $required Is it required?
     */
    public function setRequired(string $name, bool $required): void {
        if ($required) {
            if (!in_array($name, $this->required)) {
                $this->required[] = $name;
            }
        } else {
            $this->required = array_values(array_diff($this->required, [$name]));
        }
    }

    /**
     * Adds a validator for a field
     *
     * @param string $name The name of the field
     * @param AbstractValidator $validator The validator
     */
    public function addValidator(string $name, AbstractValidator $validator): void {
        if (!isset($this->validators[$name])) {
            $this->validators[$name] = [];
        }
        $this->validators[$name][] = $validator;
        $validator->setForm($this);
    }

    /**
     * Processes a form if the request method is `$httpMethod`, adds the CSRF field if `$csrf` is true
     *
     * @param string $httpMethod The required HTTP method
     * @return bool Returns true if the form is valid
     */
    public function process(string $httpMethod = 'POST'): bool {
        $result = false;
        if ($this->request->httpMethod() == $httpMethod) {
            $this->bind();
            $this->beforeValidate();
            $result = $this->validate();
            $this->afterValidate($result);
        }
        $this->generateCsrf();
        return $result;
    }

    /**
     * Runs after `bind()` and before `validate()` in `process()`
     *
     * Subclasses can use it for emitting an event or for normalizing the bound values.
     */
    protected function beforeValidate(): void {}

    /**
     * Runs after `validate()` in `process()`
     *
     * Subclasses can use it for emitting an event. Adding an error here does not change the
     * `$valid` value that `process()` returns, use `validate()` for that.
     *
     * @param bool $valid The result of the validation
     */
    protected function afterValidate(bool $valid): void {}

    /**
     * Binds the request values to the field values
     *
     * If the form has a name it will use the `form_name[]` value from the request,
     * otherwise: one field name one request parameter name.
     */
    public function bind(): void {
        if ($this->name) {
            $values = $this->request->get($this->name, []);
            $this->values = is_array($values) ? $values : [];
        } else {
            foreach ($this->fields as $name => $field) {
                $this->values[$name] = $this->request->get($name);
            }
        }
    }

    /**
     * Returns a value for a field
     * @param string $name The name of the field
     * @param bool $escape Should the value to be escaped for an HTML attribute?
     * @return null|string The value of the field
     */
    public function value(string $name, bool $escape = false): ?string {
        $value = null;
        if (array_key_exists($name, $this->values)) {
            $value = $this->values[$name];
            if ($escape) {
                $value = htmlspecialchars((string)$value, ENT_QUOTES);
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
     * Sets the values for the fields (clears the previous ones)
     * @param array $values
     */
    public function setValues(array $values): void {
        $this->values = $values;
    }

    /**
     * Adds the values for the fields (merges them with the existing ones)
     * @param array $values
     */
    public function addValues(array $values): void {
        $this->values = array_merge($this->values, $values);
    }

    /**
     * Adds an error to the form itself
     * @param string $error
     */
    public function addError(string $error): void {
        $this->formErrors[] = $error;
    }

    /**
     * Adds an error for a field, keeps the first error if the field already has one
     *
     * @param string $name The field name
     * @param string $error The error message
     */
    public function addFieldError(string $name, string $error): void {
        if (!isset($this->errors[$name])) {
            $this->errors[$name] = $error;
        }
    }

    /**
     * Runs the validators per field if the field is required or has value
     *
     * If one validator fails for a field the other validators will NOT run for that field.
     *
     * @return bool The form validation was successful?
     */
    public function validate(): bool {
        $this->validateCsrfValue();
        $this->validateRequiredFields();
        $this->runValidators();
        return !$this->hasErrors();
    }

    /**
     * Adds a form error when the CSRF token is invalid
     */
    protected function validateCsrfValue(): void {
        if (!$this->validateCsrf()) {
            $this->addError($this->message(self::MESSAGE_CSRF_INVALID, self::DEFAULT_MESSAGE_CSRF_INVALID));
        }
    }

    /**
     * Adds a field error for every empty required field
     */
    protected function validateRequiredFields(): void {
        foreach (array_keys($this->fields) as $name) {
            if ($this->required($name) && !$this->value($name)) {
                $this->addFieldError($name, $this->message(self::MESSAGE_REQUIRED, self::DEFAULT_MESSAGE_REQUIRED));
            }
        }
    }

    /**
     * Runs the field validators, skips the fields that already have an error
     */
    protected function runValidators(): void {
        foreach ($this->validators as $name => $validators) {
            if (isset($this->errors[$name])) {
                continue;
            }
            if (!$this->value($name) && !$this->required($name)) {
                continue;
            }
            foreach ($validators as $validator) {
                if (!$validator->validate($this->value($name))) {
                    $this->addFieldError($name, $validator->message());
                    break;
                }
            }
        }
    }

    /**
     * Returns an error message for a field
     *
     * @param string $name The field name
     * @return string|null The error message or null
     */
    public function error(string $name): ?string {
        return $this->errors[$name] ?? null;
    }

    /**
     * Returns with the field errors in [name => message] format
     */
    public function errors(): array {
        return $this->errors;
    }

    /**
     * Returns with the errors of the form itself as a list of strings
     */
    public function formErrors(): array {
        return $this->formErrors;
    }

    /**
     * Returns true if the form or any of its fields has an error
     */
    public function hasErrors(): bool {
        return !empty($this->errors) || !empty($this->formErrors);
    }

    public function fetch(): string {
        $html = $this->fetchErrors();
        foreach ($this->fields() as $name => $field) {
            $html .= $this->fetchField($name, $field);
        }
        return $html;
    }

    public function fetchErrors(): string {
        /** @var ViewInterface $view */
        $view = Micro::get(ViewInterface::class);
        return $view->fetch(self::VIEW_ERRORS, [
            'form' => $this
        ]);
    }

    public function fetchField(string $name, array $field): string {
        /** @var ViewInterface $view */
        $view = Micro::get(ViewInterface::class);
        return $view->fetch(self::VIEW_FIELD, [
            'form' => $this,
            'name' => $name,
            'field' => $field
        ]);
    }

    public function fetchInput(string $name, array $field): string {
        /** @var ViewInterface $view */
        $view = Micro::get(ViewInterface::class);
        return $view->fetch(self::VIEW_INPUT, [
            'form' => $this,
            'name' => $name,
            'field' => $field
        ]);
    }

    public function idByNameAndField(string $name, array $field): string {
        return $field['id'] ?? $this->inputId($name);
    }

}
