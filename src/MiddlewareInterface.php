<?php

namespace Dynart\Micro;

interface MiddlewareInterface {
    /**
     * Runs the middleware
     */
    function run(): void;
}
