<?php

declare(strict_types=1);

namespace MakinaCorpus\SoapGenerator\Reader;

class Property
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly bool $collection = false,
        public readonly int $minOccur = 0,
        public readonly ?int $maxOccur = null,
        public readonly bool $nullable = false,
    ) {}
}
