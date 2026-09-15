# Release checklist

## Implemented in the development preview

- [x] Native Kimai plugin registration, settings menu and invoice action.
- [x] English and German UI, customer-language templates and retained unused templates.
- [x] Independent customer greeting, subject and message overrides.
- [x] Separate preparation/review, native mailer delivery and final-PDF .eml download.
- [x] CSRF, invoice/customer authorization, attachment snapshot and recipient test restriction.
- [x] Persistent duplicate protection and conservative handling of uncertain transport outcomes.
- [x] English/German documentation and a source-allowlisted ZIP builder.

- [x] Settings browser acceptance: envelope icon, one Save button and visible confirmation.
- [x] All 16 DE/EN greeting/subject/message override combinations verified in staging.

## Before a stable release

- [ ] Complete interactive browser acceptance, including customer-field editing and mobile layout.
- [ ] Verify the generated .eml opens as an editable message in current Thunderbird with its PDF intact.
- [ ] Verify Kimai 2.67 compatibility; currently tested on 2.66.0 only.
- [ ] Move all integration scenarios into reproducible synthetic CI fixtures, including different permission combinations.
- [ ] Test update/deactivation/reinstallation against a retained settings and receipt snapshot.
- [ ] Add an audited administrator recovery workflow for uncertain deliveries; current recovery is documented and manual.
- [ ] Confirm filesystem locking behavior on additional supported deployment/storage platforms.
- [ ] Package, install and validate the release artifact on a reset test instance before a production pilot.

## Later, based on demand

- [ ] Additional UI translations.
- [ ] Multiple recipients and optional CC/BCC with explicit review.
- [ ] Provider-specific Sent-folder integrations where necessary.
- [ ] Full delivery-attempt history and retention controls.

No automatic invoice creation, status-triggered sending or SMTP password management is planned for the initial scope.
