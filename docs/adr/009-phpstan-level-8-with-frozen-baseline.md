# 009: PHPStan level 8, with a baseline that is never regenerated

| Field | Value |
|-------|-------|
| **Created By** | Troels Ugilt Jensen |
| **Date** | 2026-08-25 |
| **Decision Maker** | ITK Dev Economics team |
| **Stakeholders** | ITK Dev Economics team |
| **Status** | Accepted |

## Context

Economics computes money. An invoice line that is wrong because a nullable value was assumed
non-null is not a crash, it is a wrong number on a document sent to a client. Static analysis is the
cheapest defence available against that class of error, and null-safety in particular is what PHPStan
level 8 checks.

The codebase predates the level, so turning it on produced hundreds of errors in code that works. The
choice was between lowering the bar to what passes today and holding the bar while carrying the
existing errors as recorded debt.

### Drivers

- **Functional:** new code that touches financial calculation must be null-safe at the point it is
  written.
- **Non-functional:** the check must run in CI on every PR and be able to fail it.
- **Non-functional:** existing errors must not block unrelated work.
- **Non-functional:** the debt must stay visible and be able to shrink.

### Options Considered

1. **Lower the level to what passes clean.** Green immediately, no baseline to maintain. Gives up
   exactly the null-safety checks that matter most here, and there is no gradient left to climb —
   nothing tells you when the code has improved enough to raise it again.
2. **Level 8 with a frozen baseline.** New code is held to level 8 from the first line; the existing
   errors sit in `phpstan-baseline.neon` as an explicit ledger that can only shrink. Requires a rule
   that the baseline is never regenerated, or it silently absorbs new errors.
3. **Level 8 with no baseline, cleaned up in one push.** The honest end state. Hundreds of errors
   across the whole codebase is a change no one can review, and it blocks feature work while it runs.

## Decision

Option 2. `phpstan.dist.neon`:

```neon
includes:
    - phpstan-baseline.neon

parameters:
    level: 8
    paths:
        - src
        - tests
    excludePaths:
        - src/Kernel.php
```

Both `src` and `tests` are analysed — test code that lies about types hides the same bugs.
`phpstan-baseline.neon` currently holds around 626 ignored entries.

**New code must be clean, and the baseline is never regenerated to silence a new error.** The baseline
is a debt ledger, and regenerating it turns the ledger into a blindfold: the new error disappears
along with any evidence that it was ever raised. The only legitimate movements are downward — entries
removed as the code they describe is fixed.

When a new error appears, the options are to fix the code, or — if the error is a genuine false
positive — add a narrowly scoped ignore with a comment explaining why. Not a regeneration.

It runs via `composer code-analysis` (`task code-analysis`), and in CI on every PR
(`.github/workflows/pr.yml`), where it can fail the build.

## Consequences

### Positive

- New code gets level 8 null-safety from the first line, in the part of the codebase where a wrong
  assumption becomes a wrong invoice.
- The debt is enumerable. `phpstan-baseline.neon` shows exactly what is outstanding and where.
- The ratchet only turns one way, so the codebase improves monotonically without a big-bang cleanup.
- Analysing `tests` keeps the test suite from becoming a blind spot.

### Negative / Trade-offs

- The baseline is large, so a file with baselined entries gives weaker feedback than a clean one, and
  the analysis output alone does not tell you a file is partly exempt.
- The no-regeneration rule is social, not enforced: nothing in CI distinguishes a baseline that shrank
  from one that was regenerated, so a review has to catch it.
- Refactoring a baselined file can require fixing pre-existing errors that the change did not cause,
  because the baseline entries are matched by message and location.
- `src/Kernel.php` is exempt entirely rather than baselined.

### Follow-up Actions

- [ ] Consider a CI check that fails when `phpstan-baseline.neon` grows, making the ratchet mechanical
      rather than a review responsibility.
- [ ] Chip away at the baseline opportunistically — when touching a file, clear its entries.
