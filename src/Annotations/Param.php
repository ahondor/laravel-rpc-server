<?php

declare(strict_types=1);

namespace Sajya\Server\Annotations;

/**
 * @Annotation
 */
final class Result
{
    /**
     * @var string
     */
    public string $type;

    /**
     * @var string|mixed
     */
    public $example;

    public function toArray()
    {
        return array_filter([
            'type' => $this->type,
            'example' => $this->example ?? null,
        ], fn ($item) => !is_null($item));
    }
}
