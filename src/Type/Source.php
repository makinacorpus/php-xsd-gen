<?php

declare(strict_types=1);

namespace MakinaCorpus\SoapGenerator\Type;

class Source
{
    public function __construct(
        public readonly string $filename,
        public readonly int $line = 0,
        public readonly ?string $path = null,
    ) {
    }

    public static function unknown(): self
    {
        return new self('unknown');
    }

    public static function fromDomNode(\DOMNode $node): self
    {
        return new self($node->ownerDocument?->documentURI ?? 'unknown', $node->getLineNo(), $node->getNodePath() ?? 'unknown');
    }
}
