<?php

namespace Dynart\Micro;

abstract class Validator {

    /** @var Form */
    protected $form;
    protected $message;

    /**
     * Assigns a form to this validator
     * @param Form $form
     */
    public function setForm(Form $form): void {
        $this->form = $form;
    }

    /**
     * Returns with the assigned form
     * @return Form
     */
    public function form(): Form {
        return $this->form;
    }

    /**
     * The message after validation
     * @return string
     */
    public function message(): string {
        return $this->message;
    }

    abstract public function validate($value);

}