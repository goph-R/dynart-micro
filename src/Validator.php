<?php

namespace Dynart\Micro;

abstract class Validator {

    protected Form $form;
    protected string $message = '';

    public function setForm(Form $form): void {
        $this->form = $form;
    }

    public function form(): Form {
        return $this->form;
    }

    public function message(): string {
        return $this->message;
    }

    abstract public function validate(mixed $value): bool;

}
