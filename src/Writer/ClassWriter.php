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
use MakinaCorpus\SoapGenerator\Type\SimpleType;

class ClassWriter
{
    /**
     * Write a single type, all its dependencies must be in the registry.
     */
    public function writeClass(WriterContext $context, ComplexType $type): void
    {
        if (!$phpNs = $type->getPhpNamespace()) {
            throw new WriterError(\sprintf("%s: using a PHP class without namespace is not supported yet", $type->id->toString()));
        }

        $factory = new BuilderFactory();
        $classStmt = $factory->class($type->getPhpLocalName());
        $namespaceStmt = $factory->namespace($phpNs);
        $lateClassStmts = [];
        $inheritedProperties = [];

        // Deal with class extension.
        if ($type->extends) {
            try {
                $parentType = $context->findType($type->extends);
                $parentPhpNs = $parentType->getPhpNamespace();
                $parentPhpName = $parentType->getPhpLocalName();

                if ($parentPhpNs !== $phpNs) {
                    $namespaceStmt->addStmt($factory->use($parentPhpNs . '\\' . $parentPhpName));
                }
                $classStmt->extend($parentPhpName);

                $inheritedProperties = $this->aggregateInheritedProps($context, $type, $parentType);
            } catch (TypeDoesNotExistError $e) {
                if (!$context->config->ignoreMissingTypes) {
                    throw $e;
                }
                \trigger_error($e->getMessage(), \E_USER_WARNING);
            }
        }

        // Build up a constructor.
        $ctorStmt = $factory->method('__construct');
        if ($context->config->constructor) {
            $ctorStmt->makePublic();
        } else {
            $ctorStmt->makeProtected();
        }

        $arrayConstructorArgs = [];

        // Put all properties from parents in constructor.
        if ($inheritedProperties) {
            foreach ($inheritedProperties as $prop) {
                \assert($prop instanceof ComplexTypeProperty);

                // @todo add to constructor
                // @todo add to parent constructor
            }
        }

        // Build properties.
        foreach ($type->properties as $property) {
            \assert($property instanceof ComplexTypeProperty);

            try {
                list($propName, $phpType, $phpTypeStr) = $this->registerProperty(
                    $context,
                    $type,
                    $phpNs,
                    $property,
                    $factory,
                    $classStmt,
                    $namespaceStmt,
                    $ctorStmt,
                    false,
                );

                if ($context->config->propertyGetters) {
                    $getterStmt = $factory
                        ->method('get' . \ucfirst($propName))
                        ->setReturnType($phpTypeStr)
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
                    if ($property->collection) {
                        $getterStmt->setDocComment(\sprintf('/** @return %s[] */', $phpType));
                    }
                    $lateClassStmts[] = $getterStmt;
                }

                if ($context->config->propertySetters) {
                    $getterStmt = $factory
                        ->method('set' . \ucfirst($propName))
                        ->setReturnType('void')
                        ->makePublic()
                        ->addParam(
                            $factory
                                ->param('value')
                                ->setType($phpTypeStr)
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
                    if ($property->collection) {
                        $getterStmt->setDocComment(\sprintf('/** @return %s[] */', $phpType));
                    }
                    $lateClassStmts[] = $getterStmt;
                }

                if ($context->config->arrayHydrator) {
                    // @todo
                    //   if parent is class, call class constructor
                    //   if array, call normalization function
                    $arrayConstructorArgs[$propName] = new Node\Expr\BinaryOp\Coalesce(
                        new Node\Expr\ArrayDimFetch(
                            new Node\Expr\Variable('values'),
                            new Node\Scalar\String_($property->name),
                        ),
                        // @todo
                        //   if nullable, throw instead of null
                        $factory->val(null)
                    );
                }
            } catch (TypeDoesNotExistError $e) {
                if (!$context->config->ignoreMissingTypes) {
                    throw $e;
                }
                \trigger_error($e->getMessage(), \E_USER_WARNING);
            }
        }

        if ($context->config->arrayHydrator) {
            $classStmt->addStmt(
                $factory
                    ->method('fromArray')
                    ->makeStatic()
                    ->makePublic()
                    ->addParam($factory->param('values')->setType('array'))
                    ->setReturnType('self')
                    ->addStmt($factory->new('self', $arrayConstructorArgs))
            );
            // @todo if inherited, call parent
            // $arrayHydratorStmts
        }

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
     */
    private function aggregateInheritedProps(
        WriterContext $context,
        ComplexType $type,
        AbstractType $parentType
    ): array {
        if (!$parentType instanceof ComplexType) {
            throw new WriterError(\sprintf("%s: parent type %s is not a complex type", $type->id->toString(), $parentType->id->toString()));
        }

        if ($parentType->extends) {
            $ret = $this->aggregateInheritedProps($context, $type, $context->findType($parentType->extends));
        } else {
            $ret = [];
        }

        foreach ($parentType->properties as $prop) {
            \assert($prop instanceof ComplexTypeProperty);

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
     * @return string[]
     *   [propName, phpType, phpTypeName]
     */
    private function registerProperty(
        WriterContext $context,
        ComplexType $type,
        string $phpNamespace,
        ComplexTypeProperty $property,
        BuilderFactory $factory,
        Builder\Class_ $classStmt,
        Builder\Namespace_ $namespaceStmt,
        Builder\Method $ctorStmt,
        bool $inherited,
    ): array {
        $propName = $property->getPhpName();
        $propType = $context->findType($property->type);
        $propTypeNs = $propType->getPhpNamespace();

        if ($propType instanceof SimpleType) {
            $phpType = $context->convertXsdScalarToPhp($propType->type);
        } else if ($propTypeNs && $propTypeNs !== $phpNamespace) {
            $phpType = $propType->getPhpLocalName();
            $namespaceStmt->addStmt($factory->use($propTypeNs . '\\' . $phpType));
        } else {
            $phpType = $propType->getPhpLocalName();
        }

        // propType is the canonical type (eg. "string", "int")
        // propTypeString is what we write (eg. "?string", "array")
        // propDocString is what PHP can't do that we document (eg. "?string", "int[]")
        // If nullable, simply put "?" in front of type. We don't care
        // about union types, they simply don't seem to exist in WSDL
        // and XSD documentation.
        $propDocStr = null;
        $phpTypeStr = $property->nullable ? ('?' . $phpType) : $phpType;
        if ($property->collection) {
            // We ignore nullable status for collections, we always can
            // put an empty array instead, and that's fine.
            $propDocStr = \sprintf('/** @var %s[] */', $phpType);
            $phpTypeStr = 'array';
        }

        if ($inherited) {
            // @todo add as param to constructor
            //    then as arg to parent constructor
            throw new \Exception("Not implemented yet");
        } else if ($context->config->propertyPromotion) {
            $ctorParam = $factory->param($propName)->setType($phpTypeStr);

            if ($context->config->publicProperties) {
                $ctorParam->makePublic();
            } else {
                $ctorParam->makePrivate();
            }

            if ($propDocStr) {
                // $ctorParam ?
            }

            // Properties are constructor promoted.
            $ctorStmt->addParam($ctorParam);

        } else {
            $propStmt = $factory->property($propName);

            if ($context->config->publicProperties) {
                $propStmt->makePublic();
            } else {
                $propStmt->makePrivate();
            }
            if ($context->config->readonlyProperties) {
                $propStmt->makeReadonly();
            }
            if ($propDocStr) {
                $propStmt->setDocComment($propDocStr);
            }
            $propStmt->setType($phpTypeStr);
            $classStmt->addStmt($propStmt);

            // Add property to constructor statement.
            $ctorStmt->addParam(
                $factory
                    ->param($propName)
                    ->setType($phpTypeStr)
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

        return [$propName, $phpType, $phpTypeStr];
    }
}
