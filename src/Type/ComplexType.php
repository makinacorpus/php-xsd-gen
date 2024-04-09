<?php

declare(strict_types=1);

namespace MakinaCorpus\SoapGenerator\Type;

use MakinaCorpus\SoapGenerator\Error\ReaderError;

class ComplexType extends AbstractType
{
    public function __construct(
        TypeId $id,
        ?TypeId $extends = null,
        ?string $annotation = null,
        ?Source $source = null,
        public array $properties = [],
        public bool $abstract = false,
    ) {
        parent::__construct($id, $extends, $annotation, $source);
    }

    public function property(ComplexTypeProperty $property): void
    {
        if (\array_key_exists($property->name, $this->properties)) {
            throw new ReaderError(\sprintf("%s: property already exists and cannot be overriden", $property->toString()));
        }
        $this->properties[$property->name] = $property;
        $this->reset();
    }

    public function propertyExists(string $name): bool
    {
        return \array_key_exists($name, $this->properties);
    }

    protected function computeHashAdditions(): array
    {
        return \array_map($this->properties, fn (ComplexTypeProperty $prop) => $prop->getHashComponents());
    }
}
