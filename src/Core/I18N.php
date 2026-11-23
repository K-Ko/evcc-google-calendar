<?php

namespace Core;

final class I18N
{
    /**
     * Class constructor
     *
     * @param array $translations
     */
    public function __construct(array $translations)
    {
        $this->translations = array_change_key_case($translations);
    }

    /**
     * Getter
     *
     * @param  string $name
     * @return string
     */
    public function __get(string $name)
    {
        return $this->translations[strtolower($name)] ?? $name . '#';
    }

    // ----------------------------------------------------------------------
    // PRIVATE
    // ----------------------------------------------------------------------

    /** @var array */
    private $translations = [];
}
