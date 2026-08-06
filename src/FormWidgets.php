<?php

namespace Dynart\Micro;

/**
 * Which template renders a field of a given type
 *
 * A form field is data - `['type' => 'select', 'options' => [...]]` - and this is the one place
 * that decides what markup that becomes. It used to be an `if/elseif` chain inside a template,
 * which meant a field type could only be added by *replacing the whole chain*: an application
 * pointed `Form::VIEW_INPUT` at its own copy, and then nothing else could add one, because the
 * one override was spent.
 *
 * A registry has room for everybody. The framework registers its own types here like anybody
 * else, so the mechanism the application uses is the mechanism the framework is built on - one
 * nobody exercises is one nobody has tested.
 *
 * The template is fetched with `form`, `name` and `field`, exactly as before.
 */
class FormWidgets {

    /** Where the framework's own widget templates live */
    const VIEW_PREFIX = View::NAMESPACE_MICRO.':widget/';

    /** The types the framework ships. An application adds to these; it does not replace them. */
    const BUILT_IN = ['text', 'password', 'checkbox', 'select', 'textarea', 'file', 'hidden'];

    /** @var array<string, string> field type => view path */
    protected array $widgets = [];

    public function __construct() {
        foreach (self::BUILT_IN as $type) {
            $this->add($type, self::VIEW_PREFIX.$type);
        }
    }

    /**
     * Registers the template a field type renders with
     *
     * Last registration wins, so an application or a plugin can replace a built in type as well
     * as add one - `add('select', 'myapp:widget/fancy-select')` is a legitimate thing to want.
     */
    public function add(string $type, string $view): void {
        $this->widgets[$type] = $view;
    }

    public function has(string $type): bool {
        return isset($this->widgets[$type]);
    }

    /**
     * @return string|null The view path, or null when nothing renders this type
     */
    public function view(string $type): ?string {
        return $this->widgets[$type] ?? null;
    }

    /**
     * @return string[] Every registered type, sorted, for a diagnostic
     */
    public function types(): array {
        $types = array_keys($this->widgets);
        sort($types);
        return $types;
    }
}
