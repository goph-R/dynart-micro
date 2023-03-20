<?php

namespace Dynart\Micro;

class Form {

    protected $name = 'form';
    protected $csrf = true;
    protected $fields = [];
    protected $required = [];
    protected $values = [];
    protected $errors = [];

    /** @var Validator[][] */
    protected $validators = [];

    /** @var Session */
    protected $session;

    /** @var Request */
    protected $request;

    public function __construct(Request $request, Session $session, string $name = 'form', bool $csrf = true) {
        $this->request = $request;
        $this->session = $session;
        $this->name = $name;
        $this->csrf = $csrf;
    }

    public function generateCsrf() {
        if (!$this->csrf) {
            return;
        }
        try {
            $value = bin2hex(random_bytes(16));
        } catch (\Exception $e) {
            throw new \RuntimeException("Couldn't gather sufficient entropy");
        }
        $this->addFields(['_csrf' => ['type' => 'hidden']]);
        $this->setValues(['_csrf' => $value]);
        $this->session->set('form.'.$this->name.'.csrf', $value);
    }

    public function validateCsrf() {
        return $this->csrf
            ? $this->session->get('form.'.$this->name.'.csrf') == $this->value('_csrf')
            : true;
    }

    public function name() {
        return $this->name;
    }

    public function fields() {
        return $this->fields;
    }

    public function addFields(array $fields, $required=true) {
        $this->fields = array_merge($this->fields, $fields);
        if ($required) {
            $this->required = array_merge($this->required, array_keys($fields));
        }
    }

    public function required(string $name) {
        return in_array($name, $this->required);
    }

    public function setRequired(string $name, bool $required) {
        if ($required) {
            if (!in_array($name, $this->required)) {
                $this->required[] = $name;
            }
        } else {
            $this->required = array_diff($this->required, [$name]);
        }
    }

    public function addValidator(string $name, Validator $validator) {
        if (!isset($this->validators[$name])) {
            $this->validators[$name] = [];
        }
        $this->validators[$name][] = $validator;
        $validator->setForm($this);
    }

    public function process() {
        $result = false;
        if ($this->request->method() == 'POST') {
            $this->bind();
            $result = $this->validate();
        }
        $this->generateCsrf();
        return $result;
    }

    public function bind() {
        if ($this->name) {
            $this->values = $this->request->get($this->name, []);
        } else {
            foreach ($this->fields as $name => $field) {
                $this->values[$name] = $this->request->get($name);
            }
        }
    }

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

    public function values() {
        return $this->values;
    }

    public function setValues(array $values) {
        $this->values = array_merge($this->values, $values);
    }

    public function addError(string $error) {
        if (!isset($this->errors['_form'])) {
            $this->errors['_form'] = [];
        }
        $this->errors['_form'][] = $error;
    }

    public function validate() {
        if (!$this->validateCsrf()) {
            $this->addError('CSRF token is invalid.');
        }
        foreach (array_keys($this->fields) as $name) {
            if ($this->required($name) && !$this->value($name)) {
                $this->errors[$name] = 'Required.';
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

    public function error(string $name) {
        return isset($this->errors[$name]) ? $this->errors[$name] : null;
    }

}