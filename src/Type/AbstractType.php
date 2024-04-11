<?php

declare(strict_types=1);

namespace MakinaCorpus\XsdGen\Type;

use MakinaCorpus\XsdGen\Error\WriterError;

abstract class AbstractType
{
    private ?string $hash = null;
    private ?string $annotation = null;

    // Computed properties during resolution.
    public bool $resolved = false;
    public bool $inheritanceResolved = false;
    private ?string $phpNamespace = null;
    private ?string $phpLocalName = null;
    private ?string $parentPhpNamespace = null;
    private ?string $parentPhpLocalName = null;

    public function __construct(
        public readonly TypeId $id,
        public ?TypeId $extends = null,
    ) {
    }

    /**
     * Set annotation (PHP doc comment).
     */
    public function setAnnotation(string $annotation): void
    {
        if ($this->annotation) {
            $this->annotation .= "\n\n" . \trim($annotation);
        } else {
            $this->annotation = \trim($annotation);
        }
    }

    /**
     * Get annotation.
     */
    public function getAnnotation(): ?string
    {
        return $this->annotation;
    }

    /**
     * Set inherit type.
     */
    public function setInheritedType(TypeId $extends): void
    {
        $this->extends = $extends;
    }

    /**
     * Get string representation.
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Get string representation.
     */
    public function toString(): string
    {
        return $this->id->toString();
    }

    /**
     * Set target PHP names.
     */
    public function resolve(string $phpLocalName, ?string $phpNamespace): void
    {
        $this->resolved = true;
        $this->phpLocalName = $phpLocalName;
        $this->phpNamespace = $phpNamespace;
    }

    /**
     * Set inheritance information.
     */
    public function resolveInheritance(string $parentPhpLocalName, ?string $parentPhpNamespace): void
    {
        $this->inheritanceResolved = true;
        $this->parentPhpNamespace = $parentPhpNamespace;
        $this->parentPhpLocalName = $parentPhpLocalName;
    }

    /**
     * Get computed PHP local name.
     */
    public function getPhpLocalName(): string
    {
        return $this->dieIfNotResolved() ?? $this->phpLocalName;
    }

    /**
     * Get computed PHP namespace, null for root namespace.
     */
    public function getPhpNamespace(): ?string
    {
        return $this->dieIfNotResolved() ?? $this->phpNamespace;
    }

    /**
     * Get computed PHP local name.
     */
    public function getParentPhpLocalName(): ?string
    {
        return $this->dieIfNotResolved() ?? $this->parentPhpLocalName;
    }

    /**
     * Get computed PHP namespace, null for root namespace.
     */
    public function getParentPhpNamespace(): ?string
    {
        return $this->dieIfNotResolved() ?? $this->parentPhpNamespace;
    }

    public function equals(AbstractType $other): bool
    {
        return $other instanceof static && $this->getHash() === $other->getHash();
    }

    private function getHash(): string
    {
        return $this->hash ??= $this->computeHash();
    }

    protected function reset(): void
    {
        $this->hash = null;
    }

    protected function computeHashAdditions(): array
    {
        return [];
    }

    protected function computeHash(): string
    {
        return \sha1(\implode('#', \array_filter([$this->toString(), $this->extends?->toString(), ...$this->computeHashAdditions()])));
    }

    /**
     * Die if not resolved.
     */
    protected function dieIfNotResolved(): mixed
    {
        if (!$this->resolved) {
            throw new WriterError("Type was not resolved.");
        }
        return null;
    }
}
