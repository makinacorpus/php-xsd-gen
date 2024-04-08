<?php

declare(strict_types=1);

namespace MakinaCorpus\SoapGenerator\Reader;

use MakinaCorpus\SoapGenerator\Error\ReaderError;
use MakinaCorpus\SoapGenerator\GeneratorConfig;
use MakinaCorpus\SoapGenerator\Type\AbstractType;
use MakinaCorpus\SoapGenerator\Type\SimpleType;
use MakinaCorpus\SoapGenerator\Type\TypeId;
use MakinaCorpus\SoapGenerator\Type\TypeStub;
use MakinaCorpus\SoapGenerator\Writer\Writer;

class GlobalContext implements ResourceLocator
{
    private readonly GeneratorConfig $config;
    private readonly TypeRegistry $types;
    private readonly ResourceLocator $resourceLocator;
    private array $schemaLocations = [];
    private array $imported = [];

    public function __construct(
        ?GeneratorConfig $config = null,
        ?TypeRegistry $types = null,
        ?ResourceLocator $resourceLocator = null,
        array $imported = [],
    ) {
        $this->config = $config ?? new GeneratorConfig();
        $this->imported = $imported;
        $this->types = $types ?? new TypeRegistry();
        $this->resourceLocator = $resourceLocator ?? new DefaultResourceLocator();
    }

    /**
     * Create writer.
     */
    public function createWriter(): Writer
    {
        return new Writer($this->types, $this->config);
    }

    /**
     * Get type registry.
     */
    public function getConfig(): GeneratorConfig
    {
        return $this->config;
    }

    /**
     * Get type registry.
     */
    public function getTypeRegistry(): TypeRegistry
    {
        return $this->types;
    }

    /**
     * Set new type.
     */
    public function setType(AbstractType $type): void
    {
        // Prevent scalar types from being inserted more than once.
        if ($type instanceof SimpleType && $this->types->hasType($type->id)) {
            $this->types->getType($type->id);
        } else {
            $this->types->setType($type);
        }
    }

    /**
     * Find an existing type.
     */
    public function findType(TypeId $id): AbstractType
    {
        if ($this->types->hasType($id)) {
            return $this->types->getType($id);
        }

        // Return a type stub.
        return new TypeStub($id);
    }

    #[\Override]
    public function findResource(string $uri, ?string $schemaLocation = null, ?string $directory = null): string
    {
        return $this->resourceLocator->findResource($uri, $this->schemaLocations[$uri] ?? null, $directory);
    }

    /**
     * Register schema location.
     */
    public function registerSchemaLocation(string $uri, string $schemaLocation): void
    {
        $existing = $this->schemaLocations[$uri] ?? null;

        if ($existing && $existing !== $schemaLocation) {
            throw new ReaderError(\sprintf("Schema location for %s is already defined using URI %s, was given %s", $uri, $existing, $schemaLocation));
        }

        $this->schemaLocations[$uri] = $schemaLocation;
    }

    /**
     * Resolve schema location.
     */
    public function resolveSchemaLocation(string $uri): string
    {
        return $this->schemaLocations[$uri] ?? $uri;
    }

    /**
     * Mark file as being imported.
     */
    public function markAsImported(string $namespace): void
    {
        $this->imported[$namespace] = true;
    }

    /**
     * Was file already imported.
     */
    public function wasImported(string $namespace): bool
    {
        return \array_key_exists($namespace, $this->imported);
    }
}
