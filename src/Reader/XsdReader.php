<?php

declare(strict_types=1);

namespace MakinaCorpus\SoapGenerator\Reader;

use MakinaCorpus\SoapGenerator\Error\ReaderError;

class XsdReader extends AbstractReader
{
    public function findAllTypes(): void
    {
        $context = $this->getRootContext();

        foreach ($this->document->childNodes as $child) {
            if ($this->elementIs($child, 'wsdl:definitions')) {
                $this->readDefinitions($context, $child);
            } else if ($this->elementIs($child, 'xsd:schema')) {
                $this->readSchema($context, $child);
            } else if ($this->elementIs($child, 'xsd:types')) {
                $this->readTypes($context, $child);
            } else {
                $this->elementUnexpected($child, 'document root');
            }
        }
    }

    /**
     * Read an <xsd:import> element.
     */
    protected function readImport(ReaderContext $context, \DOMElement $element): void
    {
        $this->elementCheck($element, 'xsd:import');

        $context = $this->processNamespaces($context, $element);

        if (!$namespace = $this->attributeRequired($element, 'namespace')) {
            return;
        }
        $schemaLocation = $this->attribute($element, 'schemaLocation');

        $context->import($namespace, $schemaLocation);
    }

    /**
     * Read an <wsql:definitions> element.
     */
    protected function readDefinitions(ReaderContext $context, \DOMElement $element): void
    {
        $this->elementCheck($element, 'wsdl:definitions');

        $context = $this->processNamespaces($context, $element);

        foreach ($element->childNodes as $child) {
            if ($this->elementIs($child, 'xsd:schema')) {
                $this->readSchema($context, $child);
            } else if ($this->elementIs($child, 'wsdl:types')) {
                $this->readTypes($context, $child);
            } else {
                $this->elementUnexpected($child, '<wsdl:definitions>');
            }
        }
    }

    /**
     * Read an <xsd:types> element.
     */
    protected function readTypes(ReaderContext $context, \DOMElement $element): void
    {
        $this->elementCheck($element, 'wsdl:types');

        $context = $this->processNamespaces($context, $element);

        foreach ($element->childNodes as $child) {
            if ($this->elementIs($child, 'xsd:schema')) {
                $this->readSchema($context, $child);
            } else {
                $this->elementUnexpected($child, '<xsd:types>');
            }
        }
    }

    /**
     * Read an <xsd:schema> element.
     */
    protected function readSchema(ReaderContext $context, \DOMElement $element): void
    {
        $this->elementCheck($element, 'xsd:schema');

        $context = $this->processNamespaces($context, $element);

        // Read attributes from import (xmlns:NAMESPACE=URI).

        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                if ($this->elementIs($child, 'xsd:element')) {
                    $this->readElement($context, $child);
                } else if ($this->elementIs($child, 'xsd:complexType')) {
                    $this->readComplexType($context, $child);
                } else if ($this->elementIs($child, 'xsd:simpleType')) {
                    $this->readSimpleType($context, $child);
                } else if ($this->elementIs($child, 'xsd:import')) {
                    $this->readImport($context, $child);
                } else {
                    $this->elementUnexpected($child, '<xsd:schema>');
                }
            }
        }
    }

    /**
     * Read a single type definition.
     */
    protected function readElement(ReaderContext $context, \DOMElement $element, ?string $name = null): ?RemoteType
    {
        $this->elementCheck($element, 'xsd:element');

        $context = $this->processNamespaces($context, $element);

        // <element> (type)
        //   [name=TYPE_NAME]
        //   <annotation>
        //     <documentation>TYPE_DESCRIPTION
        //   <complexType>

        // Name can be null, it will crash later if we really miss it while
        // parsing the complex type under if any.
        if (!$name && $element->hasAttribute('name')) {
            $name = (string) $element->getAttribute('name');
        }

        $ret = null;

        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                if ($this->elementIs($child, 'xsd:annotation')) {
                    \trigger_error("<xsd:annotation> is not implemented yet.", E_USER_WARNING);
                } else if ($this->elementIs($child, 'xsd:complexType')) {
                    $ret = $this->readComplexType($context, $child, $name);
                } else if ($this->elementIs($child, 'xsd:simpleType')) {
                    $ret = $this->readSimpleType($context, $child, $name);
                } else {
                    $this->elementUnexpected($child, '<xsd:element>');
                }
            }
        }

        return $ret;
    }

    /**
     * Read a single type definition.
     */
    protected function readSimpleType(ReaderContext $context, \DOMElement $element, ?string $name = null): ?RemoteType
    {
        $this->elementCheck($element, 'xsd:simpleType');

        $context = $this->processNamespaces($context, $element);

        // <simpleType (name=TYPE_NAME)>
        //   <restriction>
        //     ...

        if (!$name) {
            if ($element->hasAttribute('name')) {
                $name = (string) $element->getAttribute('name');
            } else {
                throw new ReaderError('<xsd:simpleType> has no "name" attribute');
            }
        }

        // @todo Deal with simple types.
        $ret = RemoteType::scalar($name, $context->namespace, 'string');
        $context->setType($ret);

        /*
        $ret = null;

        $found = 0;
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                if ($this->elementIs($child, 'xsd:complexContent')) {
                    $found++;
                    $ret = $this->readComplexContent($context, $child, $name);
                } else if ($this->elementIs($child, 'xsd:sequence')) {
                    $found++;
                    $ret = new RemoteType($name, $context->namespace);
                    $this->readSequence($context, $child, $ret);
                    $context->setType($ret);
                } else {
                    $this->elementUnexpected($child, '<xsd:complexType>');
                }
            }
        }

        if (!$found) {
            $this->warning(\sprintf("<xsd:complexType>: no type definition found"));
        } else if (1 < $found) {
            $this->warning(\sprintf("<xsd:complexType>: duplicate content found"));
        }
         */

        return $ret;
    }

    /**
     * Read a single type definition.
     */
    protected function readComplexType(ReaderContext $context, \DOMElement $element, ?string $name = null): ?RemoteType
    {
        $this->elementCheck($element, 'xsd:complexType');

        $context = $this->processNamespaces($context, $element);

        // <complexType (name=TYPE_NAME)>
        //   <complexContent>
        //     <extension base="TYPE_NAME">
        //       <sequence>
        //         <element> (property)
        //           [name=PROP_NAME]
        // OR
        // <complexType (name=TYPE_NAME)>
        //   <sequence>
        //     <element> (property)
        //       [name=PROP_NAME]

        if (!$name) {
            if ($element->hasAttribute('name')) {
                $name = (string) $element->getAttribute('name');
            } else {
                throw new ReaderError('<xsd:complexType> has no "name" attribute');
            }
        }

        $ret = null;

        $found = 0;
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                if ($this->elementIs($child, 'xsd:complexContent')) {
                    $found++;
                    $ret = $this->readComplexContent($context, $child, $name);
                } else if ($this->elementIs($child, 'xsd:sequence')) {
                    $found++;
                    $ret = new RemoteType($name, $context->namespace);
                    $this->readSequence($context, $child, $ret);
                    $context->setType($ret);
                } else {
                    $this->elementUnexpected($child, '<xsd:complexType>');
                }
            }
        }

        if (!$found) {
            $this->warning(\sprintf("<xsd:complexType>: no type definition found"));
        } else if (1 < $found) {
            $this->warning(\sprintf("<xsd:complexType>: duplicate content found"));
        }

        return $ret;
    }

    protected function readComplexContent(ReaderContext $context, \DOMElement $element, string $name): ?RemoteType
    {
        $this->elementCheck($element, 'xsd:complexContent');

        $context = $this->processNamespaces($context, $element);

        // <complexContent>
        //   <extension base="TYPE_NAME">

        $ret = null;

        $found = 0;
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                if ($this->elementIs($child, 'xsd:extension')) {
                    $found++;
                    $ret = $this->readExtension($context, $child, $name);
                } else {
                    $this->elementUnexpected($child, '<xsd:complexContent>');
                }
            }
        }

        if (!$found) {
            $this->warning(\sprintf("<xsd:complexContent>: no type definition found"));
        } else if (1 < $found) {
            $this->warning(\sprintf("<xsd:complexContent>: duplicate content found"));
        }

        return $ret;
    }

    protected function readExtension(ReaderContext $context, \DOMElement $element, string $name): ?RemoteType
    {
        $this->elementCheck($element, 'xsd:extension');

        $context = $this->processNamespaces($context, $element);

        // <extension base="TYPE_NAME">
        //   <sequence>

        $extends = null;

        if ($typeName = $this->attribute($element, 'base')) {
            if (\strpos($typeName, ':')) {
                list($namespace, $typeName) = \explode(':', $typeName, 2);
                if ('xsd' === $namespace) {
                    $this->warning(\sprintf("<xsd:extension>: should not extend a scalar type"));
                } else {
                    $extends = $context->findType($typeName, $namespace);
                }
            }
        }

        $ret = null;

        $found = 0;
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                if ($this->elementIs($child, 'xsd:sequence')) {
                    $found++;
                    $ret = new RemoteType($name, $context->namespace, $extends?->toString());
                    $this->readSequence($context, $child, $ret);
                    $context->setType($ret);
                } else {
                    $this->elementUnexpected($child, '<xsd:extension>');
                }
            }
        }

        if (!$found) {
            $this->warning(\sprintf("<xsd:extension>: no type definition found"));
        } else if (1 < $found) {
            $this->warning(\sprintf("<xsd:extension>: duplicate content found"));
        }

        return $ret;
    }

    protected function readSequence(ReaderContext $context, \DOMElement $element, RemoteType $type): void
    {
        $this->elementCheck($element, 'xsd:sequence');

        $context = $this->processNamespaces($context, $element);

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

        foreach ($element->childNodes as $child) {
            if ($this->elementIs($child, 'xsd:element')) {
                if (!$name = $this->attributeRequired($child, 'name')) {
                    continue;
                }

                $nullable = false;

                if ($typeName = $this->attribute($child, 'type')) {
                    if (\strpos($typeName, ':')) {
                        list($typeNamespace, $typeName) = \explode(':', $typeName, 2);
                        if ('xsd' === $typeNamespace) {
                            $propType = $context->addScalarType($typeName, $typeNamespace);
                        } else {
                            $propType = $context->findType($typeName, $typeNamespace);
                        }
                    } else {
                        $propType = $context->findType($typeName, $context->namespace);
                    }
                } else {
                    // When dealing with a complex type, we need to name it using a
                    // generated reproducible name. Let's hash the parent name and
                    // namespace for this.
                    // Make it start with a letter (mandatory for PHP types). "I"
                    // means internal.
                    // @todo Any better ideas for naming? I take it!
                    // $typeName = 'I' . \md5($type->toString()) . \uniqid() . '_' . $name;
                    $typeName = $type->name . '_' . $name;
                    $propType = $this->readElement($context, $child, $typeName);
                }
                if (!$propType) {
                    $this->warning(\sprintf("<xsd:element>: could not find type of property %s", $name));

                    continue;
                }

                // "nillable" attribute is rather obsolete and should not be used.
                // It is semantically equivalent to minOccurs=0.
                if ('true' === $this->attribute($element, 'nillable')) {
                    $nullable = true;
                }

                // Handle multiplicity.
                $collection = false;
                $max = $this->attribute($child, 'maxOccurs');
                $min = $this->attribute($child, 'minOccurs');

                if (null !== $max) {
                    if ("unbounded" === $max) {
                        $max = null;
                        $collection = true;
                    } else {
                        $max = (int) $max;
                        if (1 < $max) {
                            $collection = true;
                        }
                    }
                } else if (null !== $min) {
                    $collection = true;
                }
                if (null !== $min) {
                    $min = (int) $min;
                    if ($min < 1) {
                        $nullable = true;
                    }
                }

                $type->property(
                    new Property(
                        name: $name,
                        type: $propType->toString(),
                        collection: $collection,
                        minOccur: $collection ? ($min ? $min : 0) : ($min ? 1 : 0),
                        maxOccur: $max,
                        nullable: $nullable,
                    ),
                );
            } else {
                $this->elementUnexpected($child, '<xsd:sequence>');
            }
        }
    }

    protected function readProperty(ReaderContext $context, \DOMElement $element): Property
    {
        $this->elementCheck($element, 'xsd:element');

        $context = $this->processNamespaces($context, $element);

        // <element> (property)
        //   [name=PROP_NAME]
        //   [type=TYPE_NAME]
        //   [minOccurs=INT]
        //   [maxOccurs=INT|"unbounded"]
        //   <annotation>
        //     <documentation>TYPE_DESCRIPTION
        //   ... properties from more general type.

        // If [maxOccurs=1], then it's NOT a collection.
        // Else if [minOccurs] is present, it's a collection.
    }
}
