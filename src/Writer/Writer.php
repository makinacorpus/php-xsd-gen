<?php

declare(strict_types=1);

namespace MakinaCorpus\SoapGenerator\Writer;

use MakinaCorpus\SoapGenerator\GeneratorConfig;
use MakinaCorpus\SoapGenerator\Error\TypeDoesNotExistError;
use MakinaCorpus\SoapGenerator\Reader\TypeRegistry;
use MakinaCorpus\SoapGenerator\Type\AbstractType;
use MakinaCorpus\SoapGenerator\Type\ComplexType;
use MakinaCorpus\SoapGenerator\Type\ComplexTypeProperty;
use MakinaCorpus\SoapGenerator\Type\SimpleType;

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

        // Then resolve inheritance of each type.
        foreach ($this->types->getAllTypes() as $type) {
            $this->resolveInheritance($context, $type);
        }

        // Finally generate PHP code.
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

    private function resolveInheritance(WriterContext $context, AbstractType $type): void
    {
        if (!$type->extends || $type->inheritanceResolved) {
            return;
        }

        $parentType = $this->types->getType($type->extends);

        if (!$parentType->resolved) {
            $context->logErr("{parent}: inherited type is not resolved, omiting inheritance for '{type}'", ['parent' => $parentType, 'type' => $type]);
            return;
        }
        if (!$parentType instanceof ComplexType) {
            $context->logErr("{parent}: inherited type is not a complex type, omiting inheritance for '{type}'", ['parent' => $parentType, 'type' => $type]);
            return;
        }

        // Ensure top-bottom (from ancestors to child) inheritance ordering
        // in order to avoid removing properties after they are dealt with
        // in resolveInheritanceProps().
        $this->resolveInheritance($context, $parentType);

        $type->resolveInheritance(
            $parentType->getPhpLocalName(),
            $parentType->getPhpNamespace(),
        );

        if ($type instanceof ComplexType) {
            $type->resolveInheritedProperties(
                $this->resolveInheritanceProps($context, $type, $parentType)
            );
        }
    }

    /** @return ComplexTypeProperty[] */
    private function resolveInheritanceProps(
        WriterContext $context,
        ComplexType $type,
        AbstractType $parentType,
    ): array {
        // Not logging anything here because it is done in resolveInheritance().
        if (!$parentType instanceof ComplexType || !$parentType->resolved) {
            return [];
        }

        if ($parentType->extends) {
            $ret = $this->resolveInheritanceProps($context, $type, $context->getType($parentType->extends));
        } else {
            $ret = [];
        }

        foreach ($parentType->properties as $prop) {
            \assert($prop instanceof ComplexTypeProperty);

            if (!$prop->resolved) {
                $context->logWarn("{prop}: skipping inherited property: was not resolved or dropped", ['prop' => $prop]);
                continue; // Property skipped by resolution.
            }

            if ($type->propertyExists($prop->name)) {
                $context->logInfo("{prop}: inherited property is shadowed by parent", ['prop' => $prop]);

                $shadowingProp = $type->getProperty($prop->name);
                $shadowingProp->resolveInheritance(true);

                // When working with public properties and constructor
                // property promotion altogether, constructor defined
                // shadowing properties will raise PHP errors at compile
                // time, avoid that by simply removing the property from
                // the child class.
                // There is no easy way around, simply because the parent
                // type could be in use elsewhere.
                // This should not be a problem as long as the child class
                // property is contravariant with the parent class property:
                // the child class will simply inherit from a wider type
                // instead of restricting it to a more specific one.
                // In case of non contravariancy, PHP runtime will crash
                // anyway at object creation time, and we cannot detect
                // this properly.
                if ($context->config->propertyPromotion && $context->config->propertyPublic) {
                    $context->logWarn("{prop}: constructor promotion and shadowing public properties are not compatible, dropping property", ['prop' => $shadowingProp]);
                    $shadowingProp->unresolve();
                }
            }

            // Since we order properties from the deepest ancestor toward
            // the closest one, shadowing properties will happen in natural
            // order among parents. We only need to remove properties
            // shadowed by the class we are generating.
            // Except in the case CPP + public properties.
            if (!isset($ret[$prop->name]) || (!$context->config->propertyPromotion || !$context->config->propertyPublic)) {
                $ret[$prop->name] = $prop;
            }
        }

        return $ret;
    }

    private function expandProperty(WriterContext $context, ComplexType $type, ComplexTypeProperty $prop): void
    {
        if ($prop->resolved) {
            return;
        }

        $phpName = $context->getPhpPropertyName($prop->name, $type->id);

        try {
            $propType = $context->getType($prop->type);
        } catch (TypeDoesNotExistError $e) {
            if ($this->config->errorWhenTypeMissing) {
                throw $e;
            }
            $context->logWarn('{prop}: skipping property, type is missing', ['prop' => $prop->toString()]);

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
