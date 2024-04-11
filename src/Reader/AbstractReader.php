<?php

declare(strict_types=1);

namespace MakinaCorpus\XsdGen\Reader;

use MakinaCorpus\XsdGen\Error\ReaderError;
use MakinaCorpus\XsdGen\Error\ResourceCouldNotBeFoundError;

abstract class AbstractReader
{
    private ReaderContext $context;

    public function __construct(string $filename, ?ReaderContext $context = null)
    {
        if (!\file_exists($filename)) {
            throw new ResourceCouldNotBeFoundError(\sprintf("%s: file does not exist", $filename));
        }
        if (!\is_readable($filename)) {
            throw new ResourceCouldNotBeFoundError(\sprintf("%s: file cannot be read", $filename));
        }

        if ($context) {
            $this->context = $context->createCloneForDocument($filename);
        } else {
            $this->context = new ReaderContext(filename: $filename);
        }

        $this->context->logInfo("Created reader for file '{file}'", ['file' => $filename]);
    }

    /**
     * Main execution handler.
     */
    public function execute()
    {
        $context = $this->context;
        $context->logInfo("Executing file '{file}'", ['file' => $context->filename]);

        $document = new \DOMDocument();

        if (!$document->load($context->filename, LIBXML_COMPACT | LIBXML_HTML_NODEFDTD | LIBXML_NOBLANKS | LIBXML_NOCDATA | LIBXML_NOERROR | LIBXML_NONET)) {
            throw new ReaderError(\sprintf("%s: file could not be read as XML", $context->filename));
        }

        if ($document->documentElement) {
            $this->root($context);
            $this->executeElement($context, $document->documentElement, null);
        } else {
            $context->logErr("{file}: document is empty.", ['file' => $context->filename]);
        }
    }

    /**
     * On document element.
     */
    abstract protected function root(ReaderContext $context): void;

    /**
     * Execute expectations on element and recurse.
     */
    private function executeElement(ReaderContext $context, \DOMElement $element, ?\DOMElement $parent = null): void
    {
        $context = $context = $this->createElementContext($context, $element);

        if (!$expectations = $context->parent?->getAllExpectations()) {
            return;
        }

        $executed = 0;
        foreach ($expectations as $expectation) {
            list($match, $callback, $args) = $expectation;
            if ($this->elementIs($element, $match)) {
                $executed++;
                $callback($context, $element, ...$args);
            }
        }

        if ($executed) {
            foreach ($element->childNodes as $child) {
                if (!$child instanceof \DOMElement) {
                    continue;
                }
                $this->executeElement($context, $child, $element);
            }
        } else if ($parent) {
            $context->logWarn(\sprintf("<%s> is unexpected in '%s'", $element->tagName, $parent->getNodePath()));
        } else {
            $context->logWarn(\sprintf("<%s> is unexpected at root", $element->tagName));
        }
    }

    /**
     * @deprecated
     */
    protected function processNamespaces(ReaderContext $context, \DOMElement $element): ReaderContext
    {
        if (!$element->hasAttributes()) {
            return $context;
        }

        // Find current element namespace if any.
        $currentNamespace = null;
        $newContext = null;

        if ($element->hasAttribute('targetNamespace')) {
            $currentNamespace = (string) $element->getAttribute('targetNamespace');
        } else if ($element->hasAttribute('xmlns')) {
            $currentNamespace = (string) $element->getAttribute('targetNamespace');
        }
        if ($currentNamespace) {
            $newContext = $context->createChild($currentNamespace);
        }

        if ($aliases = $this->processNamespaceAttributes($context, $element)) {
            if (!$newContext) {
                $newContext = $context->createChild();
            }
            foreach ($aliases as $alias => $namespace) {
                $newContext->registerNamespace($alias, $namespace);
            }
        }

        return $newContext ?? $context;
    }

    /**
     * Create context for element.
     */
    private function createElementContext(ReaderContext $context, \DOMElement $element): ReaderContext
    {
        // Find current element namespace if any.
        $namespace = null;
        if ($element->hasAttribute('targetNamespace')) {
            $namespace = (string) $element->getAttribute('targetNamespace');
        } else if ($element->hasAttribute('xmlns')) {
            $namespace = (string) $element->getAttribute('targetNamespace');
        }

        $context = $context->createChild($namespace);

        foreach ($this->processNamespaceAttributes($context, $element) as $alias => $namespace) {
            $context->registerNamespace($alias, $namespace);
        }

        return $context;
    }

    /**
     * Read [xmlns:ALIAS=NAMESPACE] attributes on element.
     */
    private function processNamespaceAttributes(ReaderContext $context, \DOMElement $element): array
    {
        $ret = [];

        if (!$element->hasAttributes()) {
            return $ret;
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
            foreach ($matches[1] as $index => $alias) {

                // Ignore redefinition of the XSD schema.
                if ('xsd' === $alias) {
                    continue;
                }

                $namespace = $matches[3][$index] ?: $matches[4][$index];

                // XMLSchema could be aliased, this is legal.
                if ('http://www.w3.org/2001/XMLSchema' === $namespace) {
                    $namespace = 'xsd';
                }

                $ret[$alias] = $namespace;
            }
        }

        return $ret;
    }

    /**
     * Get attribute value.
     */
    protected function attr(\DOMElement $element, string $name): ?string
    {
        if ($element->hasAttribute($name)) {
            return (string) $element->getAttribute($name);
        }
        return null;
    }

    /**
     * Missing attribute error/warning.
     */
    protected function attrRequired(\DOMElement $element, string $name): ?string
    {
        if (null === ($value = $this->attr($element, $name))) {
            $this->context->logWarn(\sprintf('<%s> is missing attribute "%s"', $element->tagName, $name));
        }
        return $value;
    }

    /**
     * Attribute or die.
     */
    protected function attrOdDie(\DOMElement $element, string $name): ?string
    {
        if (null === ($value = $this->attr($element, $name))) {
            throw new ReaderError(\sprintf("<%s> is missing the [%s] attribute", $element->tagName, $name));
        }
        return $value;
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
}
