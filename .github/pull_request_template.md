## Summary
<!-- 1–2 sentences: what does this PR do and why -->

## Changes
- 
- 

## Type
- [ ] feat — new feature
- [ ] fix — bug fix
- [ ] chore — config / tooling / docs
- [ ] refactor — no functional change
- [ ] docs — documentation only

## Testing
- [ ] `vendor/bin/pint --test` passes
- [ ] `vendor/bin/phpstan analyse` passes (Larastan level 8)
- [ ] `vendor/bin/pest` passes
- [ ] Manually tested locally
- [ ] Tested on a real mobile device (if UI change)

## Multi-tenancy check (if data change)
- [ ] New tables include `tenant_id` + index
- [ ] Models extend `TenantScopedModel`
- [ ] Verified cross-tenant isolation (tenant A cannot see tenant B's data)

## Screenshots
<!-- For UI changes — paste before/after if applicable -->

## Notes for reviewer
<!-- Anything else worth flagging -->
