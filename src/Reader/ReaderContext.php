<?php

declare(strict_types=1);

namespace MakinaCorpus\SoapGenerator\Reader;

use MakinaCorpus\SoapGenerator\Error\ReaderError;
use MakinaCorpus\SoapGenerator\Error\ResourceCouldNotBeFoundError;
use MakinaCorpus\SoapGenerator\Helper\Context;
use MakinaCorpus\SoapGenerator\Type\AbstractType;
use MakinaCorpus\SoapGenerator\Type\SimpleType;
use MakinaCorpus\SoapGenerator\Type\TypeId;

class ReaderContext implements Context
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

    #[\Override]
    public function logInfo(string|\Stringable $message, array $context = []): void
    {
        $this->global->logInfo($message, $context);
    }

    #[\Override]
    public function logWarn(string|\Stringable $message, array $context = []): void
    {
        $this->global->logWarn($message, $context);
    }

    #[\Override]
    public function logErr(string|\Stringable $message, array $context = []): void
    {
        $this->global->logErr($message, $context);
    }

    /**
     * Create type identifier.
     */
    public function createTypeId(string $name, ?string $namespace = null): TypeId
    {
        if (null === $namespace && ($pos = \strpos($name, ':'))) {
            $namespace = \substr($name, 0, $pos);
            $name = \substr($name, $pos + 1);
        }

        return new TypeId($name, $namespace ? $this->resolveNamespace($namespace) : $this->namespace);
    }

    /**
     * Get full namespace name for the given alias.
     */
    public function resolveTypeId(TypeId $id): TypeId
    {
        $namespace = $this->resolveNamespace($id->namespace);

        return $namespace !== $id->namespace ? new TypeId($id->name, $namespace) : $id;
    }

    /**
     * Set new type.
     */
    public function setType(AbstractType $type): void
    {
        $this->global->setType($type);
    }

    /**
     * Find an existing type.
     */
    public function addScalarType(TypeId $id, ?string $realType = null): SimpleType
    {
        $id = $this->resolveTypeId($id);
        $type = new SimpleType(id: $id, type: $realType ?? $id->name);

        $this->global->setType($type);

        return $type;
    }

    /**
     * Find an existing type.
     */
    public function getType(TypeId $id): AbstractType
    {
        $id = $this->resolveTypeId($id);

        $this->import($id->namespace);

        return $this->global->getType($id);
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
            $this->logErr($e->getMessage());
        }
    }

    /**
     * Create a new context level.
     */
    public function createChildWithNamespace(?string $namespace = null): self
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
     *
     * @internal
     */
    public function createCloneForDocument(?string $directory = null)
    {
        return new ReaderContext(
            directory: $directory,
            global: $this->global,
        );
    }
}
