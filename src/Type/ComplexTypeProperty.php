<?php

declare(strict_types=1);

namespace MakinaCorpus\SoapGenerator\Type;

use MakinaCorpus\SoapGenerator\Error\WriterError;

class ComplexTypeProperty
{
    // Computed properties during resolution.
    private bool $resolved = false;
    private ?string $phpName = null;

    public function __construct(
        public readonly string $name,
        public readonly TypeId $type,
        public readonly bool $collection = false,
        public readonly int $minOccur = 0,
        public readonly ?int $maxOccur = null,
        public readonly bool $nullable = false,
        public readonly bool $shadowsParent = false,
    ) {}

    /**
     * Set computed PHP target names.
     */
    public function setPhpName(string $phpName): void
    {
        $this->resolved = true;
        $this->phpName = $phpName;
    }

    /**
     * Get computed PHP name.
     */
    public function getPhpName(): string
    {
        if (!$this->resolved) {
            throw new WriterError("Type was not resolved.");
        }
        return $this->phpName;
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
}
