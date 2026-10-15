# Concepts

Shared domain vocabulary for this project — entities, named processes, and status concepts with project-specific meaning. Seeded with core domain vocabulary, then accretes as ce-compound and ce-compound-refresh process learnings; direct edits are fine. Glossary only, not a spec or catch-all.

## Agent Workshop

### Agent
A composable LLM-driven unit in the content creation workflow that performs one focused task (title generation, outline creation, content drafting, reflection). Each Agent receives structured input, makes an LLM call, and returns an AgentOutcome with a typed Payload DTO.

### AgentOutcome
The three-state return contract (OK, DEGRADED, FAILED) that every Agent returns. Carries a Payload DTO on success/degraded, or a failure reason on failed. This contract enables the orchestrator to handle degradation gracefully (retry, fallback, or continue with reduced quality) without catching exceptions.

### Payload DTO
Hand-written data transfer objects under `app/Service/Agent/Outcome/Payload/` that carry Agent business logic results at runtime. These DTOs contain domain-specific methods, validation rules, and serialization logic for caching. Distinct from Schema DTOs (below).

### Schema DTO
Auto-generated data transfer objects under `app/DTO/` that represent API contracts and drive frontend TypeScript type generation. Generated from OpenAPI schemas via the DtoGenerator tool. These DTOs are readonly, immutable, and contain only serialization/deserialization methods (`toArray()`, `fromArray()`, `validate()`). Schema DTOs define what crosses the API boundary; Payload DTOs define what flows through agent business logic.
