# Release checklist

## Implemented in 1.0.0

- [x] Native Kimai plugin registration, settings menu and invoice action.
- [x] English and German UI, customer-language templates and retained unused templates.
- [x] Independent customer greeting, subject and message overrides.
- [x] Separate preparation/review, native mailer delivery and final-PDF .eml download.
- [x] CSRF, invoice/customer authorization, attachment snapshot and recipient test restriction.
- [x] Persistent duplicate protection and conservative handling of uncertain transport outcomes.
- [x] English/German documentation and a source-allowlisted ZIP builder.

- [x] Settings browser acceptance: envelope icon, one Save button and visible confirmation.
- [x] All 16 DE/EN greeting/subject/message override combinations verified in staging.

## Release acceptance

- [x] Customer field grouping and English/German edit/save/readback verified.
- [x] Thunderbird manual acceptance: user confirmed editable recipient, subject, message and PDF attachment.
- [x] Responsive settings, preparation, preview and maintenance at 390px.
- [x] Verify the generated .eml opens as an editable message in current Thunderbird with its PDF intact.
- [x] Verify Kimai 2.66.0 and 2.67.0 compatibility.
- [x] Removal/reinstallation with 50 unchanged database/file hashes.
- [x] Add an administrator recovery workflow with recorded decision, stale-form protection and permissions.
- [x] Classify SMTP failures using local protocol tests and display corrective guidance.
- [x] Optional default-on New-to-Pending transition after acceptance, preserving terminal/concurrent changes.
- [x] Compact old personal receipt details while retaining duplicate protection.
- [x] Install release ZIP and complete package acceptance on both supported versions.

## Future work outside 1.0.0 support

- [ ] Move native acceptance into public CI; unit, protocol and state tests already run there.
- [ ] Validate distributed/multi-instance storage before claiming support.

- [ ] Additional UI translations.
- [ ] Multiple recipients and optional CC/BCC with explicit review.
- [ ] Provider-specific Sent-folder integrations where necessary.
- [ ] Full delivery-attempt history and advanced retention policies; minimal-marker compaction is implemented.

No automatic invoice creation, status-triggered sending or SMTP password management is planned for the initial scope.

See [validation evidence](docs/VALIDATION.md) for tested boundaries.
