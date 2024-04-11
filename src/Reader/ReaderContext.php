<?php

declare(strict_types=1);

namespace MakinaCorpus\XsdGen\Reader;

use MakinaCorpus\XsdGen\Error\ReaderError;
use MakinaCorpus\XsdGen\Error\ResourceCouldNotBeFoundError;
use MakinaCorpus\XsdGen\Helper\Context;
use MakinaCorpus\XsdGen\Type\AbstractType;
use MakinaCorpus\XsdGen\Type\SimpleType;
use MakinaCorpus\XsdGen\Type\TypeId;

class ReaderContext implements Context
{
    private readonly GlobalContext $global;
    private array $namespaceUriMap = [];
    private array $expectations = [];

    public function __construct(
        public readonly string $namespace = 'top_level',
        public readonly ?self $parent = null,
        public readonly ?string $filename = null,
        ?GlobalContext $global = null,
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
     * Expect child element.
     *
     * @param (callable(ReaderContext,\DOMElement):void) $callback
     */
    public function expect(string $elementName, callable $callback, mixed ...$args): static
    {
        $this->expectations[] = [$elementName, $callback, $args];

        return $this;
    }

    /**
     * @internal
     *  For AbstractReader usage only.
     */
    public function getAllExpectations(): array
    {
        return $this->expectations;
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
            $filename = $this->global->findResource($namespace, null, \dirname($this->filename));
            $reader = new XsdReader($filename, $this);
            $reader->execute();
        } catch (ResourceCouldNotBeFoundError $e) {
            $this->logErr($e->getMessage());
        }
    }

    /**
     * Create a new context level.
     */
    public function createChild(?string $namespace = null): self
    {
        return new self(
            filename: $this->filename,
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
    public function createCloneForDocument(?string $filename = null)
    {
        return new ReaderContext(
            filename: $filename,
            global: $this->global,
        );
    }
}
