<?php

namespace Dynart\Micro;

abstract class Validator {

    protected Form $form;
    protected string $message = '';

    /**
     * Assigns a form to this validator
     */
    public function setForm(Form $form): void {
        $this->form = $form;
    }

    /**
     * Returns with the assigned form
     */
    public function form(): Form {
        return $this->form;
    }

    /**
     * The message after validation
     */
    public function message(): string {
        return $this->message;
    }

    abstract public function validate(mixed $value): bool;

}
