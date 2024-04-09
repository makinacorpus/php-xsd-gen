<?php

declare(strict_types=1);

namespace MakinaCorpus\SoapGenerator\Reader;

use MakinaCorpus\SoapGenerator\Error\ReaderError;
use MakinaCorpus\SoapGenerator\Error\TypeDoesNotExistError;
use MakinaCorpus\SoapGenerator\GeneratorConfig;
use MakinaCorpus\SoapGenerator\Type\AbstractType;
use MakinaCorpus\SoapGenerator\Type\TypeId;

class TypeRegistry
{
    private GeneratorConfig $config;

    /** @var AbstractType[] */
    private array $types = [];

    public function __construct(?GeneratorConfig $config = null)
    {
        $this->config = $config ?? new GeneratorConfig();
    }

    public function setType(AbstractType $type): void
    {
        $key = $type->toString();

        if ($existing = ($this->types[$key] ?? null)) {
            if ($this->config->errorWhenTypeOverride) {
                throw new ReaderError(\sprintf("%s: cannot override type", $type->toString()));
            }
            if (!$existing->equals($type)) {
                throw new ReaderError(\sprintf("%s: type override diverges", $type->toString()));
            }
            return;
        }

        $this->types[$key] = $type;
    }

    public function hasType(TypeId $id): bool
    {
        return \array_key_exists($id->toString(), $this->types);
    }

    public function getType(TypeId $id): AbstractType
    {
        return $this->types[$id->toString()] ?? throw new TypeDoesNotExistError(\sprintf("%s: type does not exists", $id->toString()));
    }

    public function getAllTypes(): array
    {
        return $this->types;
    }
}
