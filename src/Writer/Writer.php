<?php

declare(strict_types=1);

namespace MakinaCorpus\SoapGenerator\Writer;

use MakinaCorpus\SoapGenerator\GeneratorConfig;
use MakinaCorpus\SoapGenerator\Reader\TypeRegistry;
use MakinaCorpus\SoapGenerator\Type\ComplexType;
use MakinaCorpus\SoapGenerator\Type\ComplexTypeProperty;

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
        $context = new WriterContext($this->types,  $this->config);

        // First resolve all names.
        foreach ($this->types->getAllTypes() as $type) {
            list ($phpNamespace, $phpLocalName) = $context->expandPhpTypeName($type->id);

            $type->setPhpName($phpLocalName, $phpNamespace);

            if ($type instanceof ComplexType) {
                foreach ($type->properties as $prop) {
                    \assert($prop instanceof ComplexTypeProperty);

                    $prop->setPhpName($context->getPhpPropertyName($prop->name, $type->id));
                }
            }
        }

        // And now generate types.
        foreach ($this->types->getAllTypes() as $type) {
            if ($type instanceof ComplexType) {
                (new ClassWriter())->writeClass($context, $type);
            }
        }
    }
}
