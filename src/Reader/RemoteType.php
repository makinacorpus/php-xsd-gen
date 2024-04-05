<?php

declare(strict_types=1);

namespace MakinaCorpus\SoapGenerator\Reader;

use MakinaCorpus\SoapGenerator\Error\ReaderError;

class RemoteType
{
    private ?string $hash = null;

    public function __construct(
        public readonly string $name,
        public readonly string $namespace,
        public readonly ?string $extends = null,
        public array $properties = [],
        public readonly ?string $scalar = null,
    ) {}

    public static function stub(string $name, string $namespace): self
    {
        return new self(
            name: $name,
            namespace: $namespace,
        );
    }

    public static function scalar(string $name, string $namespace = 'xsd', ?string $type = null): self
    {
        return new self(
            name: $name,
            namespace: $namespace,
            scalar: $type ?? $name,
        );
    }

    public function toString(): string
    {
        return $this->namespace . ':' . $this->name;
    }

    public function property(Property $property): void
    {
        if (\array_key_exists($property->name, $this->properties)) {
            throw new ReaderError(\sprintf("Property override: %s:%s[%s] already exists", $this->namespace, $this->name, $property->name));
        }
        $this->properties[$property->name] = $property;

        $this->hash = null;
    }

    public function equals(RemoteType $other): bool
    {
        return $this->getHash() === $other->getHash();
    }

    private function getHash(): string
    {
        return $this->hash ??= $this->computeHash();
    }

    private function computeHash(): string
    {
        $garbage = $this->namespace . '#' . $this->name . '#' . $this->extends . '#' . $this->scalar . '#';

        foreach ($this->properties as $property) {
            \assert($property instanceof Property);

            $garbage .= $property->name . '#' . $property->type . '#' . $property->collection . '#' . $property->minOccur . '#' . $property->maxOccur;
        }

        return \sha1($garbage);
    }
}
