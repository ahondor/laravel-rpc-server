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
    public $value;
}
