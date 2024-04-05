<?php

declare(strict_types=1);

namespace MakinaCorpus\SoapGenerator\Writer;

use MakinaCorpus\SoapGenerator\Error\TypeDoesNotExistError;
use MakinaCorpus\SoapGenerator\Error\WriterError;
use MakinaCorpus\SoapGenerator\GeneratorConfig;
use MakinaCorpus\SoapGenerator\Reader\Property;
use MakinaCorpus\SoapGenerator\Reader\RemoteType;
use MakinaCorpus\SoapGenerator\Reader\TypeRegistry;
use PhpParser\BuilderFactory;
use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard;
use Symfony\Component\Filesystem\Filesystem;

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
        foreach ($this->types->getAllTypes() as $type) {
            if (!$type->scalar) {
                $this->writeType($type);
            }
        }
    }

    /**
     * Write a single type, all its dependencies must be in the registry.
     */
    public function writeType(RemoteType $type): void
    {
        if ($type->scalar) {
            throw new WriterError(\sprintf("%s: cannot write a scalar type", $type));
        }

        list($phpNamespace, $phpClassName) = $this->expandPhpTypeName($type->name, $type->namespace);

        if (!$phpNamespace) {
            throw new WriterError(\sprintf("%s: using a PHP class without namespace is not supported yet", $type->toString()));
        }

        $factory = new BuilderFactory();
        $class = $factory->class($phpClassName);
        $namespace = $factory->namespace($phpNamespace);
        $lateClassStatements = [];

        // Deal with class extension.
        if ($type->extends) {
            try {
                list($extendedNamespace, $extendedClassName) = $this->expandPhpTypeName($type->extends);
                if ($extendedNamespace !== $phpNamespace) {
                    $namespace->addStmt($factory->use($extendedNamespace . '\\' . $extendedClassName));
                }
                $class->extend($extendedClassName);
            } catch (TypeDoesNotExistError $e) {
                if (!$this->config->ignoreMissingTypes) {
                    throw $e;
                }
                \trigger_error($e->getMessage(), \E_USER_WARNING);
            }
        }

        // Build up a constructor.
        $constructorStatement = $factory->method('__construct');
        if ($this->config->constructor) {
            $constructorStatement->makePublic();
        } else {
            $constructorStatement->makeProtected();
        }

        // Build properties.
        foreach ($type->properties as $property) {
            \assert($property instanceof Property);

            $propertyName = $property->name;
            if ($this->config->camelCaseProperties) {
                $propertyName = \lcfirst($propertyName);
            }

            try {
                list($propTypeNamespace, $propType) = $this->expandPhpTypeName($property->type);

                if ('scalar' === $propTypeNamespace) {
                    $propType = $this->expandScalarType($propType);
                } else if ($propTypeNamespace) {
                    if ($propTypeNamespace !== $phpNamespace) {
                        $namespace->addStmt($factory->use($propTypeNamespace . '\\' . $propType));
                    }
                }

                $propertyStatement = $factory->property($propertyName);

                if ($this->config->publicProperties) {
                    $propertyStatement->makePublic();
                } else {
                    $propertyStatement->makePrivate();
                }
                if ($this->config->readonlyProperties) {
                    $propertyStatement->makeReadonly();
                }

                $propertyTypeString = $property->nullable ? ('null|' . $propType) : $propType;
                if ($property->collection) {
                    $propertyStatement->setDocComment(\sprintf('/** @var %s[] */', $propType));
                    $propertyTypeString = 'array';
                }
                $propertyStatement->setType($propertyTypeString);

                if ($this->config->propertyGetters) {
                    $getterStatement = $factory
                        ->method('get' . \ucfirst($propertyName))
                        ->setReturnType($propertyTypeString)
                        ->makePublic()
                        ->addStmt(
                            new Node\Stmt\Return_(
                                $factory->propertyFetch(
                                    new Node\Expr\Variable('this'),
                                    $propertyName,
                                )
                            )
                        )
                    ;
                    if ($property->collection) {
                        $getterStatement->setDocComment(\sprintf('/** @return %s[] */', $propType));
                    }
                    $lateClassStatements[] = $getterStatement;
                }

                if ($this->config->propertySetters) {
                    $getterStatement = $factory
                        ->method('set' . \ucfirst($propertyName))
                        ->setReturnType($propertyTypeString)
                        ->makePublic()
                        ->addParam(
                            $factory
                                ->param('value')
                                ->setType($propertyTypeString)
                        )
                        ->addStmt(
                            new Node\Expr\Assign(
                                $factory->propertyFetch(
                                    new Node\Expr\Variable('this'),
                                    $propertyName
                                ),
                                new Node\Expr\Variable('value'),
                            ),
                        )
                    ;
                    if ($property->collection) {
                        $getterStatement->setDocComment(\sprintf('/** @return %s[] */', $propType));
                    }
                    $lateClassStatements[] = $getterStatement;
                }

                // Add property to constructor statement.
                $constructorStatement->addParam(
                    $factory
                        ->param($propertyName)
                        ->setType($propertyTypeString)
                );
                $constructorStatement->addStmt(
                    new Node\Expr\Assign(
                        $factory->propertyFetch(
                            new Node\Expr\Variable('this'),
                            $propertyName
                        ),
                        new Node\Expr\Variable($propertyName),
                    ),
                );

                $class->addStmt($propertyStatement);

            } catch (TypeDoesNotExistError $e) {
                if (!$this->config->ignoreMissingTypes) {
                    throw $e;
                }
                \trigger_error($e->getMessage(), \E_USER_WARNING);
            }
        }

        $class->addStmts([$constructorStatement, ...$lateClassStatements]);
        $namespace->addStmt($class);

        // Ensure directory and file.
        $targetFilename = $this->config->resolveFileName($phpNamespace . '\\' . $phpClassName);
        $filesystem = new Filesystem();
        $filesystem->mkdir(\dirname($targetFilename));

        $prettyPrinter = new Standard();
        \file_put_contents($targetFilename, $prettyPrinter->prettyPrintFile([$namespace->getNode()]));
    }

    /**
     * Expand scalar types to PHP types.
     *
     * @todo
     *   Allow user to override this.
     */
    private function expandScalarType(string $type): string
    {
        return match ($type) {
            'string' => 'string',
            'normalizedString' => 'string',
            'duration' => '\\' . \DateInterval::class,
            'dateTime' => '\\' . \DateTimeImmutable::class,
            'date' => '\\' . \DateTimeImmutable::class,
            'time' => 'string',
            'gYear' => 'int',
            'gYearMonth' => 'int',
            'gMonth' => 'int',
            'gMonthDay' => 'int',
            'gDay' => 'int',
            'boolean' => 'bool',
            'NOTATION' => 'string',
            'Qname' => 'string',
            'anyURI' => 'string',
            'base64Binary' => 'string',
            'hexBinary' => 'string',
            'float' => 'float',
            'double' => 'float',
            'decimal ' => 'float',
            default => $type,
        };
    }

    /**
     * Expand XSD type to PHP type using the registry and the user configuration.
     */
    private function expandPhpTypeName(string $name, ?string $namespace = null): array
    {
        // Explode type such as "XML_NAMESPACE:TYPE_NAME"
        if (!$namespace && ($pos = \strrpos($name, ':'))) {
            $namespace = \substr($name, 0, $pos);
            $name = \substr($name, $pos + 1);
        }

        if ('xsd' === $namespace) {
            return ['scalar', $name];
        }

        if (!$this->types->hasType($name, $namespace)) {
            throw new TypeDoesNotExistError(\sprintf("%s:%s: type does not exists", $namespace, $name));
        }

        $type = $this->types->getType($name, $namespace);
        if ($type->scalar) {
            return ['scalar', $type->scalar];
        }

        $phpNamespace = null;
        $phpClassName = $this->config->resolvePhpTypeName($name, $namespace);

        if ($pos = \strrpos($phpClassName, '\\')) {
            $phpNamespace = \substr($phpClassName, 0, $pos);
            $phpClassName = \substr($phpClassName, $pos + 1);
        }

        return [$phpNamespace, $phpClassName];
    }
}
