<?php

declare(strict_types=1);

namespace MakinaCorpus\XsdGen\Writer;

use MakinaCorpus\XsdGen\GeneratorConfig;
use MakinaCorpus\XsdGen\Helper\Context;
use MakinaCorpus\XsdGen\Helper\ContextTrait;
use MakinaCorpus\XsdGen\Reader\TypeRegistry;
use MakinaCorpus\XsdGen\Type\AbstractType;
use MakinaCorpus\XsdGen\Type\SimpleType;
use MakinaCorpus\XsdGen\Type\TypeId;
use Psr\Log\LoggerInterface;

class WriterContext implements Context
{
    use ContextTrait;

    public readonly GeneratorConfig $config;
    private readonly TypeRegistry $types;

    public function __construct(
        ?TypeRegistry $types,
        ?GeneratorConfig $config = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->config = $config ?? new GeneratorConfig();
        $this->types = $types ?? new TypeRegistry();
        $this->logger = $logger ?? $this->config->logger;
    }

    /**
     * Get type registry.
     */
    public function getTypeRegistry(): TypeRegistry
    {
        return $this->types;
    }

    /**
     * Find an existing type.
     */
    public function getType(TypeId $id): AbstractType
    {
        return $this->types->getType($id);
    }

    /**
     * From an XSD sequence property name, convert to PHP property name.
     *
     * @todo
     *   Allow user to override this.
     */
    public function getPhpPropertyName(string $name, TypeId $parentId): string
    {
        return $this->config->propertyCamelCase ? \lcfirst($name) : $name;
    }

    /**
     * From type identifier, generate a PHP type name.
     *
     * @todo
     *   Allow user to override this.
     */
    public function getPhpTypeName(TypeId $id): string
    {
        if ('xsd' === $id->namespace) {
            return $id->name;
        }

        $type = $this->types->getType($id);

        if ($type instanceof SimpleType) {
            return $type->type;
        }

        return $this->config->resolvePhpTypeName($id->name, $id->namespace);
    }

    /**
     * Alias of getPhpTypeName() which returns an [namespace, local name].
     *
     * @todo
     *   Allow user to override this.
     */
    public function expandPhpTypeName(TypeId $id): array
    {
        if ('xsd' === $id->namespace) {
            return ['scalar', $id->name];
        }

        $type = $this->types->getType($id);

        if ($type instanceof SimpleType) {
            return ['scalar', $type->type];
        }

        $phpNamespace = null;
        $phpClassName = $this->config->resolvePhpTypeName($id->name, $id->namespace);

        if ($pos = \strrpos($phpClassName, '\\')) {
            $phpNamespace = \substr($phpClassName, 0, $pos);
            $phpClassName = \substr($phpClassName, $pos + 1);
        }

        return [$phpNamespace, $phpClassName];
    }

    /**
     * Expand scalar types to PHP types.
     *
     * @todo
     *   Allow user to override this.
     */
    public function convertXsdScalarToPhp(string $type): string
    {
        return match ($type) {
            'anyURI' => 'string',
            'base64Binary' => 'string',
            'boolean' => 'bool',
            'date' => '\\' . \DateTimeImmutable::class,
            'dateTime' => '\\' . \DateTimeImmutable::class,
            'decimal ' => 'float',
            'double' => 'float',
            'duration' => '\\' . \DateInterval::class,
            'float' => 'float',
            'gDay' => 'int',
            'gMonth' => 'int',
            'gMonthDay' => 'int',
            'gYear' => 'int',
            'gYearMonth' => 'int',
            'hexBinary' => 'string',
            'integer' => 'int',
            'normalizedString' => 'string',
            'NOTATION' => 'string',
            'Qname' => 'string',
            'string' => 'string',
            'time' => 'string',
            default => $type,
        };
    }
}
