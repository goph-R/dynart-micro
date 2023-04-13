<?php

namespace Dynart\Micro;

/**
 * Interface for annotation processing
 * @package Dynart\Micro
 */
interface Annotation {
    /**
     * The name of the annotation
     *
     * For example: 'route', then the AnnotationProcessor will search for '@route' in the comments
     *
     * @return string
     */
    public function name(): string;

    /**
     * The regular expression for processing the annotation
     *
     * It will be used like this:
     *
     * <pre>
     * /\&ast;\s\@{$this->name()}\s{$this->regex()}\s\&ast;/U
     * </pre>
     *
     * So: Space Asterisk Space @{NAME} Space {REGEX} Space Asterisk in non greedy mode
     *
     * @return string
     */
    public function regex(): string;

    /**
     * Processes the annotation
     *
     * The `$interface` will be the name of the interface (Something::class), the `$method` is the ReflectionMethod
     * from the original method, the `$comment` is the full comment and the `$matches` contains the
     * matches from the regular expression
     *
     * @param string $interface The name of the interface
     * @param \ReflectionMethod $method The reflected method of the interface
     * @param string $comment The full comment
     * @param array $matches The matches from the regex
     */
    public function process(string $interface, \ReflectionMethod $method, string $comment, array $matches): void;
}