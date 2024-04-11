<?php

declare(strict_types=1);

namespace MakinaCorpus\XsdGen;

use MakinaCorpus\XsdGen\Helper\AbstractGenerator;
use MakinaCorpus\XsdGen\Helper\GeneratorItem;
use MakinaCorpus\XsdGen\Reader\GlobalContext;
use MakinaCorpus\XsdGen\Reader\ReaderContext;
use MakinaCorpus\XsdGen\Reader\WsdlReader;

class Generator extends AbstractGenerator
{
    /** @var array<GeneratorItem> */
    private array $plan = [];

    /**
     * Add a new file to parse.
     */
    public function file(string $filename): static
    {
        $this->plan[] = new GeneratorItem($this, $filename);

        return $this;
    }

    /**
     * Add a new file to parse, with custom options builder.
     */
    public function fileBuilder(string $filename): GeneratorItem
    {
        return new GeneratorItem($this, $filename);
    }

    /**
     * Generate all.
     */
    public function generate(): void
    {
        $defaults = ['logger' => $this->logger] + $this->options;

        foreach ($this->plan as $item) {
            \assert($item instanceof GeneratorItem);

            $config = $item->generateConfig($defaults);

            $global = new GlobalContext(config: $config);
            $context = new ReaderContext(global: $global);

            $reader = new WsdlReader($item->getFilename(), $context);
            $reader->execute();

            $global->createWriter()->writeAll();
        }
    }
}
