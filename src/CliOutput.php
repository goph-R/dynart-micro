<?php

namespace Dynart\Micro;

class CliOutput {
    
    const COLOR_OFF = "\033[0m";

    const BLACK       = 0;
    const DARK_RED    = 1;
    const DARK_GREEN  = 2;
    const DARK_YELLOW = 3;
    const DARK_BLUE   = 4;
    const DARK_PURPLE = 5;
    const DARK_CYAN   = 6;
    const GRAY        = 7;
    const DARK_GRAY   = 8;
    const RED         = 9;
    const GREEN       = 10;
    const YELLOW      = 11;
    const BLUE        = 12;
    const PURPLE      = 13;
    const CYAN        = 14;
    const WHITE       = 15;

    protected $color = null;
    protected $bgColor = null;

    public function setColor($color, $bgColor = null) {
        if (is_int($color)) {
            $this->color = "\033[" . ($color < 8 ? 30 + $color : 90 + $color - 8) . "m";
        } else {
            $this->color = null;
        }
        if (is_int($bgColor)) {
            $this->bgColor = "\033[".($bgColor < 8 ? 40 + $bgColor : 100 + $bgColor - 8)."m";
        } else {
            $this->bgColor = null;
        }
    }

    public function write(string $text) {
        if ($this->bgColor) {
            echo $this->bgColor;
        }
        if ($this->color) {
            echo $this->color;
        }
        echo $text;
        if ($this->color || $this->bgColor) {
            echo self::COLOR_OFF;
        }
    }

    public function writeLine(string $text) {
        $this->write($text);
        echo "\n";
    }
}