<?php

declare(strict_types=1);

namespace MakinaCorpus\SoapGenerator\Reader;

use MakinaCorpus\SoapGenerator\Error\ReaderError;
use MakinaCorpus\SoapGenerator\GeneratorConfig;

class TypeRegistry
{
    private GeneratorConfig $config;
    /** @var RemoteType[] */
    private array $types = [];

    public function __construct(?GeneratorConfig $config = null)
    {
        $this->config = $config ?? new GeneratorConfig();
    }

    public function setType(RemoteType $type): void
    {
        $key = $this->getKey($type->name, $type->namespace);

        if ($existing = ($this->types[$key] ?? null)) {
            if ($this->config->errorWhenTypeOverride) {
                throw new ReaderError(\sprintf("%s:%s: cannot override type", $type->namespace, $type->name));
            }
            if (!$existing->equals($type)) {
                throw new ReaderError(\sprintf("%s:%s: type override diverges", $type->namespace, $type->name));
            }
            return;
        }

        $this->types[$key] = $type;
    }

    public function hasType(string $name, string $namespace): bool
    {
        return \array_key_exists($this->getKey($name, $namespace), $this->types);
    }

    public function getType(string $name, string $namespace): RemoteType
    {
        return $this->types[$this->getKey($name, $namespace)] ?? new \Exception("type does not exists");
    }

    public function getAllTypes(): array
    {
        return $this->types;
    }

    private function getKey(string $name, string $namespace): string
    {
        return $namespace . ':' . $name;
    }
}
