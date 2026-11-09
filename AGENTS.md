# Recruitment migration context

For the APEC Homes recruitment migration, read these files before editing:

1. `docs/LARAVEL_MIGRATION_HANDOFF.md`
2. `docs/MIGRATION_PROGRESS.md`
3. `docs/SOURCE_REFERENCE_MANIFEST.json`

The working reference project is `C:\xampp\htdocs\cif`. Preserve its current design and behavior for HR, Department requestors and Candidates. Do not infer complete requirements from generic Laravel scaffolding or the older README alone. Read the relevant current PHP/JavaScript and tests when implementing a feature.

Keep migration progress and validation evidence in `docs/MIGRATION_PROGRESS.md`, so work can continue across sessions. Mark modules verified only after meaningful behavior and visual comparisons. Record unresolved gaps and the next task.

Preserve existing user work and private environment settings. Develop against an isolated database; do not reset the original/hosted data or send real email as an incidental test. Never put secrets in documentation or frontend assets. A local code migration does not authorize publishing or replacing the hosted system.

Later explicit user instructions take precedence over the starting migration plan.
