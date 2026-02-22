<?php

namespace Dynart\Micro;

class AuthorizationException extends MicroException {

    public function __construct(int $code) {
        parent::__construct('', $code);
    }
}
