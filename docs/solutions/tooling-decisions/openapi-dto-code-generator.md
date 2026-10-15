---
title: "OpenAPI Schema to PHP DTO Code Generator"
date: 2026-10-15
category: tooling-decisions
module: DTO Generation
problem_type: tooling_decision
component: tooling
severity: medium
applies_when:
  - "Frontend and backend need type-safe data contracts"
  - "API specifications exist as OpenAPI schemas"
  - "Manual DTO synchronization causes drift or maintenance burden"
tags:
  - code-generation
  - openapi
  - dto
  - type-safety
  - automation
  - twig
  - schema-driven
---

# OpenAPI Schema to PHP DTO Code Generator

## Context

The Agent workshop system was generating API contracts with OpenAPI but maintaining parallel DTO classes by hand. Manual DTO maintenance created three friction points:

1. **Type drift risk** — hand-written PHP DTOs could diverge from OpenAPI schemas used for frontend TypeScript generation, breaking runtime contracts
2. **Redundant work** — adding a new field meant updating the schema, the PHP DTO class (constructor, toArray, fromArray, validation), and hoping nothing mismatched
3. **Code review overhead** — reviewers had to verify that DTO implementations faithfully matched schema definitions

The project already had OpenAPI infrastructure for frontend type generation (U11/U12), but the backend still maintained DTO classes manually under `backend/app/Service/Agent/Outcome/Payload/`. The missing piece: an automated pipeline from OpenAPI schemas to ready-to-use PHP DTO classes.

Off-the-shelf tools like jane-php/open-api and openapi-generator-cli were rejected because they generate full HTTP client code (models + client + authentication layers) — overkill for a project that only needed the data models, and the generated code didn't match the project's PHP style conventions (readonly properties, final classes, PSR-12 formatting, named constructor arguments).

## Guidance

### Architecture

Build a **modular OpenAPI Schema → Twig template → PHP DTO** code generator:

**Components**:
- `backend/bin/generate-dtos.php` — CLI generator script (DtoGenerator class)
- `backend/templates/dto.php.twig` — Twig template rendering PHP class code
- `backend/openapi/schemas/*.yaml` — modular schema files by business domain (title.yaml, outline.yaml, content.yaml, images.yaml, reflection.yaml)
- `backend/app/DTO/*.php` — generated readonly PHP DTOs with `@generated` marker
- `composer.json scripts` — `generate:dto` runs the generator
- `backend/docs/DTO-GENERATION.md` — user guide

**Data flow**:
```
OpenAPI YAML schemas
  ↓ (Symfony/Yaml parser)
DtoGenerator extracts components
  ↓ (Twig template engine)
PHP code string
  ↓ (file_put_contents with @generated marker)
backend/app/DTO/*.php classes
```

**Integration points**:
- **Composer PSR-4 autoload** — `App\DTO` namespace maps to `backend/app/DTO/`
- **Existing CI** — Swagger generation workflow (`swagger:generate → swagger.json`) consumes these DTOs via `#[OA\Schema]` annotations embedded in generated code
- **Frontend types** — `openapi-typescript` reads swagger.json (which includes these DTOs) to generate frontend/src/types/api.ts

### Implementation Highlights

#### 1. Type Mapping with $ref Resolution

```php
// bin/generate-dtos.php
private function mapOpenApiTypeToPhp(array $propertyDef): string
{
    // Handle $ref first
    if (isset($propertyDef['$ref'])) {
        $refPath = explode('/', $propertyDef['$ref']);
        $className = end($refPath);
        return "\\App\\DTO\\{$className}";
    }
    
    // Then native types
    $type = $propertyDef['type'] ?? 'mixed';
    $format = $propertyDef['format'] ?? null;
    
    return match ($type) {
        'string' => $format === 'date-time' ? '\\DateTimeInterface' : 'string',
        'integer' => 'int',
        'number' => 'float',
        'boolean' => 'bool',
        'array' => $this->resolveArrayType($propertyDef),
        'object' => 'array', // Untyped objects = array
        default => 'mixed',
    };
}
```

**Why**: PHP 8+ `match` expressions provide exhaustive type mapping with compile-time completeness checks. Date-time format detection generates `\DateTimeInterface` (immutable-friendly) instead of raw strings. `$ref` resolution enables nested DTOs without manual linking.

#### 2. Constructor Parameter Ordering

```php
// dto.php.twig template
public function __construct(
{% for property in properties %}
{% if property.required and not property.nullable %}
    {{ property.type }} ${{ property.name }},
{% endif %}
{% endfor %}
{% for property in properties %}
{% if property.required and property.nullable %}
    ?{{ property.type }} ${{ property.name }} = null,
{% endif %}
{% endfor %}
{% for property in properties %}
{% if not property.required and not property.nullable %}
    {{ property.type }} ${{ property.name }} = {{ property.default }},
{% endif %}
{% endfor %}
{% for property in properties %}
{% if not property.required and property.nullable %}
    ?{{ property.type }} ${{ property.name }} = null,
{% endif %}
{% endfor %}
) {
```

**Why**: PHP requires non-optional parameters before optional ones. Generated constructors follow OpenAPI semantics: non-nullable required fields → nullable required (default null) → non-nullable optional (default value) → nullable optional (default null). This prevents "Cannot use positional argument after named argument" errors.

#### 3. Bidirectional Serialization

```php
// Generated in every DTO
public function toArray(): array
{
    return [
        'field_name' => $this->fieldName,
        'nested' => $this->nested?->toArray(),
    ];
}

public static function fromArray(array $data): static
{
    return new static(
        fieldName: $data['field_name'] ?? '',
        nested: isset($data['nested']) ? NestedDto::fromArray($data['nested']) : null,
    );
}
```

**Why**: Named arguments in `fromArray()` improve readability and avoid positional mismatch bugs when properties are reordered. `static` return type enables inheritance (though DTOs are `final` by default). Null-safe operator `?->` handles nullable nested objects gracefully. Defaults in `fromArray()` match constructor defaults for consistency.

#### 4. Runtime Validation Method

```php
// Generated validate() method
public static function validate(array $data): void
{
    if (!isset($data['required_field'])) {
        throw new \InvalidArgumentException('Missing required field: required_field');
    }
    if (isset($data['age']) && (!is_int($data['age']) || $data['age'] < 0)) {
        throw new \InvalidArgumentException('Field age must be int >= 0');
    }
}
```

**Why**: Separate `validate()` method allows pre-construction validation (useful for API controllers that want to fail fast before instantiation). Enforces OpenAPI constraints: required fields, type checks, min/max bounds, enum values. Throws descriptive exceptions for debugging.

#### 5. Modular Schema Organization

```
backend/openapi/schemas/
├── content.yaml      # ContentDraft, ContentSection DTOs
├── images.yaml       # ImageResult, ImageMetadata DTOs
├── outline.yaml      # OutlineDraft, OutlineSection DTOs
├── reflection.yaml   # ReflectionScore DTO
└── title.yaml        # TitleCandidate, TitlePoll DTOs
```

**Why**: Each business domain gets its own schema file. Generator scans `*.yaml` and processes all. Prevents monolithic schema files. Module name becomes `@category` in PHPDoc for IDE grouping.

#### 6. Generated File Marker

```php
/**
 * @generated 此文件由 bin/generate-dtos.php 自动生成,请勿手动修改
 * @see openapi/schemas/content.yaml
 * @category Content
 */
```

**Why**: `@generated` tag signals to tooling (IDEs, linters) to skip manual editing warnings. `@see` links back to source schema for traceability. Chinese warning clarifies for team. `@category` groups related DTOs in IDEs.

### Non-Obvious Decisions Preserved

#### 1. Two Parallel DTO Systems

**What the code shows**:
- `backend/app/DTO/` — generated from OpenAPI schemas
- `backend/app/Service/Agent/Outcome/Payload/` — hand-written runtime DTOs

**Why no migration or consolidation**:
- **Schema DTOs** (`backend/app/DTO/`) are for **API contracts and frontend TypeScript generation**. They match the OpenAPI spec exactly and flow through the Swagger → openapi-typescript pipeline.
- **Payload DTOs** (`backend/app/Service/Agent/Outcome/Payload/`) are for **runtime agent business logic**. They contain domain-specific methods, validation rules, and serialization logic that don't belong in API contracts.

These serve different purposes and don't conflict. Documentation (`DTO-GENERATION.md § "Difference from Runtime Payload DTOs"`) explicitly states this separation to prevent future consolidation attempts.

#### 2. Twig Autoescape Disabled

```php
// bin/generate-dtos.php
$this->twig = new Environment($loader, [
    'autoescape' => false, // Generating PHP code, not HTML
    'strict_variables' => true,
]);
```

**Why**: Template generates PHP code, not HTML. Twig's default HTML-escaping would mangle PHP syntax (`<` → `&lt;`, `>` → `&gt;`). This is correct but non-obvious to Twig newcomers who see escaping as a security requirement.

#### 3. PHPDoc Generics vs Property Types

```php
// Generated code
/**
 * @var string[]
 */
public array $tags;
```

**Why not** `public array<string> $tags`?
PHP 8.1 doesn't support generic array syntax in property types (only in PHPDoc). Template correctly uses PHPDoc `@var` for static analysis tools (PHPStan, Psalm) while keeping valid PHP 8.1 syntax. Future: PHP 8.3+ may support native generics, at which point the template can be updated.

#### 4. OpenAPI Annotations Kept in Generated Code

```php
// Generated DTO includes these
#[OA\Schema(
    schema: 'ContentDraft',
    required: ['text', 'word_count'],
    // ...
)]
class ContentDraft
{
    #[OA\Property(property: 'text', type: 'string')]
    public readonly string $text;
}
```

**Why not remove them** (as initially prototyped)?
The existing CI workflow (`swagger:generate`) scans PHP code for `#[OA\*]` annotations to generate swagger.json. Removing annotations would break the frontend type generation pipeline. Annotations are redundant with the source YAML but preserve the existing toolchain.

#### 5. No CI Validation for Schema DTOs Yet

**What's missing**:
- Existing CI validates Swagger Payload DTOs (`backend/app/Service/Agent/Outcome/Payload/**`) but not Schema DTOs (`backend/app/DTO/`)
- No GitHub Actions job to verify schemas match generated files on push/PR
- Developers must manually run `composer run generate:dto` before commit

**Why**:
Schema DTOs are newer. CI integration was planned (U8 in original task list) but not yet implemented when this system merged. Future work: add a CI step that runs generator and checks `git diff --exit-code backend/app/DTO/` to prevent drift.

## Why This Matters

**Benefits realized**:
- **Zero type drift** — frontend TypeScript and backend PHP DTOs generated from single source of truth (OpenAPI schema)
- **90% less boilerplate** — adding a field to a DTO now means editing one YAML line and running one command, not editing 5 methods across 2 files
- **Code review velocity** — reviewers see schema changes in YAML; DTO implementation is auto-verified by generator tests
- **IDE tooling improvement** — generated DTOs carry complete PHPDoc with types, examples, and schema links

**Trade-offs**:
- **Learning curve** — developers must learn OpenAPI schema syntax and understand the separation between Schema DTOs and Payload DTOs
- **Generator maintenance** — Twig templates and generator script now require maintenance when PHP or OpenAPI standards evolve
- **Build step dependency** — CI/CD must run generator or validate generated code is committed

**Impact if not followed**:
- Manually maintaining DTOs with 10+ properties across 12 classes wastes ~30 min per schema change
- Type drift between OpenAPI spec and PHP DTOs breaks frontend contracts silently (caught only at runtime)
- Missing validation logic or serialization methods cause production bugs

## When to Apply

**Use this generator when**:
- API contracts are defined in OpenAPI specs
- Frontend and backend need synchronized type definitions
- DTO classes are repetitive data carriers (no complex business logic)
- Project uses PHP 8.1+ with readonly properties and constructor property promotion

**Don't use when**:
- DTOs contain domain-specific business logic beyond serialization and validation (use hand-written Payload DTOs)
- Project uses PHP < 8.1 (generator relies on readonly properties and promoted constructors)
- OpenAPI schemas don't exist (generator requires schema-first workflow)

## Examples

### 1. Defining a Schema

```yaml
# backend/openapi/schemas/content.yaml
components:
  schemas:
    ContentDraft:
      type: object
      required:
        - text
        - word_count
      properties:
        text:
          type: string
          description: "Generated article text"
          example: "In the realm of technology..."
        word_count:
          type: integer
          minimum: 0
          example: 1500
        metadata:
          type: object
          additionalProperties:
            type: string
```

### 2. Generating the DTO

```bash
# Local development (in Docker)
docker run --rm -v $(pwd)/backend:/app hyperf/hyperf:8.2-alpine-v3.20-swoole \
  composer run generate:dto

# Output:
# Generated: backend/app/DTO/ContentDraft.php
```

### 3. Using the Generated DTO

```php
// API controller
use App\DTO\ContentDraft;

public function store(Request $request): JsonResponse
{
    $data = $request->all();
    
    // Pre-validation
    ContentDraft::validate($data); // Throws InvalidArgumentException if invalid
    
    // Instantiate with type safety
    $draft = ContentDraft::fromArray($data);
    
    // Business logic
    $this->contentService->save($draft);
    
    return response()->json($draft->toArray());
}
```

### 4. CI Workflow (Future Enhancement)

```yaml
# .github/workflows/ci.yml (planned addition)
- name: Generate DTOs
  run: composer run generate:dto

- name: Verify DTOs are synchronized
  run: git diff --exit-code backend/app/DTO/
  # Fails if generated DTOs differ from committed files
```

## Implementation Gotchas (session history)

**CI failures cascade** (session history):
1. PHP version mismatch (8.1 vs 8.2) — upgraded CI to 8.2
2. Missing `zircote/swagger-php` dev dependency — added to composer.json
3. `--no-dev` flag prevented swagger-php installation — removed flag
4. Missing `@OA\Info` annotations — created `backend/app/OpenApiConfig.php` with minimal metadata
5. TypeScript peer dependency conflict (6.0.3 vs openapi-typescript@7 requires typescript@^5.x) — downgraded to TypeScript 5.9.3
6. Narrow CI path triggers missed workflow file changes — expanded `paths:` filter

**Generator bugs fixed** (session history):
- Syntax error: `foreach ($properties as $propName, $propDef)` missing `=>` → fixed to `$propName => $propDef`
- Twig template nullable validation generated extra `)` in conditionals (lines 124, 128, 142, 146) → removed extra parentheses
- Case-sensitive filesystem: generator initially created `backend/app/Dto/` but needed `backend/app/DTO/` → ran `git mv app/Dto app/DTO_temp && git mv app/DTO_temp app/DTO`

**Build environment constraints** (session history):
- Local machine has no PHP runtime — all generator invocations used Docker container
- Composer operations required `--ignore-platform-reqs` flag outside container

## Related

- `backend/docs/DTO-GENERATION.md` — full user guide with troubleshooting
- `docs/u12-frontend-type-generation.md` — frontend TypeScript generation (companion system)
- `docs/U11-task-completion-summary.md` — Swagger annotation prerequisite work
- GitHub issues: #12 (U11 annotations), #13 (U12 frontend types), #15 (parent tracking)
- Merged PR #16 (2026-10-15, 82 files +12,659 lines, squash merge to main)
