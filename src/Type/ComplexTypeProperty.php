<?php

declare(strict_types=1);

namespace MakinaCorpus\XsdGen\Type;

use MakinaCorpus\XsdGen\Error\WriterError;

class ComplexTypeProperty
{
    // Computed properties during resolution.
    public bool $resolved = false;
    public bool $inheritanceResolved = false;
    private ?string $phpName = null;
    private ?string $phpType = null;
    private ?string $phpValueType = null;
    private ?string $phpValueTypeNs = null;
    private ?string $phpDocType = null;
    private bool $phpTypeBuiltIn = false;
    private bool $shadowsParent = false;
    private ?string $annotation = null;

    public function __construct(
        public readonly TypeId $parent,
        public readonly string $name,
        public readonly TypeId $type,
        public readonly bool $collection = false,
        public readonly int $minOccur = 0,
        public readonly ?int $maxOccur = null,
        public readonly bool $nullable = false,
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
     * Set target PHP names.
     */
    public function resolve(
        string $phpName,
        string $phpType,
        string $phpValueType,
        ?string $phpValueTypeNs,
        ?string $phpDocType,
        bool $phpTypeBuiltIn,
    ): void {
        $this->resolved = true;
        $this->phpName = $phpName;
        $this->phpType = $phpType;
        $this->phpValueType = $phpValueType;
        $this->phpValueTypeNs = $phpValueTypeNs;
        $this->phpTypeBuiltIn = $phpTypeBuiltIn;
    }

    /**
     * Drop the property from generated code.
     */
    public function unresolve(): void
    {
        $this->resolved = false;
        $this->inheritanceResolved = true;
    }

    /**
     * Set inheritance information.
     */
    public function resolveInheritance(bool $shadowsParent): void
    {
        $this->inheritanceResolved = true;
        $this->shadowsParent = $shadowsParent;
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
        return \sprintf('%s[%s]', $this->parent->toString(), $this->name);
    }

    /**
     * Get PHP class property name.
     */
    public function getPhpName(): string
    {
        return $this->dieIfNotResolved() ?? $this->phpName;
    }

    /**
     * For hydrator/extractor meta information.
     *
     * @internal
     */
    public function getPhpTypeMeta(): string
    {
        $this->dieIfNotResolved();

        if ($this->phpTypeBuiltIn) {
            return $this->phpValueType;
        }
        if ($this->phpValueTypeNs) {
            return $this->phpValueTypeNs . '\\' . $this->phpValueType;
        }
        return $this->phpValueType;
    }

    /**
     * Get PHP value type string, when a collection return the collection
     * type instead of the value type (eg. "array').
     */
    public function getPhpType(): string
    {
        return $this->dieIfNotResolved() ?? $this->phpType;
    }

    /**
     * Is PHP type built-int (ie. can't it be "used").
     */
    public function isPhpTypeBuiltIn(): bool
    {
        return $this->dieIfNotResolved() ?? $this->phpTypeBuiltIn;
    }

    /**
     * Get PHP value types, when an collection return the collection value
     * type and not the collection type itself (eg. "string").
     */
    public function getPhpValueType(): string
    {
        return $this->dieIfNotResolved() ?? $this->phpValueType;
    }

    /**
     * Get the PHP value type namespace. Nulls means root namespace.
     */
    public function getPhpValueTypeNamespace(): ?string
    {
        return $this->dieIfNotResolved() ?? $this->phpValueTypeNs;
    }

    /**
     * Get the PHP value type PHPdoc string (eg "null|string", "string[]").
     */
    public function getPhpDocType(): ?string
    {
        return $this->dieIfNotResolved() ?? $this->phpDocType;
    }

    /**
     * Does this property shadows any property in parenting tree.
     */
    public function shadowsParent(): bool
    {
        return $this->dieIfNotResolved() ?? $this->shadowsParent;
    }

    /**
     * @internal
     * @see ComplexType::computeHashAdditions()
     */
    public function getHashComponents(): array
    {
        return [
            $this->name,
            $this->type->toString(),
            (string)(int) $this->collection,
            (string) $this->minOccur,
            (string) $this->maxOccur,
            (string)(int) $this->nullable,
        ];
    }

    /**
     * Die if not resolved.
     */
    private function dieIfNotResolved(): mixed
    {
        if (!$this->resolved) {
            throw new WriterError("Property was not resolved.");
        }
        return null;
    }
}
