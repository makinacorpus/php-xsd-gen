# XSD to PHP generator

Generate PHP classes from an XSD schema.

# Examples

Note: all examples in the following section removes some generated additional static
that exists for object hydration and serialization purpose.

## ComplexType to PHP class

### Source XSD

This API generates PHP classes from a given XSD schema.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<xsd:schema
  xmlns:xsd="http://www.w3.org/2001/XMLSchema"
  xmlns="https://schemas.makina-corpus.com/testing/inheritance"
  targetNamespace="https://schemas.makina-corpus.com/testing/inheritance">
  <xsd:complexType name="Address">
    <xsd:annotation>
      <xsd:documentation>Some random address</xsd:documentation>
    </xsd:annotation>
    <xsd:sequence>
      <xsd:element name="Street" type="xsd:string" minOccurs="1">
        <xsd:annotation>
          <xsd:documentation>A basic "xsd:string'.</xsd:documentation>
        </xsd:annotation>
      </xsd:element>
      <xsd:element name="City" type="foo:string" minOccurs="1" xmlns:foo="http://www.w3.org/2001/XMLSchema">
        <xsd:annotation>
          <xsd:documentation>This example is about shadowing "xsd" attribute with an alias.</xsd:documentation>
        </xsd:annotation>
      </xsd:element>
      <xsd:element name="Country" type="xsd:string">
        <xsd:annotation>
          <xsd:documentation>A basic "xsd:string'.</xsd:documentation>
        </xsd:annotation>
      </xsd:element>
    </xsd:sequence>
  </xsd:complexType>
</xsd:schema>
```

### Default settings output

```php
<?php

namespace MakinaCorpus\XsdGen\Tests\Generated\Defaults\Inheritance;

class Address
{
    /**
     * @internal Hydrator for XML or array exchange.
     */
    public static function create(array|object $values): self
    {
        if ($values instanceof self) {
            return $values;
        }

        return new self(
            street: $values['Street'] ?? throw new \InvalidArgumentException('Address::\$street property cannot be null'),
            city: $values['City'] ?? throw new \InvalidArgumentException('Address::\$city property cannot be null'),
            country: $values['Country'] ?? throw new \InvalidArgumentException('Address::\$country property cannot be null'),
        );
    }

    public function __construct(
        /**
         * A basic "xsd:string'.
         */
        public readonly string $street,
        /**
         * This example is about shadowing "xsd" attribute with an alias.
         */
        public readonly string $city,
        /**
         * A basic "xsd:string'.
         */
        public readonly string $country
    ) {
    }
}
```

### Legacy settings output

@todo

## `<xsd:restriction>` in ComplexType

### Source XSD

`<xsd:restriction>` is type inheritance with properties override. And that's
what will do the generated PHP output.

Note: when a type restricts another, and change its properties, if property
definition is not covariant, generated PHP code may be invalid. In order to
avoid this from being a syntax error, set the `property_public` to `false`,
which makes all properties private and avoid covariance problems.

Tip: you probably want to set `property_getter` to `true` as well otherwise
you will not be able to read your properties.

Note: when using constructor property promotion and public properties
altogether, redifining properties in a child class will raise PHP fatal
errors at compile time. In order to prevent this happen, when this condition
is fulfilled, the child class property will silentely removed.

```xml
  <!-- ... -->
  <xsd:complexType name="FrenchAddress">
    <xsd:annotation>
      <xsd:documentation>Uses "xsd:restriction" to change existing properties.</xsd:documentation>
    </xsd:annotation>
    <xsd:complexContent>
      <xsd:restriction base="Address">
        <xsd:sequence>
          <xsd:element name="Country" type="xsd:string" minOccurs="1">
            <xsd:annotation>
              <xsd:documentation>Alters cardinality.</xsd:documentation>
            </xsd:annotation>
          </xsd:element>
        </xsd:sequence>
      </xsd:restriction>
    </xsd:complexContent>
  </xsd:complexType>
```

### Default settings output

```php
<?php

namespace MakinaCorpus\XsdGen\Tests\Generated\Defaults\Inheritance;

class FrenchAddress extends Address
{
    /**
     * @internal Hydrator for XML or array exchange.
     */
    public static function create(array|object $values): self
    {
        if ($values instanceof self) {
            return $values;
        }

        return new self(
            street: $values['Street'] ?? throw new \InvalidArgumentException('FrenchAddress::\$street property cannot be null'),
            city: $values['City'] ?? throw new \InvalidArgumentException('FrenchAddress::\$city property cannot be null'),
            country: $values['Country'] ?? throw new \InvalidArgumentException('FrenchAddress::\$country property cannot be null')
        );
    }

    public function __construct(string $street, string $city, string $country)
    {
        parent::__construct(
            street: $street,
            city: $city,
            country: $country,
        );
    }
}
```

### Legacy settings output

@todo

## `<xsd:extension>` in ComplexType

### Source XSD

`<xsd:extension>` allows type inheritance. And that's what will do the
generated PHP output.

```xml
  <!-- ... -->
  <xsd:complexType name="FrenchAddressWithPhone">
    <xsd:annotation>
      <xsd:documentation>Uses "xsd:extension" and add properties.</xsd:documentation>
    </xsd:annotation>
    <xsd:complexContent>
      <xsd:extension base="FrenchAddress">
        <xsd:sequence>
          <xsd:element name="PhoneNumber" type="xsd:string" minOccurs="0"></xsd:element>
        </xsd:sequence>
      </xsd:extension>
    </xsd:complexContent>
  </xsd:complexType>

```

### Default settings output

```php
<?php

namespace MakinaCorpus\XsdGen\Tests\Generated\Defaults\Inheritance;

class FrenchAddressWithPhone extends FrenchAddress
{
    /**
     * @internal Hydrator for XML or array exchange.
     */
    public static function create(array|object $values): self
    {
        if ($values instanceof self) {
            return $values;
        }

        return new self(
            street: $values['Street'] ?? throw new \InvalidArgumentException('FrenchAddressWithPhone::\$street property cannot be null'),
            city: $values['City'] ?? throw new \InvalidArgumentException('FrenchAddressWithPhone::\$city property cannot be null'),
            country: $values['Country'] ?? throw new \InvalidArgumentException('FrenchAddressWithPhone::\$country property cannot be null'),
            phoneNumber: $values['PhoneNumber'] ?? null,
        );
    }

    public function __construct(
        string $street,
        string $city,
        string $country,
        public readonly ?string $phoneNumber
    ) {
        parent::__construct(
            street: $street,
            city: $city,
            country: $country,
        );
    }
}
```

### Legacy settings output

@todo

## ComplexType reference in ComplexType

### Source XSD

@todo

### Default settings output

@todo

### Legacy settings output

@todo

## Russian doll ComplexType in ComplexType

### Source XSD

@todo

### Default settings output

@todo

### Legacy settings output

@todo

## SimpleType

## `<xsd:restriction>` in SimpleType

Type restrictions are not implemented yet. They might be in the future but
they will require extensive genereted guard code which is out of scope right
now.

## `<xsd:enum>` in SimpleType

Enums are not implemented yet. It is a planned feature.

# Behaviour

## Imports and namespaces

### XML namespace to PHP namespace conversion

@todo

### `xmlns:NAMESPACE=` attribute schema parsing

On any node parsed, additional aliased namespaces will be parsed, registered
and looked-up for type dependencies resolution. As expected, when a namespace
is defined and aliased at a node level, only this node and its children will
be able to resolve this namespace alias.

### Remote URI resource fetching

Remote URI resource fetching is not implemented yet. It is planned.

### Local resource fetching

When the namespace resource URI is a local file, it will be fetched and read
as an XSD file.

### `<xsd:import/>` element handling

`<xsd:import/>` are handled and will import the given namespace at its direct
parent node level, and make it accessible to children.

`schemaLocation` attribute if present will override the namespace URI for
resource fetching. Hence you can import a remote URI namespace while fetching
the content from a local file.

## `<xsd:sequence/>` and class properties

`<xsd:element/>` found in `<xsd:sequence/>` will spawn the class properties.

@todo explain type=foo vs complexType
@todo explain property russian doll type naming

## Collections

### PHP type

Properties may have multiple values, which make them collections. When
a collection is detected (see algorithm in the next two sections) it
its type in the generated PHP code is `array`.

There is no planned support for a collection API, yet in the future
`doctrine/persistence` collections may be implemented as well.

Whenever a collection property is written, its PHP doc string will contain
the value type for IDE to be able to resolve the value type. The generated
value accessors will also inherit from their respective `@returns` and
`@param` PHP doc type annotations.

### minOccurs and maxOccurs

`minOccurs` and `maxOccurs` parameters will drive the collection status of
a `<xsd:sequence><xsd:element>` generated property.

If `maxOccurs` exists with a value equal to `unbounded` or an integer value
greater than `1`, then the property type will be set to `array`.

If `maxOccurs` is omited, and `minOccurs` value is greate than `1`, the the
property will be set to `array`.

In all other cases, the property will have the element target type.

If `minOccurs` is not defined, or its value is `0`, then the property will
be made nullable.

## nillable

`nillable` attribute in `<xsd:sequence><xsd:element>` will make the
property nullable. This takes precedence over the `minOccurs` parameter.

`nillable` is ignored for collection, an empty collection will always be
created no matter it is nullable or not.

## Property shadowing

When a type `class A { int $c }` is extended by some type
`class B extends A { int $c }` which redefine an existing property, multiple
scenarios may occur:

1. If the constructor property promotion is enabled, the property in the
   `B` type will be ignored, and type specialization will be lost. This
   is mandatory otherwise PHP will detect the property redefinition and
   cause a crash when compiling the code.
   Note that when the properties are private, the constructor promoted
   property will remain defined.

2. If the properties are normal properties defined at the class level,
   we let it be overriden, but pass the value to the parent constructor
   if the property is not nullable.
   Note that if the `B::$c` property is not contravariant with the
   `A::$c` property you will experience runtime errors.

Please note that handling `<xsd:restriction>` shadowed properties is still
experimental, but a quite rare use case.

## Error handling

Per default the XSD reader is tolerant and will not break on recoverable
errors. Most error behaviours can be tuned at the configuration level.

All errors are logged through a `Psr\Log\LoggerInterface` instance that
you can inject in the configuration.

### Cannot fetch remote resource

When a remote resource cannot be fetched, all types from the given
namespace will be ignored, properties using those types will be omited
from the target PHP classes.

This validation check is made during XSD file parsing recursion.

This can be made stricter and raise exception by setting the
`resource_missing_remote_error` option to `true`.

### Cannot fetch remote resource

When a remote resource cannot be fetched, an exception will be raised
and XSD parsing will stop.

This can be made looser in order to simply ignore types from the unfound
resource file by setting the `resource_missing_local_error` option to `false`.

### Unfound type

When a type is not found the same behaviour applies, all properties using
it will be omited from the target PHP class.

This validation check is made during type resolution after the XSD has been
fully parsed.

This can be made stricter and raise exception by setting the `type_missing_error`
option to `true`.

### Redefined type

When a type is found more than once, for example when you parse a set of
multiple WSDL files at once which do not import a common resource but all
embed all their common types, types redefinitions will be ignored.

This can be made stricter and raise exception by setting the `type_override_error`
option to `true`.

### Invalid XML content

When invalid or unexpected XML elements are found during XSD schema read
they will simply be ignored. Tolerance level will the same as the XML
parser in use, which is PHP core `\DOMDocument` API.

This behaviour has no related configuration options.

# Usage

The `MakinaCorpus\XsdGen\Generator` class is a method-chaining based
configuration builder and runner.

Here a simple working example from the unit tests:

```php
(new Generator())
    ->defaultDirectory(__DIR__ . '/Generated')
    ->defaultNamespace('MakinaCorpus\\XsdGen\\Tests\\Generated')
    ->namespace('http://schemas.makina-corpus.com/testing/common/1.0', 'MakinaCorpus\\Common')
    ->propertyGetter(true)
    ->propertyPromotion(true)
    ->propertyPublic(false)
    ->propertyReadonly(false)
    ->propertySetter(false)
    ->logger(new EchoLogger())
    ->file(__DIR__ . '/resources/random-dependencies.xsd')
    ->generate()
;
```

All configuration options have associated methods you can chain on the
object to configure the generator behaviour.

# Fine tune generated code

## PHP code generator options

@todo

### Constructor promoted properties

@todo

### Public or private properties

@todo

### Getters

@todo

### Setters

@todo

## WSDL and XSD reader options

### Error handling

@todo

## Common options

### Logger

@todo

# Generated code style

Generated code will have the default `nikic/php-parser` pretty printer style,
there is no way around that. This means that it will lack some empty lines
and all methods will be single line per default. This is not easy to read for
humans.

We recommend to configure your favorite CS fixer tool to act upon the generated
PHP code in order to make it fit your own conventions.

# Todolist

 - Propagate <xsd:annotation> as PHP-doc (missing on <xsd:element>)
 - Make \InvalidArgumentException in create() method user configurable
 - Handle enum: option for generating either PHP enum, or class with constants
 - Handle other simple types
 - Make simple type user-pluggable
 - Separate fromArray() and hydrate()
 - Write XML serializer
 - Write XML deserializer
