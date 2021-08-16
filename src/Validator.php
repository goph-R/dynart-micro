<?php

namespace Dynart\Micro;

abstract class Validator {
    
    protected $form;
    protected $message;

    public function setForm(Form $form) {
        $this->form = $form;
    }

    public function message() {
        return $this->message;
    }

    abstract public function validate($value);

}