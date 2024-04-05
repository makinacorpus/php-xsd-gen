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
