# Coding standards

Readability and long-term maintainability are mandatory project requirements.
Code is written for the next developer to understand without reconstructing
intent from implementation details.

## Documentation

- Every application class, PHP method and standalone PHP function must have a
  `/** ... */` PHPDoc block immediately above it.
- Every JavaScript class, method and function authored for the application must
  have a `/** ... */` JSDoc block immediately above it.
- The first sentence explains what the function does in domain language.
- Document non-obvious parameters, return shapes, exceptions, side effects,
  database writes and transactional assumptions.
- Comments must explain intent or constraints. Do not narrate an obvious line of
  code, and do not use inline comments as a substitute for clear names.
- Generated framework files and third-party/vendor code are not reformatted or
  rewritten only to add documentation. When generated code becomes owned
  application logic, it must be brought up to this standard.

Example:

```php
/**
 * Calculates the character state that will result from a reset without writing
 * any data to the game database.
 *
 * @throws ResetNotAllowedException When the character does not satisfy the
 * reset profile requirements.
 */
public function preview(
    CharacterSnapshot $character,
    ResetProfile $profile,
): ResetPreview {
    // Implementation.
}
```

## Layout and expressions

- Place each variable assignment on its own line.
- Do not declare or assign multiple variables in a single statement.
- Do not compress functions, closures, conditions or loops onto one line.
- Always use braces for control structures, including single-line bodies.
- Break long argument lists and fluent chains across lines when that makes the
  sequence easier to scan.
- Prefer intermediate variables with domain names over deeply nested calls.
- Avoid nested ternaries and clever boolean expressions.
- Keep one logical operation per statement.

## Naming and method design

- Use descriptive domain names such as `requiredResetLevel` and
  `remainingLevelUpPoints`; avoid abbreviations such as `lvl`, `res` or `tmp`
  outside verified legacy column mappings.
- Method names describe an action or question precisely.
- Methods have one responsibility and remain small enough to understand without
  scrolling through unrelated behaviour.
- Extract domain calculations from controllers, Livewire components and Blade
  templates into typed application/domain classes.
- Legacy SQL names are isolated in version adapters and persistence mappings.

## Types and data structures

- Declare parameter and return types for every PHP method.
- Prefer immutable value objects and data-transfer objects for operation input,
  preview and result data.
- Use enums for finite policies such as inventory preservation mode or reset
  price strategy.
- Document array shapes when an array is the appropriate boundary format.
- Do not pass unstructured settings arrays through the domain layer.

## Database writes

- Mutations of game data must live in explicit application actions/services.
- Every multi-column or multi-table game operation must be transactional.
- Ownership, online state and resource checks are repeated inside the write
  boundary.
- Every game mutation records a before/after audit entry and the settings
  snapshot that produced it.
- Controllers and interactive components never build raw SQL strings.

## Verification

- Every domain calculation receives focused unit tests.
- Every endpoint or interactive operation receives a feature test.
- Database adapters receive integration tests against sanitized 97d+99i
  fixtures.
- PHP changes are formatted with Laravel Pint before completion.
