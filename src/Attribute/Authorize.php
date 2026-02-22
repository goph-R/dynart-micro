<?php

namespace Dynart\Micro\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class Authorize {

    public function __construct(public string $permission = '') {}
}
