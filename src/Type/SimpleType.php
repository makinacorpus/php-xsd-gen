<?php

declare(strict_types=1);

namespace MakinaCorpus\XsdGen\Type;

class SimpleType extends AbstractType
{
    public function __construct(
        TypeId $id,
        ?TypeId $extends = null,
        public readonly string $type = 'string',
    ) {
        parent::__construct($id, $extends);
    }
}
