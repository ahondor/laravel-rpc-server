<?php

declare(strict_types=1);

namespace Sajya\Server\Annotations;

/**
 * @Annotation
 */
final class Param
{
    /**
     * @var string
     */
    public string $name;

    /**
     * @var string
     */
    public string $type;

    /**
     * @var bool
     */
    public bool $optional = false;

    /**
     * @var mixed
     */
    public $default = null;

    public function toArray()
    {
        return array_filter([
            'name'     => $this->name,
            'type'     => $this->type,
            'optional' => $this->optional,
            'default'  => $this->default ?? null,
        ], fn ($item) => !is_null($item));
    }
}
