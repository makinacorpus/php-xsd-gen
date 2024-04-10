<?php

declare(strict_types=1);

namespace MakinaCorpus\SoapGenerator\Writer;

use MakinaCorpus\SoapGenerator\Error\TypeDoesNotExistError;
use MakinaCorpus\SoapGenerator\Error\WriterError;
use MakinaCorpus\SoapGenerator\Type\AbstractType;
use MakinaCorpus\SoapGenerator\Type\ComplexType;
use MakinaCorpus\SoapGenerator\Type\ComplexTypeProperty;
use PhpParser\Builder;
use PhpParser\BuilderFactory;
use PhpParser\Node;
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

        $arrayConstructorArgs = [];
        $inheritedProperties = [];
        $lateClassStmts = [];
        $metaArrayValues = [];
        $parentCtorArgs = [];
        $parentType = null;

        // Deal with class extension.
        if ($type->extends) {
            try {
                $parentType = $context->getType($type->extends);
                $parentPhpNs = $parentType->getPhpNamespace();
                $parentPhpName = $parentType->getPhpLocalName();

                if ($parentPhpNs !== $phpNs) {
                    $namespaceStmt->addStmt($factory->use($parentPhpNs . '\\' . $parentPhpName));
                }
                $classStmt->extend($parentPhpName);

                $inheritedProperties = $this->aggregateInheritedProps($context, $type, $parentType);
            } catch (TypeDoesNotExistError $e) {
                if (!$context->config->errorWhenTypeMissing) {
                    throw $e;
                }
                $context->logErr('{type}: cannot inherit from {parent}: type is missing', ['type' => $type->toString(), 'parent' => $type->extends->toString()]);
            }
        }

        // Build up a constructor.
        $ctorStmt = $factory->method('__construct');
        if ($context->config->classConstructor) {
            $ctorStmt->makePublic();
        } else {
            $ctorStmt->makeProtected();
        }

        // Put all properties from parents in constructor.
        if ($parentType && $inheritedProperties) {
            foreach ($inheritedProperties as $prop) {
                \assert($prop instanceof ComplexTypeProperty);

                $propName = $prop->getPhpName();

                $ctorStmt->addParam($factory->param($propName)->setType($prop->getPhpType()));
                $parentCtorArgs[$propName] = new Node\Expr\Variable($propName);

                // Add to array hydrator, if any.
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
        }

        // Build properties.
        foreach ($type->properties as $prop) {
            \assert($prop instanceof ComplexTypeProperty);

            if (!$prop->resolved) {
                continue; // Property skipped by resolution.
            }

            $propName = $prop->getPhpName();

            $this->propertyRegister(
                $context,
                $type,
                $phpNs,
                $prop,
                $factory,
                $classStmt,
                $namespaceStmt,
                $ctorStmt,
            );

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
                    ->makeStatic()
                    ->makePublic()
                    ->addParam($factory->param('values')->setType('array|self'))
                    ->setReturnType('self')
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
        if ($parentType) {
            $ctorStmt->addStmt($factory->staticCall(new Node\Name('parent'), '__construct', $parentCtorArgs));
        }

        // Meta data method for hydrators and extractors.
        $classStmt->addStmt(
            $factory
                ->method('propertyMetadata')
                ->makeStatic()
                ->makePublic()
                ->setDocComment('/** @internal */')
                ->setReturnType('array')
                ->addStmt(
                    new Node\Stmt\Return_(
                        new Node\Expr\Array_(
                            $metaArrayValues,
                        ),
                    )
                )
        )
        ;

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
     * Recursively aggregate inherited properties of parents.
     *
     * Removes properties that are shadowed by the type we are generating.
     *
     * @return ComplexTypeProperty[]
     */
    private function aggregateInheritedProps(
        WriterContext $context,
        ComplexType $type,
        AbstractType $parentType
    ): array {
        if (!$parentType instanceof ComplexType) {
            throw new WriterError(\sprintf("%s: parent type %s is not a complex type", $type->toString(), $parentType->toString()));
        }

        if ($parentType->extends) {
            $ret = $this->aggregateInheritedProps($context, $type, $context->getType($parentType->extends));
        } else {
            $ret = [];
        }

        foreach ($parentType->properties as $prop) {
            \assert($prop instanceof ComplexTypeProperty);

            if (!$prop->resolved) {
                continue; // Property skipped by resolution.
            }

            if ($type->propertyExists($prop->name)) {
                // Property is shadowed.
                continue;
            }

            // Since we order properties from the deepest ancestor toward
            // the closest one, shadowing properties will happen in natural
            // order among parents. We only need to remove properties
            // shadowed by the class we are generating.
            $ret[$prop->name] = $prop;
        }

        return $ret;
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
        Builder\Namespace_ $namespaceStmt,
        Builder\Method $ctorStmt,
    ): void {
        $propName = $prop->getPhpName();
        $phpType = $prop->getPhpType();
        $phpValueType = $prop->getPhpValueType();
        $phpValueTypeNs = $prop->getPhpValueTypeNamespace();

        if (!$prop->isPhpTypeBuiltIn() && $phpValueTypeNs && $phpValueTypeNs !== $classNs) {
            $namespaceStmt->addStmt($factory->use($phpValueTypeNs . '\\' . $phpValueType));
        }

        // If nullable, simply put "?" in front of type. We don't care
        // about union types, they simply don't seem to exist in WSDL
        // and XSD documentation.
        $propDocStr = null;
        if ($prop->collection) {
            // We ignore nullable status for collections, we always can
            // put an empty array instead, and that's fine.
            $propDocStr = \sprintf('/** @var %s[] */', $phpValueType);
        }

        if ($context->config->propertyPromotion) {
            $ctorParam = $factory->param($propName)->setType($phpType);

            if ($context->config->propertyPublic) {
                $ctorParam->makePublic();
            } else {
                $ctorParam->makePrivate();
            }

            if ($propDocStr) {
                // @todo
                // $ctorParam ?
                // Adding a PHP-doc over a param is a case that is not
                // implemented by the builder, they probably should then
                // be written as @param over the constructor method
                // itself.
            }

            // Properties are constructor promoted.
            $ctorStmt->addParam($ctorParam);

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
            $stmt->setDocComment(\sprintf('/** @param %s[] \$%s*/', $phpValueType, $propName));
        }

        return $stmt;
    }
}
