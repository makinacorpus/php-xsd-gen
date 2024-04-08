<?php

declare(strict_types=1);

namespace MakinaCorpus\SoapGenerator\Type;

class TypeId
{
    public function __construct(
        public readonly string $name,
        public readonly string $namespace,
    ) {}

    public function toString(): string
    {
        return $this->namespace . ':' . $this->name;
    }

    public function equals(TypeId $other): bool
    {
        return $this->name === $other->name && $this->namespace && $other->namespace;
    }
}
