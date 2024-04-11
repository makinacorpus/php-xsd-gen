<?php

declare(strict_types=1);

namespace MakinaCorpus\XsdGen;

use MakinaCorpus\XsdGen\Error\ConfigError;
use Psr\Log\LoggerInterface;

/**
 * Configuration reference:
 *
 * ```yaml
 * xsd_gen:
 *     # Default namespace prefix for all generated classes.
 *     # This will be suffixed to generated namespaces.
 *     defaultNamespace: "Vendor\App\Generated"
 *
 *     # Default PSR-4 directory for storing generated code.
 *     defaultDirectory: "src/Generated"
 *
 *     # PHP namespace directory map.
 *     # Keys are fully qualified namespaces, values are folders (absolute or relative).
 *     directories:
 *         "App\Vendor\Foo": "src/Foo"
 *
 *     # XML namespaces to PHP namespaces.
 *     # Keys are XSD complete namespaces, values are full qualified PHP namespaces.
 *     namespaces:
 *         "https://vendor.com/foo": "App\Vendor\Foo"
 *
 *     # Per-type namespace.
 *     # Keys are XML namespaces, values are array, whose keys are local type names
 *     # and values are PHP class names.
 *     # "static" namespace will always be looked up, keys are fully qualified XML
 *     # type names (eg. NAMESPACE/NAME) or fully qualified PHP class names, values
 *     # are full qualified PHP class name for substitution.
 *     name:
 *         "https://vendor.com/foo":
 *             BlaBla: Bla
 *         "App\Vendor\Foo":
 *             BlaBla: Bla
 *          static:
 *              "https://vendor.com/foo/BlaBla": "App\Vendor\Foo\Bla"
 *              "App\Vendor\Foo\BlaBla": "App\Vendor\Foo\Bla"
 *
 *     # Make generated classes constructor be public. Default is true
 *     # and should not be changed otherwise SOAP tooling won't work
 *     # anymore.
 *     class_constructor: true
 *
 *     # Add a static method named "create()" which accepts either an
 *     # already hydrated instance, or an abitrary array of values
 *     # whose names are XSD property names for creating a new instance.
 *     # This is part of generated SOAP tooling.
 *     class_factory_method: true
 *
 *     # Fix properties names to be camelCased when the source XSD names
 *     # are not. Default is true.
 *     property_camel_case: true
 *
 *     # Set property default values whenever possible.
 *     # Not implemented yet, and probably never be.
 *     property_defaults: true
 *
 *     # Generate property getters. Default is true.
 *     property_getter: true
 *
 *     # Use constructor property promotion when possible.
 *     # Default is false.
 *     property_promotion: false
 *
 *     # Use public properties when possible.
 *     # Default is true.
 *     property_public: false
 *
 *     # Make properties being readonly when possible.
 *     # Default is false.
 *     property_readonly: true
 *
 *     # Generate property setters. Default is false.
 *     property_setter: false
 *
 *     # When a remote resource cannot be fetched, exception are raised.
 *     # Otherwise, missing types are simply ignored.
 *     # Default is false.
 *     resource_missing_remote_error: false
 *
 *     # When a local resource is missing, exception are raised.
 *     # Otherwise, missing types are simply ignored.
 *     # Default is true.
 *     resource_missing_local_error: true
 *
 *     # You might happen to work with incomplete WSDL files or files
 *     # referencing remote XSD schemas that do not exist or you cannot
 *     # fetch. When this happens, there are great chances that some types
 *     # will be missing from the definitions.
 *     # Per default, when types are missing, this API will simply omit
 *     # the incomplete type properties from the generated code.
 *     # Set this to true to raise fatal errors when types are missing.
 *     type_missing_error: false
 *
 *     # Sometime you might deal with wrongly built WSDL, which repeats
 *     # the same types in more than one file instead of importing common
 *     # XSD files. In this case per default this API will let conflicting
 *     # type names override the previously found ones.
 *     # Set this to true to prevent type override and raise fatal errors
 *     # when name conflicts are discovered.
 *     type_override_error: false
```
 */
class GeneratorConfig
{
    public readonly ?string $defaultNamespace;
    public readonly string $defaultDirectory;
    public readonly bool $propertySetters;

    public function __construct(
        ?string $defaultNamespace = null,
        string $defaultDirectory = 'src/Generated',
        /** @var array<string,string|null> */
        private readonly array $namespaceMap = [],
        /** @var array<string,string> */
        private readonly array $nameMap = [],
        public readonly bool $classConstructor = true,
        public readonly bool $classFactoryMethod = true,
        public readonly bool $errorWhenTypeMissing = false,
        public readonly bool $errorWhenTypeOverride = false,
        public readonly bool $propertyCamelCase = true,
        public readonly bool $propertyDefaults = true,
        public readonly bool $propertyGetters = true,
        public readonly bool $propertyPromotion = false,
        public readonly bool $propertyPublic = false,
        public readonly bool $propertyReadonly = true,
        bool $propertySetters = false,
        public readonly ?LoggerInterface $logger = null,
    ) {
        $this->defaultNamespace = $defaultNamespace ? \trim($defaultNamespace, '\\') : null;
        $this->defaultDirectory = \rtrim($defaultDirectory, '/');

        if ($propertyReadonly && $propertySetters) {
            \trigger_error("Setters cannot be written for readonly properties.", E_USER_WARNING);
            $this->propertySetters = false;
        } else {
            $this->propertySetters = $propertySetters;
        }
    }

    /**
     * Create instance from user array (for example, from configuration).
     */
    public static function fromArray(array $values): self
    {
        $args = [];
        foreach ($values as $key => $data) {
            match ($key) {
                'class_constructor' => $args['classConstructor'] = self::validateBool($data, $key),
                'class_factory_method' => $args['classFactoryMethod'] = self::validateBool($data, $key),
                'default_directory' => $args['defaultDirectory'] = self::validateDirectory($data, $key),
                'default_namespace' => $args['defaultNamespace'] = self::validatePhpNamespace($data, $key),
                'logger' => $args['logger'] = $data, // @todo validation?
                'namespaces' => $args['namespaceMap'] = self::validateNamespaceMap($data, $key),
                'property_camel_case' => $args['propertyCamelCase'] = self::validateBool($data, $key),
                'property_default' => $args['propertyDefaults'] = self::validateBool($data, $key),
                'property_getter' => $args['propertyGetters'] = self::validateBool($data, $key),
                'property_promotion' => $args['propertyPromotion'] = self::validateBool($data, $key),
                'property_public' => $args['propertyPublic'] = self::validateBool($data, $key),
                'property_readonly' => $args['propertyReadonly'] = self::validateBool($data, $key),
                'property_setter' => $args['propertySetters'] = self::validateBool($data, $key),
                'type_missing_error' => $args['errorWhenTypeMissing'] = self::validateBool($data, $key),
                'type_override_error' => $args['errorWhenTypeOverride'] = self::validateBool($data, $key),
                default => throw new ConfigError(\sprintf("'%s': unexpected configuration key", $key)),
            };
        }

        return (new \ReflectionClass(__CLASS__))->newInstanceArgs($args);
    }

    /**
     * Resolve given source namespace to a PHP namespace.
     */
    public function resolveNamespace(string $namespace): string
    {
        $converted = $this->convertName($namespace);
        $namespacePrefix = $this->defaultNamespace ? $this->defaultNamespace . '\\' : '';

        foreach ($this->namespaceMap as $prefix => $target) {
            if ($namespace === $prefix || $converted === $prefix) {
                if (null === $target) {
                    return \rtrim($namespacePrefix, '\\');
                }
                return $namespacePrefix . $target;
            }

            if (\str_starts_with($namespace, $prefix)) {
                $suffix = \ltrim($this->convertName(\substr($namespace, \strlen($prefix))), '\\');
                if (null === $target) {
                    return $namespacePrefix . $suffix;
                }
                return $namespacePrefix . \trim($target, '\\') . '\\' . $suffix;
            }

            if (\str_starts_with($converted, $prefix)) {
                $suffix = \ltrim(\substr($converted, \strlen($prefix)), '\\');
                if (null === $target) {
                    return $namespacePrefix . $suffix;
                }
                return $namespacePrefix . \trim($target, '\\') . '\\' . $suffix;
            }
        }

        return $namespacePrefix . $this->convertName($namespace);
    }

    /**
     * Resolve filename for the given PHP class name.
     */
    public function resolveFileName(string $phpClassName): string
    {
        $phpClassName = \trim($phpClassName, '\\');

        $trailingNamespace = null;
        if ($pos = \strrpos($phpClassName, '\\')) {
            $trailingNamespace = \substr($phpClassName, 0, $pos);
            $localClassName = \substr($phpClassName, $pos + 1);
        } else {
            $localClassName = $phpClassName;
        }

        // Strip PSR-4 prefix from namespace to compute filename.
        if ($this->defaultNamespace && \str_starts_with($trailingNamespace, $this->defaultNamespace)) {
            $trailingNamespace = \substr($trailingNamespace, \strlen($this->defaultNamespace));
        }

        return $this->defaultDirectory . '/' . \str_replace('\\', '/', $trailingNamespace) . '/' . $localClassName . '.php';
    }

    /**
     * Resolve PHP fully qualified class name from source type name and namespace.
     */
    public function resolvePhpTypeName(string $name, string $namespace): string
    {
        $target = $namespace . '/' . $name;

        // First, try with fully qualified name.
        // @phpstan-ignore-next-line
        if ($found = ($this->nameMap['static'][$target] ?? null)) {
            return $found;
        }

        $phpNamespace = \trim($this->resolveNamespace($namespace), '\\');
        $className = $this->convertName($name);
        $target = $phpNamespace . '\\' . $className;

        // Then try with default generated fully qualified PHP name.
        // @phpstan-ignore-next-line
        if ($found = ($this->nameMap['static'][$target] ?? null)) {
            return $found;
        }

        // Then using PHP namespace.
        // @phpstan-ignore-next-line
        if ($found = ($this->nameMap[$phpNamespace][$name] ?? null)) {
            return $found;
        }

        // Then using XML namespace.
        // @phpstan-ignore-next-line
        if ($found = ($this->nameMap[$namespace][$name] ?? null)) {
            return $found;
        }

        // Convert some reserved keyword names.
        if ('object' === \strtolower($className)) {
            $className = 'Object_';
        }

        return $phpNamespace . '\\' . $className;
    }

    /**
     * Default name conversion strategy.
     *
     * @todo Later move this into a pluggable component.
     */
    private function convertName(string $name): string
    {
        if ($pos = \strpos($name, '://')) {
            $name = \substr($name, $pos + 3);
        }

        return \implode(
            '\\',
            \array_map(
                'ucfirst',
                \array_filter(
                    \preg_split(
                        '/[^a-z0-9_]+/ims',
                        $name,
                    ),
                    fn (string $value) => \preg_match('/^[a-z]/ims', $value),
                ),
            ),
        );
    }

    private static function validateBool(mixed $value, string $what): bool
    {
        if (!\is_bool($value)) {
            throw new ConfigError(\sprintf("'%s' must a bool value, '%s' given", $what, \get_debug_type($value)));
        }
        return $value;
    }

    private static function validateString(mixed $value, string $what): string
    {
        if (!\is_string($value)) {
            throw new ConfigError(\sprintf("'%s' must a string value, '%s' given", $what, \get_debug_type($value)));
        }
        return $value;
    }

    private static function validatePhpNamespace(mixed $value, string $what): string
    {
        // @todo validate more
        return self::validateString($value, $what);
    }

    private static function validateDirectory(mixed $value, string $what): string
    {
        // @todo validate more
        return self::validateString($value, $what);
    }

    private static function validateNamespaceMap(mixed $value, string $what): array
    {
        if (!\is_array($value)) {
            throw new ConfigError(\sprintf("'%s' must an array, '%s' given", $what, \get_debug_type($value)));
        }
        foreach ($value as $source => $target) {
            if (!\is_string($source)) {
                throw new ConfigError(\sprintf("'%s' entry key must a string, '%s' given", $what, \get_debug_type($source)));
            }
            $source = self::validateString($source, $what . '.<key>');
            $value[$source] = self::validatePhpNamespace($target, $what . '.' . $source);
        }

        return $value;
    }
}
