<?php

declare(strict_types=1);

namespace MakinaCorpus\SoapGenerator\Helper;

use MakinaCorpus\SoapGenerator\Generator;

class GeneratorItem extends AbstractGenerator
{
    public function __construct(
        private Generator $parent,
        private string $filename,
        array $options = []
    ) {
        parent::__construct($options);
    }

    public function end(): Generator
    {
        return $this->parent;
    }

    /**
     * Will be used by each item to merge options.
     */
    protected function getOptionsOverrides(): array
    {
        return $this->options;
    }

    /**
     * @internal
     */
    public function getFilename(): string
    {
        return $this->filename;
    }
}
