<?php

namespace Dynart\Micro;

interface CliOutputInterface {
    public function setColor(?int $color, ?int $bgColor = null): void;
    public function setUseColor(bool $value): void;
    public function write(string $text): void;
    public function writeLine(string $text): void;
}
