<?php

declare(strict_types=1);

namespace MakinaCorpus\XsdGen\Writer;

class UseDeduplicator
{
    private array $statements = [];

    public function addUse(string $namespace, string $name): void
    {
        $value = $namespace . '\\' . $name;

        $this->statements[$value] = null;
    }

    public function getAllUse(): array
    {
        return \array_keys($this->statements);
    }
}
