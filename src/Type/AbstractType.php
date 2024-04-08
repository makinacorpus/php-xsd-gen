<?php

declare(strict_types=1);

namespace MakinaCorpus\SoapGenerator\Type;

use MakinaCorpus\SoapGenerator\Error\WriterError;

abstract class AbstractType
{
    private ?string $hash = null;
    public readonly Source $source;

    // Computed properties during resolution.
    private bool $resolved = false;
    private ?string $phpNamespace = null;
    private ?string $phpLocalName = null;

    public function __construct(
        public readonly TypeId $id,
        public readonly ?TypeId $extends = null,
        public readonly ?string $annotation = null,
        ?Source $source = null,
    ) {
        $this->source = $source ?? Source::unknown();
    }

    /**
     * Set computed PHP target names.
     */
    public function setPhpName(string $phpLocalName, ?string $phpNamespace): void
    {
        $this->resolved = true;
        $this->phpLocalName = $phpLocalName;
        $this->phpNamespace = $phpNamespace;
    }

    /**
     * Get computed PHP local name.
     */
    public function getPhpLocalName(): string
    {
        if (!$this->resolved) {
            throw new WriterError("Type was not resolved.");
        }
        return $this->phpLocalName;
    }

    /**
     * Get computed PHP namespace, null for root namespace.
     */
    public function getPhpNamespace(): ?string
    {
        if (!$this->resolved) {
            throw new WriterError("Type was not resolved.");
        }
        return $this->phpNamespace;
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
        return \sha1(\implode('#', \array_filter([$this->id->toString(), $this->extends?->toString(), ...$this->computeHashAdditions()])));
    }
}
