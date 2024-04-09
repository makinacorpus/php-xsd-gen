<?php

declare(strict_types=1);

namespace MakinaCorpus\SoapGenerator\Writer;

use MakinaCorpus\SoapGenerator\GeneratorConfig;
use MakinaCorpus\SoapGenerator\Reader\TypeRegistry;
use MakinaCorpus\SoapGenerator\Type\AbstractType;
use MakinaCorpus\SoapGenerator\Type\ComplexType;
use MakinaCorpus\SoapGenerator\Type\ComplexTypeProperty;
use MakinaCorpus\SoapGenerator\Type\SimpleType;
use MakinaCorpus\SoapGenerator\Error\TypeDoesNotExistError;

class Writer
{
    private readonly GeneratorConfig $config;

    public function __construct(
        private readonly TypeRegistry $types,
        ?GeneratorConfig $config = null,
    ) {
        $this->config = $config ?? new GeneratorConfig();
    }

    /**
     * Write all files for all types found in the given registry.
     */
    public function writeAll(): void
    {
        $context = new WriterContext($this->types, $this->config);

        // First resolve all names.
        foreach ($this->types->getAllTypes() as $type) {
            $this->expandType($context, $type, true);
        }

        // And now generate types.
        foreach ($this->types->getAllTypes() as $type) {
            if ($type instanceof ComplexType) {
                (new ClassWriter())->writeClass($context, $type);
            }
        }
    }

    private function expandType(WriterContext $context, AbstractType $type, bool $withProperties = true): void
    {
        if (!$type->resolved) {
            list($phpNamespace, $phpLocalName) = $context->expandPhpTypeName($type->id);

            $type->resolve($phpLocalName, $phpNamespace);
        }

        if ($withProperties && $type instanceof ComplexType) {
            foreach ($type->properties as $prop) {
                \assert($prop instanceof ComplexTypeProperty);

                $this->expandProperty($context, $type, $prop);
            }
        }
    }

    private function expandProperty(WriterContext $context, ComplexType $type, ComplexTypeProperty $prop): void
    {
        if ($prop->resolved) {
            return;
        }

        $phpName = $context->getPhpPropertyName($prop->name, $type->id);

        try {
            $propType = $context->findType($prop->type);
        } catch (TypeDoesNotExistError $e) {
            if (!$this->config->ignoreMissingTypes) {
                throw $e;
            }
            \trigger_error(\sprintf("%s: skipping property, type is missing", $prop->toString()), E_USER_WARNING);

            return;
        }

        $this->expandType($context, $propType, false);

        $phpValueTypeNs = $propType->getPhpNamespace();

        if ($propType instanceof SimpleType) {
            $phpValueType = $context->convertXsdScalarToPhp($propType->type);
        } else {
            $phpValueType = $propType->getPhpLocalName();
        }

        // propType is the canonical type (eg. "string", "int")
        // propTypeString is what we write (eg. "?string", "array")
        // propDocString is what PHP can't do that we document (eg. "?string", "int[]")
        // If nullable, simply put "?" in front of type. We don't care
        // about union types, they simply don't seem to exist in WSDL
        // and XSD documentation.
        $phpDocType = null;
        $phpType = $prop->nullable ? ('?' . $phpValueType) : $phpValueType;
        if ($prop->collection) {
            // We ignore nullable status for collections, we always can
            // put an empty array instead, and that's fine.
            $phpDocType = \sprintf('/** @var %s[] */', $phpValueType);
            $phpType = 'array';
        }

        $prop->resolve($phpName, $phpType, $phpValueType, $phpValueTypeNs, $phpDocType, $propType instanceof SimpleType);
    }
}
