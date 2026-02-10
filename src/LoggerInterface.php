<?php

namespace Dynart\Micro;

interface LoggerInterface extends \Psr\Log\LoggerInterface {
    public function level(): string;
}
