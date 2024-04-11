# XSD to PHP generator

Generate PHP classes from an XSD schema.

TL;DR:

```php
(new Generator())
    ->defaultDirectory(__DIR__ . 'src/Generated')
    ->defaultNamespace('MakinaCorpus\\XsdGen\\Tests\\Generated')
    ->namespace('http://schemas.makina-corpus.com/testing/common/1.0', 'MakinaCorpus\\Common')
    ->propertyGetter(true)
    ->propertyPromotion(true)
    ->propertyPublic(false)
    ->propertyReadonly(false)
    ->propertySetter(false)
    ->logger(new EchoLogger())
    ->file(__DIR__ . '/resources/xsd/some-file.xsd')
    ->generate()
;
```

... will give you working PHP code replicating XSD defined data structure.

Future plan is mostly to have a complete end-to-end SOAP exchange
implementation for production projects.

# Generated code examples

Note: all examples in the following section removes some generated additional static
methods that exists for object hydration and serialization purpose.

## ComplexType to PHP class

### Source XSD

This API generates PHP classes from a given XSD schema.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<xsd:schema
  xmlns:xsd="http://www.w3.org/2001/XMLSchema"
  xmlns="https://schemas.makina-corpus.com/testing/inheritance"
  targetNamespace="https://schemas.makina-corpus.com/testing/inheritance">
  <xsd:complexType name="ShadowedClass">
    <xsd:annotation>
      <xsd:documentation>This class has shadowed properties.</xsd:documentation>
    </xsd:annotation>
    <xsd:sequence>
      <xsd:element name="shadowedCovariant" type="Address" minOccurs="1">
        <xsd:annotation>
          <xsd:documentation>This property is shadowed by a covariant type.</xsd:documentation>
        </xsd:annotation>
      </xsd:element>
      <xsd:element name="shadowedIncompatible" type="Address" minOccurs="1">
        <xsd:annotation>
          <xsd:documentation>This property is shadowed but is not compatible.</xsd:documentation>
        </xsd:annotation>
      </xsd:element>
      <xsd:element name="nonShadowedOther" type="Address">
        <xsd:annotation>
          <xsd:documentation>This property is not shadowed.</xsd:documentation>
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

class ShadowedClass
{
    /**
     * This property is shadowed by a covariant type.
     */
    private readonly Address $shadowedCovariant;

    /**
     * This property is shadowed but is not compatible.
     */
    private readonly Address $shadowedIncompatible;

    /**
     * This property is not shadowed.
     */
    private readonly Address $nonShadowedOther;

    public function __construct(
        Address $shadowedCovariant,
        Address $shadowedIncompatible,
        Address $nonShadowedOther
    ) {
        $this->shadowedCovariant = $shadowedCovariant;
        $this->shadowedIncompatible = $shadowedIncompatible;
        $this->nonShadowedOther = $nonShadowedOther;
    }

    public function getShadowedCovariant(): Address
    {
        return $this->shadowedCovariant;
    }

    public function getShadowedIncompatible(): Address
    {
        return $this->shadowedIncompatible;
    }

    public function getNonShadowedOther(): Address
    {
        return $this->nonShadowedOther;
    }
}
```

### Modern settings output

```php
<?php

namespace MakinaCorpus\XsdGen\Tests\Generated\Legacy\Inheritance;

class ShadowedClass
{
    public function __construct(
        /**
         * This property is shadowed by a covariant type.
         */
        public readonly Address $shadowedCovariant,

        /**
         * This property is shadowed but is not compatible.
         */
        public readonly Address $shadowedIncompatible,

        /**
         * This property is not shadowed.
         */
        public readonly Address $nonShadowedOther
    ) {}
}
```

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
  <xsd:complexType name="ShadowingClass">
    <xsd:annotation>
      <xsd:documentation>This class shadows properties.</xsd:documentation>
    </xsd:annotation>
    <xsd:complexContent>
      <xsd:restriction base="ShadowedClass">
        <xsd:sequence>
          <xsd:element name="shadowedCovariant" type="FrenchAddress" minOccurs="1">
            <xsd:annotation>
              <xsd:documentation>This property shadows the parent one, and is covariant.</xsd:documentation>
            </xsd:annotation>
          </xsd:element>
          <xsd:element name="shadowedIncompatible" type="xsd:date" minOccurs="1">
            <xsd:annotation>
              <xsd:documentation>This property shadows the parent one, but is not covariant.</xsd:documentation>
            </xsd:annotation>
          </xsd:element>
        </xsd:sequence>
      </xsd:restriction>
    </xsd:complexContent>
  </xsd:complexType>
```

### Default settings output

```php
class ShadowingClass extends ShadowedClass
{
    /**
     * This property shadows the parent one, and is covariant.
     */
    private readonly FrenchAddress $shadowedCovariant;
    /**
     * This property shadows the parent one, but is not covariant.
     */
    private readonly \DateTimeImmutable $shadowedIncompatible;

    public function __construct(
        /** Inherited property. */
        Address $nonShadowedOther,
        FrenchAddress $shadowedCovariant,
        \DateTimeImmutable $shadowedIncompatible
    ) {
        $this->shadowedCovariant = $shadowedCovariant;
        $this->shadowedIncompatible = $shadowedIncompatible;

        parent::__construct(
            shadowedCovariant: $shadowedCovariant,
            shadowedIncompatible: $shadowedIncompatible,
            nonShadowedOther: $nonShadowedOther,
        );
    }

    public function getShadowedCovariant(): FrenchAddress
    {
        return $this->shadowedCovariant;
    }

    public function getShadowedIncompatible(): \DateTimeImmutable
    {
        return $this->shadowedIncompatible;
    }
}
```

As you can see, the private `$shadowedCovariant` and `$shadowedIncompatible`
properties are allowed to shadow their parent definitions, which is therefore
completely hidden now.

Warning: in this example, the `$shadowedIncompatible` will cause PHP errors
because the type is not covariant, there is no way around this.

An alternative method would have been to merge classes with their parent
definition in order to entirely drop the inheritance in the benefit of one
huge class.

### Modern settings output

```php
<?php

namespace MakinaCorpus\XsdGen\Tests\Generated\Legacy\Inheritance;

class ShadowingClass extends ShadowedClass
{
    public function __construct(
        /** Inherited property. */
        Address $shadowedCovariant,
        /** Inherited property. */
        Address $shadowedIncompatible,
        /** Inherited property. */
        Address $nonShadowedOther,
    ) {
        parent::__construct(
            shadowedCovariant: $shadowedCovariant,
            shadowedIncompatible: $shadowedIncompatible,
            nonShadowedOther: $nonShadowedOther,
        );
    }
}
```

As you can see, the private `$shadowedCovariant` and `$shadowedIncompatible`
properties are not redefined because their are public and it would cause
fatal errors in case of incompatible types.

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

Parent class:

```php
class Address
{
    /**
     * A basic "xsd:string'.
     */
    private readonly string $addressLine;

    /**
     * A basic "xsd:string'.
     */
    private readonly ?string $country;

    public function __construct(
        string $addressLine,
        ?string $country,
    ) {
        $this->addressLine = $addressLine;
        $this->country = $country;
    }

    public function getAddressLine(): string
    {
        return $this->addressLine;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }
}
```

Child class:

```php
<?php

namespace MakinaCorpus\XsdGen\Tests\Generated\Defaults\Inheritance;

class AddressAndPhone extends Address
{
    /**
     * Additional property on extended type.
     */
    private readonly ?string $phoneNumber;

    public function __construct(
        /** Inherited property. */
        string $addressLine,
        /** Inherited property. */
        ?string $country,
        ?string $phoneNumber,
    ) {
        $this->phoneNumber = $phoneNumber;

        parent::__construct(
            addressLine: $addressLine,
            country: $country,
        );
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }
}
```

### Modern settings output

Parent class:

```php
<?php

namespace MakinaCorpus\XsdGen\Tests\Generated\Modern\Inheritance;

class Address
{
    public function __construct(
        /**
         * A basic "xsd:string'.
         */
        public readonly string $addressLine,

        /**
         * A basic "xsd:string'.
         */
        public readonly ?string $country,
    ) {}
}
```

Child class:

```php
<?php

namespace MakinaCorpus\XsdGen\Tests\Generated\Modern\Inheritance;

class AddressAndPhone extends Address
{
    public function __construct(
        /** Inherited property. */
        string $addressLine,

        /** Inherited property. */
        ?string $country,

        /**
         * Additional property on extended type.
         */
        public readonly ?string $phoneNumber
    ) {
        parent::__construct(
            addressLine: $addressLine,
            country: $country,
        );
    }
}
```

## ComplexType reference versus "russian doll" property

### Source XSD

```xml
<?xml version="1.0" encoding="UTF-8"?>
<xsd:schema xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="https://schemas.makina-corpus.com/testing/inheritance" targetNamespace="https://schemas.makina-corpus.com/testing/inheritance" elementFormDefault="unqualified" attributeFormDefault="unqualified">
  <xsd:complexType name="Address">
    <xsd:annotation>
      <xsd:documentation>Some random address</xsd:documentation>
    </xsd:annotation>
    <xsd:sequence>
      <xsd:element name="AddressLine" type="xsd:string" minOccurs="1">
        <xsd:annotation>
          <xsd:documentation>A basic "xsd:string'.</xsd:documentation>
        </xsd:annotation>
      </xsd:element>
      <xsd:element name="Country" type="xsd:string" minOccurs="0">
        <xsd:annotation>
          <xsd:documentation>A basic "xsd:string'.</xsd:documentation>
        </xsd:annotation>
      </xsd:element>
    </xsd:sequence>
  </xsd:complexType>
  <xsd:complexType name="RussianDollExample">
    <xsd:annotation>
      <xsd:documentation>This class shadows properties.</xsd:documentation>
    </xsd:annotation>
    <xsd:sequence>
      <xsd:element name="typeReference" type="Address">
        <xsd:annotation><xsd:documentation>This is an existing complex type reference.</xsd:documentation>
        </xsd:annotation>
      </xsd:element>
      <xsd:element name="complexProperty">
        <xsd:annotation><xsd:documentation>This is a russian doll complex type.</xsd:documentation>
        </xsd:annotation>
        <xsd:complexType>
          <xsd:sequence>
            <xsd:element name="arbitraryProperty" type="xsd:string">
              <xsd:annotation><xsd:documentation>This property is inside the internal type.</xsd:documentation>
              </xsd:annotation>
            </xsd:element>
          </xsd:sequence>
        </xsd:complexType>
      </xsd:element>
    </xsd:sequence>
  </xsd:complexType>
</xsd:schema>
```

### Default settings output

Named complex type:

```php
<?php

namespace MakinaCorpus\XsdGen\Tests\Generated\Defaults\Inheritance;

class RussianDollExample
{
    /**
     * This is an existing complex type reference.
     */
    private readonly Address $typeReference;

    /**
     * This is a russian doll complex type.
     */
    private readonly RussianDollExample_complexProperty $complexProperty;

    public function __construct(
        Address $typeReference,
        RussianDollExample_complexProperty $complexProperty
    ) {
        $this->typeReference = $typeReference;
        $this->complexProperty = $complexProperty;
    }

    public function getTypeReference(): Address
    {
        return $this->typeReference;
    }

    public function getComplexProperty(): RussianDollExample_complexProperty
    {
        return $this->complexProperty;
    }
}
```

Internal complex property:

```php
<?php

namespace MakinaCorpus\XsdGen\Tests\Generated\Defaults\Inheritance;

class RussianDollExample_complexProperty
{
    /**
     * This property is inside the internal type.
     */
    private readonly string $arbitraryProperty;

    public function __construct(string $arbitraryProperty)
    {
        $this->arbitraryProperty = $arbitraryProperty;
    }

    public function getArbitraryProperty(): string
    {
        return $this->arbitraryProperty;
    }
}
```

As you can see, for the existing `Address` type, a simple property using
the existing class is written.

For the nested complex type however, a `RussianDollExample_complexProperty`
new class is created.

Nested complex properties can nest complex properties themselves. Name will
always be `ClassName_ComplexPropertyName`.

### Modern settings output

Named complex type:

```php
<?php

namespace MakinaCorpus\XsdGen\Tests\Generated\Modern\Inheritance;

class RussianDollExample
{
    public function __construct(
        /**
         * This is an existing complex type reference.
         */
        public readonly Address $typeReference,

        /**
         * This is a russian doll complex type.
         */
        public readonly RussianDollExample_complexProperty $complexProperty
    ) {}
}
```

Internal complex property:

```php
<?php

namespace MakinaCorpus\XsdGen\Tests\Generated\Modern\Inheritance;

class RussianDollExample_complexProperty
{
    public function __construct(
        /**
         * This property is inside the internal type.
         */
        public readonly string $arbitraryProperty
    ) {}
}
```

## SimpleType

## `<xsd:restriction>` in SimpleType

Type restrictions are not implemented yet. They might be in the future but
they will require extensive genereted guard code which is out of scope right
now.

As of now, simple types will be arbitrarily replaced with PHP equivalents
when available, the current conversion map is the following:

 - `xsd:anyURI`: `string`
 - `xsd:base64Binary`: `string`
 - `xsd:boolean`: `bool`
 - `xsd:date`: `\DateTimeImmutable`,
 - `xsd:dateTime`: `\DateTimeImmutable`,
 - `xsd:decimal `: `float`
 - `xsd:double`: `float`
 - `xsd:duration`: `\DateInterval`,
 - `xsd:float`: `float`
 - `xsd:gDay`: `int`
 - `xsd:gMonth`: `int`
 - `xsd:gMonthDay`: `int`
 - `xsd:gYear`: `int`
 - `xsd:gYearMonth`: `int`
 - `xsd:hexBinary`: `string`
 - `xsd:integer`: `int`
 - `xsd:normalizedString`: `string`
 - `xsd:NOTATION`: `string`
 - `xsd:Qname`: `string`
 - `xsd:string`: `string`
 - `xsd:time`: `string`

It is planned to make simple types behaviour pluggable.

## `<xsd:enum>` in SimpleType

Enums are not implemented yet. It is a planned feature.

# Behaviour

## Imports and namespaces

### XML namespace to PHP namespace conversion

#### Default behaviour

When spawning this API, you need to set two importants parameters:

 - `default_namespace`: is the PSR-4 namespace prefix for all your generated
   code, if you need to split files in more specialized namespaces, read the
   documentation below. For example, you may set `YourVendor\YourApp\Generated`
   in here.

 - `default_directory`: is the root folder for the PSR-4 namespace you chose.
   For example if your existing `/path/to/project/src/` folder is the root
   for the `YourVendor\YourApp` namespace, you probably want to use
   the `/path/to/project/src/Generated` value.

Now consider the following XSD namespace:

```
https://schemas.makina-corpus.com/testing/inheritance
```

If you choose not to configure it further, this URI will be converted to
the following namespace infix:

```
Schemas\Makina\Corpus\Com\Testing\Inheritance
```

Then the PSR-4 prefix will be applied, final class namespace will then be:

```
YourVendor\YourApp\Generated\Schemas\Makina\Corpus\Com\Testing\Inheritance`
```

#### Configuring namespaces

Considering that raw namespace URI conversion to a PHP namespace is
arbitrary and probably will not fit your need, you can choose to change
on a per URI prefix basis the target PSR-4 namespace.

Here, we could, for example, set the `namespaces` variable to the following:

```yaml
default_namespace: YourVendor\YourApp\Generated
default_directory: /path/to/project/src/Generated

namespaces:
    "https://schemas.makina-corpus.com/testing": MakinaCorpus\Testing
```

The given URI will match, and trailing namespace remaining will simply
be `/inheritance`. It then converts to the following fully qualifed PHP
namespace name:

```
YourVendor\YourApp\Generated\MakinaCorpus\Testing\Inheritance`
```

Note: the namespaces you configure will always be a namespace infix,
relative to the default PSR-4 prefix.

You can set as many namespaces as you wish, they will be evuluated
in order, consider writing starting with the most specific and ending with
the least specific namespace URI prefix.

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

# Fine tune generated code (configuration reference)

When spawning the generator, you can pass numerous options to drive how will
be generated the code, to satisfy your own conventions.

## PHP code generator options

### Protected constructor

If you intend to use these objects only in a scenario where they are
automatically hydrated from XML (default is `false`):

*In configuration*:

```yaml
xsd_gen:
    class_constructor: false
```

*With the `Generator` class*:

```php
(new Generator())
    ->classConstructor(false)
```

### Generate factory method

Generate a `create(array|self)` factory method for hydration tooling (default is `true`):

*In configuration*:

```yaml
xsd_gen:
    class_factory_method: false
```

*With the `Generator` class*:

```php
(new Generator())
    ->classFactoryMethod(false)
```

### Default PSR-4 namespace prefix

Will always be prepend to generated PHP class namespaces (no default):

*In configuration*:

```yaml
xsd_gen:
    default_namespace: YourVendor\YourApp\Generated
```

*With the `Generator` class*:

```php
(new Generator())
    ->defaultNamespace('YourVendor\\YourApp\\Generated')
```

### Default PSR-4 namespace directory

Where is the source code, this path is the default namespace prefix PSR-4
folder where the source code will be put (no default):

*In configuration*:

```yaml
xsd_gen:
    default_directory: /path/to/app/src/Generated
```

*With the `Generator` class*:

```php
(new Generator())
    ->defaultDirectory('/path/to/app/src/Generated')
```

### Convert property name to camelCase

Should property names be camed cased, otherwise they simply keep the XSD
given name (default is `true`):

*In configuration*:

```yaml
xsd_gen:
    property_camel_case: false
```

*With the `Generator` class*:

```php
(new Generator())
    ->propertyCamelCase(false)
```

### Properties default values

Should default values be set when applyable (default is `true`).

*In configuration*:

```yaml
xsd_gen:
    property_default: false
```

*With the `Generator` class*:

```php
(new Generator())
    ->propertyDefault(false)
```

Warning: this is not implemented yet.

### Constructor promoted properties

Generated code will use constructor promoted properties instead of normal
class properties (default is `false`):

*In configuration*:

```yaml
xsd_gen:
    property_promotion: true
```

*With the `Generator` class*:

```php
(new Generator())
    ->propertyPromotion(true)
```

### Public or private properties

Make properties `public` instead of `private` (default is `false`):

*In configuration*:

```yaml
xsd_gen:
    property_public: true
```

*With the `Generator` class*:

```php
(new Generator())
    ->propertyPublic(true)
```

### Readonly properties

Make properties `readonly` (default is `true`):

*In configuration*:

```yaml
xsd_gen:
    property_readonly: false
```

*With the `Generator` class*:

```php
(new Generator())
    ->propertyReadonly(false)
```

### Getters

Generate property getters (default is `true`):

*In configuration*:

```yaml
xsd_gen:
    property_getter: false
```

*With the `Generator` class*:

```php
(new Generator())
    ->propertyGetter(false)
```

### Setters

Generate property setters (default is `false`):

*In configuration*:

```yaml
xsd_gen:
    property_setter: true
```

*With the `Generator` class*:

```php
(new Generator())
    ->propertySetter(true)
```

Warning: this is not compatible with `property_readonly` and will set back
to false if `readonly` is detected.

## WSDL and XSD reader options

### Referenced type is missing

When a type is referenced but unfound in the present XSD document or any
other one loaded by import magic, raise an exception and prevent code
generation to happen. Otherwise simply drop the type and all properties
using it (default is `false`).

*In configuration*:

```yaml
xsd_gen:
    type_missing_error: true
```

*With the `Generator` class*:

```php
(new Generator())
    ->typeMissingError(true)
```

### Type is defined more than once

When a type definition with the same namespace and name is found more than
once, raise an exception and prevent code generation to happen. Otherwise
simply keep the last one (default is `false`).

*In configuration*:

```yaml
xsd_gen:
    type_override_error: true
```

*With the `Generator` class*:

```php
(new Generator())
    ->typeOverrideError(true)
```

## Common options

### Logger

All messages, informational, warnings and errors are being sent to a logger
implementing interfaces from `psr/log`. You can pass the `logger` option to the
generator using any `Psr\Log\LoggerInterface` instance.

# Generated code style

Generated code will have the default `nikic/php-parser` pretty printer style,
there is no way around that. This means that it will lack some empty lines
and all methods will be single line per default. This is not easy to read for
humans.

We recommend to configure your favorite CS fixer tool to act upon the generated
PHP code in order to make it fit your own conventions.

# Future plans

## Now!

 - Use `#[\Override]` attribute when applyable in generated code.
 - Propagate `<xsd:annotation>` from `<xsd:element>` to the underlaying
   complex type in order to add PHP-doc to generated class (current state
   of XSD reader doesn't allow this).
 - Add more unit test, a lot of unit tests, many many unit tests.

## Near future

 - Add support for the `<xsd:enum>` element, by either using class constants
   or using PHP `BackedEnum`, choice between the two being user configurable.
 - Provide an option to skip inheritance which will merge all parenting tree
   into a single class, thus elimating completly the property shadowing
   variancy problem.
 - Implement remote resource download.
 - Allow user to choose another exception than `\InvalidArgumentException`
   for hydrator method validation.
 - Provide a Symfony bundle with configuration inside.
 - Provide a complete per-source-file configuration.
 - Provide alternative ways to spawn object collections, first implementation
   with `doctrine/collections` to validate a proof-of-concept, and make this
   pluggable for users to replace with something else.

## Not so near future, but planned

 - Generate XML tooling for hydrating values from XML content.
 - Generate XML tooling for serializing values to XML content.
 - Generate SOAP tooling for calling remote methods.
 - Allow user to deal with simple types and provide its own conversion matrix.
 - Allow user to plug static methods or functions to deal with simple types or
   class conversion (both ways).
