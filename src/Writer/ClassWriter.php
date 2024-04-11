<?php

declare(strict_types=1);

namespace MakinaCorpus\XsdGen\Writer;

use MakinaCorpus\XsdGen\Error\WriterError;
use MakinaCorpus\XsdGen\Type\ComplexType;
use MakinaCorpus\XsdGen\Type\ComplexTypeProperty;
use PhpParser\Builder;
use PhpParser\BuilderFactory;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Comment\Doc;
use PhpParser\PrettyPrinter\Standard;
use Symfony\Component\Filesystem\Filesystem;

class ClassWriter
{
    /**
     * Write a single type, all its dependencies must be in the registry.
     */
    public function writeClass(WriterContext $context, ComplexType $type): void
    {
        if (!$phpNs = $type->getPhpNamespace()) {
            throw new WriterError(\sprintf("%s: using a PHP class without namespace is not supported yet", $type->toString()));
        }

        $factory = new BuilderFactory();

        $classPhpType = $type->getPhpLocalName();
        $classStmt = $factory->class($classPhpType);
        $namespaceStmt = $factory->namespace($phpNs);
        $uses = new UseDeduplicator();

        $arrayConstructorArgs = [];
        $inheritedProperties = $type->getInheritedProperties();
        $lateClassStmts = [];
        $metaArrayValues = [];
        $parentCtorArgs = [];

        // Deal with class extension.
        if ($parentPhpName = $type->getParentPhpLocalName()) {
            $parentPhpNs = $type->getParentPhpNamespace();
            if ($parentPhpNs !== $phpNs) {
                $uses->addUse($parentPhpNs, $parentPhpName);
            }
            $classStmt->extend($parentPhpName);
        }

        // Build up a constructor.
        $ctorStmt = $factory->method('__construct');
        if ($context->config->classConstructor) {
            $ctorStmt->makePublic();
        } else {
            $ctorStmt->makeProtected();
        }

        // Put all properties from parents in constructor.
        if ($inheritedProperties) {
            foreach ($inheritedProperties as $prop) {
                \assert($prop instanceof ComplexTypeProperty);

                if (!$prop->resolved) {
                    continue;
                }

                $propName = $prop->getPhpName();
                $propertyIsShadowed = $type->propertyExists($prop->name);

                if (!$prop->isPhpTypeBuiltIn() && ($propPhpNs = $prop->getPhpValueTypeNamespace()) && $propPhpNs !== $phpNs) {
                    $uses->addUse($propPhpNs, $prop->getPhpValueType());
                }

                // Constructor argument will already be there when not using CPP
                // because in that case, we keep the most specific version of the
                // argument.
                // @see Writer::resolveInheritanceProps() for a more detailed
                // explanation.
                if (!$propertyIsShadowed || $context->config->propertyPromotion) {
                    $ctorStmt->addParam($factory->param($propName)->setType($prop->getPhpType()));
                }
                $parentCtorArgs[$propName] = new Node\Expr\Variable($propName);

                if ($context->config->classFactoryMethod) {
                    $arrayConstructorArgs[$propName] = $this->propertyHydratorArgument($context, $prop, $factory, $classPhpType);
                }

                // Same as constructor, if property already exists it will
                // be handled by type own properties.
                if (!$propertyIsShadowed) {
                    $metaArrayValues[] = new Node\ArrayItem(
                        new Node\Expr\Array_([
                            $factory->val($prop->getPhpTypeMeta()),
                            $factory->val($prop->collection),
                        ]),
                        $factory->val($propName),
                    );
                }
            }
        }

        // Build properties.
        foreach ($type->properties as $prop) {
            \assert($prop instanceof ComplexTypeProperty);

            if (!$prop->resolved) {
                $context->logWarn("{prop}: skipping property: was not resolved or dropped", ['prop' => $prop]);
                continue;
            }

            $propName = $prop->getPhpName();

            $this->propertyRegister($context, $type, $phpNs, $prop, $factory, $classStmt, $uses, $ctorStmt);

            if ($context->config->propertyGetters) {
                $lateClassStmts[] = $this->propertyGetter($context, $prop, $factory);
            }

            if ($context->config->propertySetters) {
                $lateClassStmts[] = $this->propertySetter($context, $prop, $factory);
            }

            if ($context->config->classFactoryMethod) {
                $arrayConstructorArgs[$propName] = $this->propertyHydratorArgument($context, $prop, $factory, $classPhpType);
            }

            $metaArrayValues[] = new Node\ArrayItem(
                new Node\Expr\Array_([
                    $factory->val($prop->getPhpTypeMeta()),
                    $factory->val($prop->collection),
                ]),
                $factory->val($propName),
            );
        }

        if ($context->config->classFactoryMethod) {
            $classStmt->addStmt(
                $factory
                    ->method('create')
                    ->setDocComment(
                        <<<EOT
                        /**
                         * @internal Hydrator for XML or array exchange.
                         */
                        EOT
                    )
                    ->makeStatic()
                    ->makePublic()
                    ->addParam($factory->param('values')->setType('array|object'))
                    ->setReturnType('self')
                    // @todo Fix this, since we typed with "object",
                    //    we need to throw exception if not array after
                    //    first if.
                    ->addStmts([
                        new Node\Stmt\If_(
                            new Node\Expr\Instanceof_(
                                $factory->var('values'),
                                new Node\Name('self'),
                            ),
                            [
                                'stmts' => [
                                    new Node\Stmt\Return_(
                                        new Node\Expr\Variable('values')
                                    )
                                ],
                            ],
                        ),
                        new Node\Stmt\Return_(
                            $factory->new('self', $arrayConstructorArgs)
                        ),
                    ])
            );
        }

        // Add parent constructor call.
        if ($parentCtorArgs) {
            $ctorStmt->addStmt($factory->staticCall(new Node\Name('parent'), '__construct', $parentCtorArgs));
        }

        // Meta data method for hydrators and extractors.
        $classStmt->addStmt(
            $factory
                ->method('propertyMetadata')
                ->makeStatic()
                ->makePublic()
                ->setDocComment(
                    <<<EOT
                    /**
                     * @internal Property metadata for XML exchange.
                     */
                    EOT
                )
                ->setReturnType('array')
                ->addStmt(
                    new Node\Stmt\Return_(
                        new Node\Expr\Array_(
                            $metaArrayValues,
                        ),
                    )
                )
        );

        foreach ($uses->getAllUse() as $importedName) {
            $namespaceStmt->addStmt($factory->use($importedName));
        }

        // Finalize class.
        $classStmt->addStmts([$ctorStmt, ...$lateClassStmts]);
        $namespaceStmt->addStmt($classStmt);

        // Ensure directory and file.
        $targetFilename = $context->config->resolveFileName($phpNs . '\\' . $type->getPhpLocalName());
        $filesystem = new Filesystem();
        $filesystem->mkdir(\dirname($targetFilename));

        $prettyPrinter = new Standard();
        \file_put_contents($targetFilename, $prettyPrinter->prettyPrintFile([$namespaceStmt->getNode()]));
    }

    /**
     * Build create() argument for a given property.
     */
    private function propertyHydratorArgument(
        WriterContext $context,
        ComplexTypeProperty $prop,
        BuilderFactory $factory,
        string $classPhpType,
    ): Node\Expr {
        $propName = $prop->getPhpName();
        $propType = $context->getType($prop->type);
        $phpType = $prop->getPhpValueType();

        if ($prop->collection) {
            $defaultExpr = $factory->val([]);
        } else if ($prop->nullable) {
            $defaultExpr = $factory->val(null);
        } else {
            $defaultExpr = new Node\Expr\Throw_(
                $factory->new(
                    '\\' . \InvalidArgumentException::class,
                    [$factory->val(\sprintf('%s::\$%s property cannot be null', $classPhpType, $propName))]
                ),
            );
        }

        $arrayFetchExpr = new Node\Expr\ArrayDimFetch(
            new Node\Expr\Variable('values'),
            new Node\Scalar\String_($prop->name),
        );

        if ($propType instanceof ComplexType) {
            return new Node\Expr\Ternary(
                new Node\Expr\Isset_([
                    clone $arrayFetchExpr,
                ]),
                $factory->staticCall(
                    $phpType,
                    'create',
                    [$arrayFetchExpr],
                ),
                $defaultExpr,
            );
        }

        return new Node\Expr\BinaryOp\Coalesce($arrayFetchExpr, $defaultExpr);
    }

    /**
     * Register property in class.
     */
    private function propertyRegister(
        WriterContext $context,
        ComplexType $type,
        string $classNs,
        ComplexTypeProperty $prop,
        BuilderFactory $factory,
        Builder\Class_ $classStmt,
        UseDeduplicator $uses,
        Builder\Method $ctorStmt,
    ): void {
        $propName = $prop->getPhpName();
        $phpType = $prop->getPhpType();
        $phpValueType = $prop->getPhpValueType();
        $phpValueTypeNs = $prop->getPhpValueTypeNamespace();

        if (!$prop->isPhpTypeBuiltIn() && $phpValueTypeNs && $phpValueTypeNs !== $classNs) {
            $uses->addUse($phpValueTypeNs, $phpValueType);
        }

        // @todo Make something better for formatting.
        $propDocStr = null;
        if ($annotation = $prop->getAnnotation()) {
            $propDocStr = "/**\n * " . \implode("\n * ", \array_map('trim', \explode("\n", $annotation)));
        }

        // If nullable, simply put "?" in front of type. We don't care
        // about union types, they simply don't seem to exist in WSDL
        // and XSD documentation.
        if ($prop->collection) {
            // We ignore nullable status for collections, we always can
            // put an empty array instead, and that's fine.
            $phpDocType = $phpValueType . '[]';

            if ($propDocStr) {
                $propDocStr .= "\n * \n * @var " . $phpDocType . "\n */";
            } else {
                $propDocStr .= "/** @var " . $phpDocType . "\n */";
            }
        } else if ($propDocStr) {
            $propDocStr .= "\n */";
        }

        if ($context->config->propertyPromotion) {
            $flags = 0;
            if ($context->config->propertyPublic) {
                $flags |= Modifiers::PUBLIC;
            } else {
                $flags |= Modifiers::PRIVATE;
            }
            if ($context->config->propertyReadonly) {
                $flags |= Modifiers::READONLY;
            }

            $attributes = [];
            if ($propDocStr) {
                $attributes['comments'][] = new Doc($propDocStr);
            }

            $ctorStmt->addParam(
                new Node\Param(
                    attributes: $attributes,
                    flags: $flags,
                    type: new Node\Identifier($phpType),
                    var: $factory->var($propName),
                )
            );
        } else {
            $propStmt = $factory->property($propName);

            if ($context->config->propertyPublic) {
                $propStmt->makePublic();
            } else {
                $propStmt->makePrivate();
            }
            if ($context->config->propertyReadonly) {
                $propStmt->makeReadonly();
            }
            if ($propDocStr) {
                $propStmt->setDocComment($propDocStr);
            }
            $propStmt->setType($phpType);
            $classStmt->addStmt($propStmt);

            // Add property to constructor statement.
            $ctorStmt->addParam(
                $factory
                    ->param($propName)
                    ->setType($phpType)
            );

            $ctorStmt->addStmt(
                new Node\Expr\Assign(
                    $factory->propertyFetch(
                        new Node\Expr\Variable('this'),
                        $propName
                    ),
                    new Node\Expr\Variable($propName),
                ),
            );
        }
    }

    /**
     * Generate property getter method statement.
     */
    private function propertyGetter(
        WriterContext $context,
        ComplexTypeProperty $prop,
        BuilderFactory $factory,
    ): Builder\Method {
        $propName = $prop->getPhpName();
        $phpType = $prop->getPhpType();
        $phpValueType = $prop->getPhpValueType();

        $stmt = $factory
            ->method('get' . \ucfirst($propName))
            ->setReturnType($phpType)
            ->makePublic()
            ->addStmt(
                new Node\Stmt\Return_(
                    $factory->propertyFetch(
                        new Node\Expr\Variable('this'),
                        $propName,
                    )
                )
            )
        ;

        if ($prop->collection) {
            $stmt->setDocComment(\sprintf('/** @return %s[] */', $phpValueType));
        }

        return $stmt;
    }

    /**
     * Generate property setter method statement.
     */
    private function propertySetter(
        WriterContext $context,
        ComplexTypeProperty $prop,
        BuilderFactory $factory,
    ): Builder\Method {
        $propName = $prop->getPhpName();
        $phpType = $prop->getPhpType();
        $phpValueType = $prop->getPhpValueType();

        $stmt = $factory
            ->method('set' . \ucfirst($propName))
            ->setReturnType('void')
            ->makePublic()
            ->addParam(
                $factory
                    ->param('value')
                    ->setType($phpType)
            )
            ->addStmt(
                new Node\Expr\Assign(
                    $factory->propertyFetch(
                        new Node\Expr\Variable('this'),
                        $propName
                    ),
                    new Node\Expr\Variable('value'),
                ),
            )
        ;

        if ($prop->collection) {
            $stmt->setDocComment(\sprintf('/** @param %s[] $value*/', $phpValueType));
        }

        return $stmt;
    }
}
