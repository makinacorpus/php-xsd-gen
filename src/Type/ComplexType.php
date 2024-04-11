<?php

declare(strict_types=1);

namespace MakinaCorpus\XsdGen\Type;

use MakinaCorpus\XsdGen\Error\ReaderError;

class ComplexType extends AbstractType
{
    /** @var ComplexTypeProperty[] */
    private array $inheritedProperties = [];

    public function __construct(
        TypeId $id,
        ?TypeId $extends = null,
        public array $properties = [],
        public bool $abstract = false,
    ) {
        parent::__construct($id, $extends);
    }

    /**
     * Set inheritance information.
     *
     * @param ComplexTypeProperty[] $inheritedProperties
     */
    public function resolveInheritedProperties(array $inheritedProperties): void
    {
        $this->inheritedProperties = $inheritedProperties;
    }

    /**
     * Add property.
     */
    public function setProperty(ComplexTypeProperty $property): void
    {
        if (\array_key_exists($property->name, $this->properties)) {
            throw new ReaderError(\sprintf("%s: property already exists and cannot be overriden", $property->toString()));
        }
        $this->properties[$property->name] = $property;
        $this->reset();
    }

    /**
     * Does property exists.
     */
    public function propertyExists(string $name): bool
    {
        try {
            return $this->getProperty($name)->resolved;
        } catch (ReaderError) {
            return false;
        }
    }

    /**
     * Get a single property.
     */
    public function getProperty(string $name): ComplexTypeProperty
    {
        return $this->properties[$name] ?? throw new ReaderError(\sprintf("%s[%s]: property does not exists", $this, $name));
    }

    /**
     * Get inherited properties.
     *
     * @return ComplexTypeProperty[]
     */
    public function getInheritedProperties(): array
    {
        return $this->dieIfNotResolved() ?? $this->inheritedProperties;
    }

    #[\Override]
    protected function computeHashAdditions(): array
    {
        return \array_map(fn (ComplexTypeProperty $prop) => $prop->getHashComponents(), $this->properties);
    }
}
