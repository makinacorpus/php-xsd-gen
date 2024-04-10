<?php

declare(strict_types=1);

namespace MakinaCorpus\SoapGenerator\Helper;

use Psr\Log\LoggerInterface;
use MakinaCorpus\SoapGenerator\GeneratorConfig;

/**
 * @todo
 *   - Handle logger
 *   - Add a single namespace(source, target) name
 *   - handle name map
 */
abstract class AbstractGenerator
{
    public function __construct(
        protected array $options = [],
        protected ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Set logger for parsing messages.
     */
    public function logger(?LoggerInterface $logger): static
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Should the class have a public constructor
     *
     * Default is true.
     */
    public function classConstructor(bool $toggle = true): static
    {
        $this->options['class_constructor'] = $toggle;

        return $this;
    }

    /**
     * Should the class have a static create(self|array) factory method.
     *
     * Default is true.
     */
    public function classFactoryMethod(bool $toggle = true): static
    {
        $this->options['class_factory_method'] = $toggle;

        return $this;
    }

    /**
     * Set default PHP namespace prefix for all generated code.
     */
    public function defaultNamespace(string $namespace): static
    {
        $this->options['default_namespace'] = $namespace;

        return $this;
    }

    /**
     * Set default source directory. This will be the path prefix
     * corresponding to PSR-4 namespace of default namespace.
     */
    public function defaultDirectory(string $directory): static
    {
        $this->options['default_directory'] = $directory;

        return $this;
    }

    /**
     * Set the custom namespace map.
     *
     * Namespaces will be processed in order, you should order from
     * the most specific to the most generic.
     *
     * Each key is a source namespace, or a PHP generated namespace
     * generated accordinly to the previous rules. The source namespace
     * can be incomplete, and trailing data will be used to infix the
     * default namespace.
     * Values are the PHP namespace infix that will be placed in between
     * the default root namespace name, and the final class name.
     *
     * For example, consider you have the following default PHP namespace:
     * "\Vendor\App", and the following rule:
     *
     *   "https//schema.vendor.com/app/CommonTypes" => "Common"
     *
     * And you stumble upon the following namespace in one of your XSD
     * definitions:
     *
     *  "https//schema.vendor.com/app/CommonTypes/Some/Feature/1.0"
     *
     * The namespace will match "https//schema.vendor.com/app/CommonTypes" hence
     * the trailing namespace name will be: "Some/Feature/1.0"
     *
     * And converted to PHP as: "Some\Feature" (because numbers
     * are invalid namespace names, hence ignored).
     *
     * Then, the final class namespace will be: \Vendor\App\Common\Some\Feature".
     *
     * @param array<string,string> $namespaces
     */
    public function namespaces(array $namespaces): static
    {
        foreach ($namespaces as $namespaceUri => $phpNamespace) {
            $this->namespace($namespaceUri, $phpNamespace);
        }

        return $this;
    }

    /**
     * Set a single namesapce.
     *
     * @see self::namespaces()
     */
    public function namespace(string $namespaceUri, string $phpNamespace): static
    {
        $this->options['namespaces'][$namespaceUri] = $phpNamespace;

        return $this;
    }

    /**
     * Convert property names to camelCase names.
     *
     * Default is true.
     */
    public function propertyCamelCase(bool $toggle = true): static
    {
        $this->options['property_camel_case'] = $toggle;

        return $this;
    }

    /**
     * Add property defaults when possible.
     *
     * Default is true.
     */
    public function propertyDefault(bool $toggle = true): static
    {
        $this->options['property_default'] = $toggle;

        return $this;
    }

    /**
     * Generate property getters.
     *
     * Default is false.
     */
    public function propertyGetter(bool $toggle = false): static
    {
        $this->options['property_getter'] = $toggle;

        return $this;
    }

    /**
     * Write all properties using property constructor promotion.
     *
     * Default is true.
     */
    public function propertyPromotion(bool $toggle = true): static
    {
        $this->options['property_promotion'] = $toggle;

        return $this;
    }

    /**
     * Make all properties public.
     *
     * Default is true.
     */
    public function propertyPublic(bool $toggle = true): static
    {
        $this->options['property_public'] = $toggle;

        return $this;
    }

    /**
     * Make all properties readonly.
     *
     * Default is true.
     */
    public function propertyReadonly(bool $toggle = true): static
    {
        $this->options['property_readonly'] = $toggle;

        return $this;
    }

    /**
     * Generate property setters.
     *
     * If you let properties as readonly, this setting will be ignored.
     *
     * Default is false.
     */
    public function propertySetter(bool $toggle = false): static
    {
        $this->options['property_setter'] = $toggle;

        return $this;
    }

    /**
     * Raise error when a type is missing from definition files.
     *
     * Sometime you might want to generate incomplete schemas that references
     * unreachable or non existing schema XSD files.
     *
     * Default is false.
     */
    public function typeMissingError(bool $toggle = false): static
    {
        $this->options['type_missing_error'] = $toggle;

        return $this;
    }

    /**
     * Raise error when a type is defined more than once.
     *
     * Sometime you might want to generate a complete schema composed of more
     * than one file, which may define the same type over and over again instead
     * of using a common XSD source for all common types.
     *
     * Default is false.
     */
    public function typeOverrideError(bool $toggle = true): static
    {
        $this->options['type_override_error'] = $toggle;

        return $this;
    }

    /**
     * Merge configuration.
     */
    protected function mergeConfig(array $defaults): array
    {
        foreach ($this->options as $key => $value) {
            if (null === $value) {
                continue;
            }
            if ('namespaces' === $key) {
                foreach ($value as $namespaceUri => $phpNamespace) {
                    $defaults['namespaces'][$namespaceUri] = $phpNamespace;
                }
            }
            $defaults[$key] = $value;
        }

        if ($this->logger) {
            $defaults['logger'] = $this->logger;
        }

        return $defaults;
    }

    /**
     * Generate configuration.
     */
    protected function generateConfig(array $defaults = []): GeneratorConfig
    {
        return GeneratorConfig::fromArray($this->mergeConfig($defaults));
    }
}
