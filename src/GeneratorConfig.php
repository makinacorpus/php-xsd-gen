<?php

declare(strict_types=1);

namespace MakinaCorpus\SoapGenerator;

/**
 * @todo
 *   - Default namespace
 *   - Default folder
 *   - filename generator
 *   - use trait or inheritance
 *   - use helper or not
 *   - name mapping
 *   - type mapping
 *      - convert X to Y
 *          - where Y either a custom type
 *              - needs a custom constructor generator
 *              - OR a factory method that is always there
 *          - where Y is a scalar type
 *              - optional factory method for conversion
 *   - generate array hydrator
 *   - generate from XML hydrator
 *   - generate tooling ?
 *
 * ```yaml
 * config:
 *     # Default namespace prefix for all generated classes.
 *     # This will be suffixed to generated namespaces.
 *     defaultNamespace: "Vendor\App\Soap"
 *     # Default PSR-4 directory for storing generated code.
 *     defaultDirectory: "src/Soap"
 *     # PHP namespace directory map.
 *     # Keys are fully qualified namespaces, values are folders (absolute or relative).
 *     directory:
 *         "App\Vendor\Foo": "src/Foo"
 *     # XML namespaces to PHP namespaces.
 *     # Keys are SOAP complete namespaces, values are full qualified PHP namespaces.
 *     namespace:
 *         "https://vendor.com/foo": "App\Vendor\Foo"
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
 * ```
 */
class GeneratorConfig
{
    public readonly ?string $defaultNamespace;
    public readonly string $defaultDirectory;
    public readonly bool $propertySetters;

    public function __construct(
        ?string $defaultNamespace = null,
        string $defaultDirectory = 'src/Soap',
        /** @var array<string,string> */
        private readonly array $namespaceMap = [],
        /** @var array<string,string> */
        private readonly array $nameMap = [],
        public readonly bool $arrayHydrator = true,
        public readonly bool $camelCaseProperties = true,
        public readonly bool $constructor = true,
        public readonly bool $errorWhenTypeOverride = false,
        public readonly bool $ignoreMissingTypes = true,
        public readonly bool $propertyDefaults = true,
        public readonly bool $propertyGetters = true,
        public readonly bool $propertyPromotion = true,
        public readonly bool $publicProperties = true,
        public readonly bool $readonlyProperties = true,
        bool $propertySetters = false,
    ) {
        $this->defaultNamespace = $defaultNamespace ? \trim($defaultNamespace, '\\') : null;
        $this->defaultDirectory = \rtrim($defaultDirectory, '/');

        if ($readonlyProperties && $propertySetters) {
            \trigger_error("Setters cannot be written for readonly properties.", E_USER_WARNING);
            $this->propertySetters = false;
        } else {
            $this->propertySetters = $propertySetters;
        }
    }

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

    public function resolvePhpTypeName(string $name, string $namespace): string
    {
        $target = $namespace . '/' . $name;

        // First, try with fully qualified name.
        if ($found = ($this->nameMap['static'][$target] ?? null)) {
            return $found;
        }

        $phpNamespace = \trim($this->resolveNamespace($namespace), '\\');
        $className = $this->convertName($name);
        $target = $phpNamespace . '\\' . $className;

        // Then try with default generated fully qualified PHP name.
        if ($found = ($this->nameMap['static'][$target] ?? null)) {
            return $found;
        }

        // Then using PHP namespace.
        if ($found = ($this->nameMap[$phpNamespace][$name] ?? null)) {
            return $found;
        }

        // Then using XML namespace.
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
}
