<?php

declare(strict_types=1);

namespace MakinaCorpus\SoapGenerator\Reader;

use MakinaCorpus\SoapGenerator\Error\ReaderError;
use MakinaCorpus\SoapGenerator\Error\ResourceCouldNotBeFoundError;

class ReaderContext
{
    private GlobalContext $global;
    private array $namespaceUriMap = [];

    public function __construct(
        public readonly string $namespace = 'top_level',
        public readonly ?string $directory = null,
        ?GlobalContext $global = null,
        private ?self $parent = null,
    ) {
        $this->global = $global ?? new GlobalContext();
    }

    /**
     * Get global context.
     */
    public function getGlobalContext(): GlobalContext
    {
        return $this->global;
    }

    /**
     * Set new type.
     */
    public function setType(RemoteType $type): void
    {
        $this->global->setType($type);
    }

    /**
     * Find an existing type.
     */
    public function addScalarType(string $name, ?string $namespace, ?string $realType = null): RemoteType
    {
        if (null !== $namespace) {
            $namespace = $this->resolveNamespace($namespace);
        }

        $type = RemoteType::scalar($name, $namespace, $realType);

        $this->global->setType($type);

        return $type;
    }

    /**
     * Find an existing type.
     */
    public function findType(string $name, string $namespace): RemoteType
    {
        $namespace = $this->resolveNamespace($namespace);

        return $this->global->findType($name, $namespace);
    }

    /**
     * Register namespace defined within this file.
     */
    public function registerNamespace(string $alias, string $uri): void
    {
        $existing = $this->namespaceUriMap[$alias] ?? null;

        if ($existing && $existing !== $uri) {
            throw new ReaderError(\sprintf("Namespace alias %s is already defined using URI %s, was given %s", $alias, $existing, $uri));
        }

        $this->namespaceUriMap[$alias] = $uri;
    }

    /**
     * Get full namespace name for the given alias.
     */
    public function resolveNamespace(string $alias): string
    {
        return $this->namespaceUriMap[$alias] ?? $this->parent?->resolveNamespace($alias) ?? $alias;
    }

    /**
     * Create a new context level.
     */
    public function nest(?string $namespace = null): self
    {
        return new self(
            directory: $this->directory,
            global: $this->global,
            namespace: $namespace ?? $this->namespace,
            parent: $this,
        );
    }

    /**
     * Clone for new directory.
     */
    public function clone(?string $directory = null)
    {
        return new ReaderContext(
            directory: $directory,
            global: $this->global,
        );
    }

    /**
     * Import an additionnal file.
     */
    public function import(string $namespace, ?string $schemaLocation = null): void
    {
        $namespace = $this->resolveNamespace($namespace);

        if ($this->global->wasImported($namespace)) {
            return;
        }
        $this->global->markAsImported($namespace);

        if ($schemaLocation) {
            $this->global->registerSchemaLocation($namespace, $schemaLocation);
        }

        try {
            $filename = $this->global->findResource($namespace, null, $this->directory);
            $reader = new XsdReader($filename, $this);
            $reader->findAllTypes();
        } catch (ResourceCouldNotBeFoundError $e) {
            \trigger_error($e->getMessage(), \E_USER_WARNING);
        }
    }
}
