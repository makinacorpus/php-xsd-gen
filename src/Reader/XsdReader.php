<?php

declare(strict_types=1);

namespace MakinaCorpus\SoapGenerator\Reader;

use MakinaCorpus\SoapGenerator\Type\AbstractType;
use MakinaCorpus\SoapGenerator\Type\ComplexType;
use MakinaCorpus\SoapGenerator\Type\ComplexTypeProperty;
use MakinaCorpus\SoapGenerator\Type\SimpleType;

class XsdReader extends AbstractReader
{
    #[\Override]
    protected function root(ReaderContext $context): void
    {
        $context
            ->expect('wsdl:definitions', $this->definitions(...))
            ->expect('wsdl:types', $this->types(...))
            ->expect('xsd:import', $this->import(...))
            ->expect('xsd:schema', $this->schema(...))
            ->expect('xsd:types', $this->types(...))
        ;
    }

    protected function definitions(ReaderContext $context, \DOMElement $element): void
    {
        $context
            ->expect('wsdl:types', $this->types(...))
            ->expect('xsd:import', $this->import(...))
            ->expect('xsd:schema', $this->schema(...))
            ->expect('xsd:types', $this->types(...))
        ;
    }

    protected function types(ReaderContext $context, \DOMElement $element): void
    {
        $context
            ->expect('xsd:import', $this->import(...))
            ->expect('xsd:schema', $this->schema(...))
        ;
    }

    protected function schema(ReaderContext $context, \DOMElement $element): void
    {
        $context
            ->expect('xsd:complexType', $this->complexType(...))
            ->expect('xsd:element', $this->element(...))
            ->expect('xsd:import', $this->import(...))
            ->expect('xsd:simpleType', $this->simpleType(...))
        ;
    }

    protected function import(ReaderContext $context, \DOMElement $element): void
    {
        if (!$namespace = $this->attrRequired($element, 'namespace')) {
            return;
        }

        $context->import($namespace, $this->attr($element, 'schemaLocation'));
    }

    protected function annotation(ReaderContext $context, \DOMElement $element, mixed $object): void
    {
        if ($object instanceof AbstractType) {
            $object->setAnnotation($element->textContent);
        } else if ($object instanceof ComplexTypeProperty) {
            $object->setAnnotation($element->textContent);
        } else {
            $context->logWarn("Unsupported annotation at path '{path}'", ['path' => $element->getNodePath() ?? 'unknown']);
        }
    }

    protected function element(ReaderContext $context, \DOMElement $element, ?string $name = null): void
    {
        // <element> (type)
        //   [name=TYPE_NAME]
        //   <annotation>
        //     <documentation>TYPE_DESCRIPTION
        //   <complexType>

        // Name can be null it will crash later if we really miss it while
        // parsing the complex type under if any.
        $name ??= $this->attrOdDie($element, 'name');

        $context
            // @todo ->expect('xsd:annotation', $this->annotation())
            ->expect('xsd:complexType', $this->complexType(...), $name)
            ->expect('xsd:simpleType', $this->simpleType(...), $name)
        ;
    }

    protected function simpleType(ReaderContext $context, \DOMElement $element, ?string $name = null): void
    {
        // <simpleType (name=TYPE_NAME)>
        //   <restriction>
        //     ...

        $name ??= $this->attrOdDie($element, 'name');
        $id = $context->createTypeId($name);

        $type = $context->addScalarType($id, 'string');

        $context
            ->expect('xsd:annotation', $this->annotation(...), $type)
        ;

        // @todo Deal with simple types.
    }

    /**
     * Read a single type definition.
     */
    protected function complexType(ReaderContext $context, \DOMElement $element, ?string $name = null): void
    {
        // <complexType (name=TYPE_NAME)>
        //   <complexContent>
        //     <extension base="TYPE_NAME">
        //       <sequence>
        //         <element> (property)
        //           [name=PROP_NAME]
        // OR
        // <complexType (name=TYPE_NAME)>
        //   <complexContent>
        //     <restriction base="TYPE_NAME">
        //       <sequence>
        //         <element> (property)
        //           [name=PROP_NAME]
        // OR
        // <complexType (name=TYPE_NAME)>
        //   <sequence>
        //     <element> (property)
        //       [name=PROP_NAME]

        // @todo handle abstract=BOOL attribute.
        // @todo handle annotation
        // @todo
        //   xsd:extension can yield a sequence in it
        //   case in which it overrides the types.
        //    for implementing this: parent properties need to be private
        //    and child properties need to shadow parent's

        $type = new ComplexType(
            $context->createTypeId(
                $name ?? $this->attrOdDie($element, 'name')
            )
        );

        $context->setType($type);

        $context
            ->expect('xsd:annotation', $this->annotation(...), $type)
            ->expect('xsd:complexContent', $this->complexContent(...),  $type)
            ->expect('xsd:sequence', $this->sequence(...), $type)
        ;
    }

    protected function complexTypeAnnotation(ReaderContext $context, \DOMElement $element, SimpleType $type): void
    {
        $type->setAnnotation($element->textContent);
    }

    protected function complexContent(ReaderContext $context, \DOMElement $element, ComplexType $type): void
    {
        // <complexContent>
        //   <extension base="TYPE_NAME">
        // OR
        // <complexContent>
        //   <restriction base="TYPE_NAME">

        $context
            ->expect('xsd:extension', $this->extension(...), $type)
            ->expect('xsd:restriction', $this->restriction(...),  $type)
        ;
    }

    protected function extension(ReaderContext $context, \DOMElement $element, ComplexType $type): void
    {
        // <extension base="TYPE_NAME">
        //   <sequence>

        if ($typeName = $this->attr($element, 'base')) {
            $extendsId = $context->createTypeId($typeName);

            if ('xsd' === $extendsId->namespace) {
                $context->logWarn("{type}: complex type cannot extend (via xsd:extension) a scalar type", ['type' => $type]);
            } else {
                // Enforce type resolution.
                $context->getType($extendsId);
                $type->setInheritedType($extendsId);
            }
        }

        $context
            ->expect('xsd:annotation', $this->annotation(...), $type)
            ->expect('xsd:sequence', $this->sequence(...), $type)
        ;
    }

    protected function restriction(ReaderContext $context, \DOMElement $element, ComplexType $type): void
    {
        if ($typeName = $this->attr($element, 'base')) {
            $extendsId = $context->createTypeId($typeName);

            if ('xsd' === $extendsId->namespace) {
                $context->logWarn("{type}: complex type cannot extend (via xsd:restriction) a scalar type", ['type' => $type]);
            } else {
                // Enforce type resolution.
                $context->getType($extendsId);
                $type->setInheritedType($extendsId);
            }
        }

        $context
            ->expect('xsd:annotation', $this->annotation(...), $type)
            ->expect('xsd:sequence', $this->sequence(...), $type)
        ;
    }

    protected function sequence(ReaderContext $context, \DOMElement $element, ComplexType $type): void
    {
        // <element> (property)
        //   [name=PROP_NAME]
        //   [type=TYPE_NAME]
        //   [minOccurs=INT]
        //   [maxOccurs=INT|"unbounded"]
        //   <annotation>
        //     <documentation>TYPE_DESCRIPTION
        //   ... properties from more general type.
        //
        // If [maxOccurs=1], then it's NOT a collection.
        // Else if [minOccurs] is present, it's a collection.

        $context
            ->expect('xsd:annotation', $this->annotation(...), $type)
            ->expect('xsd:element', function (ReaderContext $context, \DOMElement $element) use ($type) {
                if (!$name = $this->attrRequired($element, 'name')) {
                    $context->logErr("{type}: found empty property", ['type' => $type]);

                    return;
                }

                // nillable attribute is obsolete schemantics and probably
                // should not be used much. It is semantically equivalent
                // to minOccurs="0".
                $nullable = ('true' === $this->attr($element, 'nillable'));
                $collection = false;
                $typeId = null;

                if (null !== ($max = $this->attr($element, 'maxOccurs'))) {
                    if ("unbounded" === $max) {
                        $max = null;
                        $collection = true;
                    } else {
                        $max = (int) $max;
                        if (1 < $max) {
                            $collection = true;
                        }
                    }
                }

                if (null !== ($min = $this->attr($element, 'minOccurs'))) {
                    $min = (int) $min;
                    if ($min < 1) {
                        $nullable = true;
                    } else if (1 < $min) {
                        $collection = true;
                    }
                }

                if ($typeName = $this->attr($element, 'type')) {
                    $typeId = $context->createTypeId($typeName);
                    if ('xsd' === $typeId->namespace) {
                        $context->addScalarType($typeId);
                    } else {
                        // Enforce type resolution.
                        $context->getType($typeId);
                    }
                } else {
                    // When dealing with a complex type we need to name it using
                    // a generated reproducible name. For now, simply take the
                    // parent type name and suffix using the property name.
                    // Since that types are unique in the same namespace, we
                    // should not experience any conflicting names.
                    $typeId = $context->createTypeId($type->id->name . '_' . $name);

                    $context
                        ->expect('xsd:annotation', fn () => null)
                        ->expect('xsd:complexType', $this->complexType(...), $typeId->name)
                        ->expect('xsd:simpleType', $this->simpleType(...), $typeId->name)
                    ;
                }

                $property = new ComplexTypeProperty(
                    parent: $type->id,
                    name: $name,
                    type: $typeId,
                    collection: $collection,
                    minOccur: $collection ? ($min ? $min : 0) : ($min ? 1 : 0),
                    maxOccur: $max,
                    nullable: $nullable,
                );
                $type->setProperty($property);

                $context
                    ->expect('xsd:annotation', $this->annotation(...), $property)
                ;
            })
        ;
    }
}
