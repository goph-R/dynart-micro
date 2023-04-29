<?php

namespace Dynart\Micro;

/**
 * Interface for annotation processing
 * @package Dynart\Micro
 */
interface Annotation {

    const TYPE_CLASS    = 'class';
    const TYPE_PROPERTY = 'property';
    const TYPE_METHOD   = 'method';

    /**
     * The types of the annotation
     *
     * Can be: class, property, method
     *
     * @return array
     */
    public function types(): array;

    /**
     * The name of the annotation
     *
     * For example, if the name is 'route', then
     * the AnnotationProcessor will search for '* @route ' in the comments first.
     *
     * @return string
     */
    public function name(): string;

    /**
     * The regular expression for processing the annotation if the name was found
     *
     * It will be used like this:
     *
     * Asterisk Space @`name` Space `regex` Space Asterisk in non greedy mode
     *
     * <pre>
     * /\&ast;\s\@{$this->name()}\s{$this->regex()}\s\&ast;/U
     * </pre>
     *
     * @return string
     */
    public function regex(): string;

    /**
     * Processes the annotation
     *
     * @param string $type The type of the annotation (can be: class, property, method)
     * @param string $className The name of the class
     * @param mixed $subject The reflected class/property/method (depends on the `$type`)
     * @param string $comment The full comment without new lines
     * @param array $matches The matches from the regex
     */
    public function process(string $type, string $className, $subject, string $comment, array $matches): void;
}