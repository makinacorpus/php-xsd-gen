# SOAP WSDL to PHP generator

Generates classes from WSDL and XSD definitions structure.

Generated classes:
 - Are per default named following the WSDL and XSD naming.
 - Can be renamed using a user-given configuration map.
 - Are placed in a single user-given namespace.
 - Can be namespaced user a user-given configuration map.
 - Can be named or namespaced user a user-given naming strategy.
 - Can have an arbitrary array static constructor.
 - Can have an arbitrary from XML static constructor.
 - Can have associated tooling generated.

Any type can be:
 - Substitued using a user-given type name.
 - Use user-given factories for hydration.

Following WSDL configuration:
 - Endpoint calling code can be generated.

# Usage

@todo

# Generated code alterators

@todo

# A word about generated code style

Generated code will have the default `nikic/php-parser` pretty printer style,
there is no way around that.

Once generated, you can run your favorite CS fixer upon the generated code
to format it to your liking.
