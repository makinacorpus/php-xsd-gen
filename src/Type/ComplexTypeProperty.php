<?php

declare(strict_types=1);

namespace MakinaCorpus\SoapGenerator\Type;

use MakinaCorpus\SoapGenerator\Error\WriterError;

class ComplexTypeProperty
{
    // Computed properties during resolution.
    public bool $resolved = false;
    private ?string $phpName = null;
    private ?string $phpType = null;
    private ?string $phpValueType = null;
    private ?string $phpValueTypeNs = null;
    private ?string $phpDocType = null;
    private bool $phpTypeBuiltIn = false;

    public function __construct(
        public readonly TypeId $parent,
        public readonly string $name,
        public readonly TypeId $type,
        public readonly bool $collection = false,
        public readonly int $minOccur = 0,
        public readonly ?int $maxOccur = null,
        public readonly bool $nullable = false,
        public readonly bool $shadowsParent = false,
    ) {
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
        $this->dieIfNotResolved();

        return $this->phpName;
    }

    /**
     * Get PHP value type string, when a collection return the collection
     * type instead of the value type (eg. "array').
     */
    public function getPhpType(): string
    {
        $this->dieIfNotResolved();

        return $this->phpType;
    }

    /**
     * Is PHP type built-int (ie. can't it be "used").
     */
    public function isPhpTypeBuiltIn(): bool
    {
        $this->dieIfNotResolved();

        return $this->phpTypeBuiltIn;
    }

    /**
     * Get PHP value types, when an collection return the collection value
     * type and not the collection type itself (eg. "string"). 
     */
    public function getPhpValueType(): string
    {
        $this->dieIfNotResolved();

        return $this->phpValueType;
    }

    /**
     * Get the PHP value type namespace. Nulls means root namespace.
     */
    public function getPhpValueTypeNamespace(): ?string
    {
        $this->dieIfNotResolved();

        return $this->phpValueTypeNs;
    }

    /**
     * Get the PHP value type PHPdoc string (eg "null|string", "string[]").
     */
    public function getPhpDocType(): ?string
    {
        $this->dieIfNotResolved();

        return $this->phpDocType;
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
    private function dieIfNotResolved(): void
    {
        if (!$this->resolved) {
            throw new WriterError("Property was not resolved.");
        }
    }
}
