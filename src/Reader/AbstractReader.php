<?php

declare(strict_types=1);

namespace MakinaCorpus\SoapGenerator\Reader;

use MakinaCorpus\SoapGenerator\Error\ReaderError;
use MakinaCorpus\SoapGenerator\Error\ResourceCouldNotBeFoundError;

abstract class AbstractReader
{
    protected \DOMDocument $document;
    private ReaderContext $context;

    public function __construct(
        private string $filename,
        ?ReaderContext $context = null,
    ) {
        if (!\file_exists($filename)) {
            throw new ResourceCouldNotBeFoundError(\sprintf("%s: file does not exist", $filename));
        }
        if (!\is_readable($filename)) {
            throw new ResourceCouldNotBeFoundError(\sprintf("%s: file cannot be read", $filename));
        }

        $directory = \dirname($filename);
        if ($context) {
            $this->context = $context->clone($directory);
        } else {
            $this->context = new ReaderContext(directory: $directory);
        }

        $this->document = new \DOMDocument();
        if (!$this->document->load($filename, LIBXML_COMPACT | LIBXML_HTML_NODEFDTD | LIBXML_NOBLANKS | LIBXML_NOCDATA | LIBXML_NOERROR | LIBXML_NONET)) {
            throw new ReaderError(\sprintf("%s: file could not be read as XML", $filename));
        }

        if ($this->document->documentElement) {
            $this->processNamespaces($this->context, $this->document->documentElement);
        }
    }

    /**
     * Get root context.
     */
    protected function getRootContext(): ReaderContext
    {
        return $this->context;
    }

    /**
     * Read [xmlns:ALIAS=NAMESPACE] attributes on element.
     */
    protected function processNamespaces(ReaderContext $context, \DOMElement $element): ReaderContext
    {
        if (!$element->hasAttributes()) {
            return $context;
        }

        $currentNamespace = null;
        $newContext = null;

        if ($element->hasAttribute('targetNamespace')) {
            $currentNamespace = (string) $element->getAttribute('targetNamespace');
        } else if ($element->hasAttribute('xmlns')) {
            $currentNamespace = (string) $element->getAttribute('targetNamespace');
        }
        if ($currentNamespace) {
            $newContext = $context->nest($currentNamespace);
        }

        // @todo The only proper solution would be to use getAttributeNames()
        //    but it causes arbitrary segfaults on my box, under certain
        //    conditions I was not able to understand why (those conditions
        //    are reproducible in some test cases I work with).
        //    This would need proper PHP debugging.
        // Beware, you eyes are going to bleed.
        $tempDoc = new \DOMDocument();
        $tempNode = $tempDoc->importNode($element);
        \assert($tempNode instanceof \DOMNode);
        $tempDoc->appendChild($tempNode);
        $tempBuffer = $tempDoc->saveHTML();
        $matches = [];
        if (\preg_match_all('/xmlns:([a-z0-9_-]+)=("([^"]+)"|([^\s]+))/ims', $tempBuffer, $matches)) {
            foreach ($matches[1] as $index => $alias)  {
                if (!$newContext) {
                    $newContext = $context->nest();
                }
                $namespace = $matches[3][$index] ?: $matches[4][$index];
                $newContext->registerNamespace($alias, $namespace);
            }
        }

        /*
        foreach ($element->attributes as $attribute) {
            \assert($attribute instanceof \DOMAttr);

            $name = $attribute->name;

            if (\str_starts_with($name, 'xmlns:')) {
                if (!$newContext) {
                    $newContext = $context->nest();
                }

                $newContext->registerNamespace(\substr($name, 6), (string) $element->getAttribute($name));
            }
        }
         */

        return $newContext ?? $context;
    }

    /**
     * Trigger warning.
     */
    protected function warning(string $message): void
    {
        \trigger_error($message, E_USER_WARNING);
    }

    /**
     * Missing attribute error/warning.
     */
    protected function attribute(\DOMElement $element, string $name): ?string
    {
        if ($element->hasAttribute($name)) {
            return (string) $element->getAttribute($name);
        }
        return null;
    }

    /**
     * Missing attribute error/warning.
     */
    protected function attributeRequired(\DOMElement $element, string $name): ?string
    {
        if (null === ($value = $this->attribute($element, $name))) {
            $this->attributeMissing($element, $name);
        }
        return $value;
    }

    /**
     * Missing attribute error/warning.
     */
    protected function attributeMissing(\DOMElement $element, string $name): void
    {
        $this->warning(\sprintf('<%s> is missing attribute "%s"', $element->tagName, $name));
    }

    /**
     * Unexpected element error/warning.
     */
    protected function elementUnexpected(\DOMNode $element, ?string $in = null): void
    {
        if ($element instanceof \DOMElement) {
            $this->warning(\sprintf("<%s> is unexpected in %s", $element->tagName, $in ?? 'document'));
        }
    }

    /**
     * Check if element matches name (and namespace).
     */
    protected function elementIs(\DOMNode $element, string $name, ?string $defaultPrefix = null): bool
    {
        if (!$element instanceof \DOMElement) {
            return false;
        }

        if (false !== \strpos($name, ':')) {
            list($prefix, $name) = \explode(':', $name, 2);
        } else {
            $prefix = $defaultPrefix;
        }

        return (!$element->prefix || $element->prefix === $prefix) && $element->localName === $name;
    }

    /**
     * Raise an exception if element does not match.
     */
    protected function elementCheck(\DOMElement $element, string $name, ?string $defaultPrefix = null): void
    {
        if (!$this->elementIs($element, $name)) {
            throw new ReaderError(\sprintf("Expected <%s>, got <%s>", $name, $element->tagName));
        }
    }
}
