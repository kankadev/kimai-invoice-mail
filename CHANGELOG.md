# Changelog

## 1.0.0

- Preserve accepted delivery history through failed repeats and maintenance.
- Bind every reviewed send to the receipt state under its lock, invalidating stale sessions after recovery.
- Synchronize directory entries before SMTP and show the original recipient during recovery.
- Include release checklist in the source package.
- Validate Kimai 2.66/2.67, lifecycle retention, responsive views and SMTP/state regressions.

## 0.1.0-dev

Initial development preview: multilingual invoice email templates, customer overrides, reviewed delivery using Kimai's mailer, and .eml download with the stored PDF. Direct sending is disabled until configured. Not yet a stable production release.

### Settings usability
- Use Kimai’s native envelope menu icon.
- Save all settings together with visible confirmation and validation before writing.
- Explain customer placeholders beside override fields.
- Verified all 16 DE/EN override combinations and settings HTTP roundtrips in Kimai 2.66.

### Customer email fields
- Group subject, greeting and message in one native form fieldset with shared placeholder guidance.
- Use distinct form block names to prevent dynamic text-field theme collisions.
- Verify grouped field saving in English/German and 16 prepared email/PDF combinations.

### Placeholder guidance and workflow documentation
- Show one shared, translated placeholder table in customer forms and settings.
- Separate exact copyable tokens from explanations, one placeholder per row.
- Document preparation, read-only review, direct sending, EML download and repeat-send behavior in English and German.

### Delivery outcomes and operations
- Add an enabled-by-default option to mark new invoices Pending after recorded SMTP acceptance.
- Distinguish definite SMTP rejection from uncertain outcomes and offer actionable translated guidance.
- Add administrator recovery with documented decisions, CSRF and stale-receipt protection.
- Add manual removal of old personal receipt details without deleting duplicate markers.
- Use the existing MAILER_URL synchronously through Symfony’s SMTP factory; reject unsupported transport types.
- Add local SMTP protocol fixtures and CI coverage.
