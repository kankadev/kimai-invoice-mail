# Release validation

Version 1.0.0 supports self-hosted Kimai 2.66.0 and 2.67.0 on Linux with local persistent storage and a single synchronous SMTP transport. Test with your own provider before enabling direct sending. Validation is evidence for this scope, not a guarantee of error-free operation.

## Automated regression checks

CI runs PHP syntax checks, plain-text placeholder tests, local SMTP protocol fixtures, delivery-state tests and source-package construction. The SMTP fixture never relays messages. Run against a Symfony Mailer autoloader:

```sh
php Tests/TemplateTextTest.php
php Tests/SmtpSenderTest.php /path/to/vendor/autoload.php
php Tests/DeliveryStateTest.php /path/to/vendor/autoload.php
python3 tools/package.py
```

State tests cover accepted-history preservation, repeated failures, parallel/stale previews, recovery, legacy markers, locking, corrupt records and maintenance isolation. Protocol tests cover acceptance, authentication rejection, recipient rejection, temporary rejection, capacity rejection, DATA rejection, post-DATA disconnect, TLS failure and unsupported configuration.

## Native Kimai acceptance

Performed on isolated, sanitized installations with a mail catcher and no customer delivery:

- English/German settings and all 16 greeting/subject/message override combinations.
- Native preparation, read-only review, EML export and byte-identical saved PDF attachments.
- CSRF rejection, restricted-user denial, recovery inputs, both decisions and stale recovery submissions.
- Actual configured SMTP acceptance, Pending transition and replay rejection.
- Native status checks for paid/canceled invoices, disabled option, missing edit permission and concurrent payment.
- Fault injection before SMTP and after acceptance, verifying blocked/uncertain recovery behavior.
- Removal and reinstallation on Kimai 2.67: 50 hashes covering configuration, customer metadata, invoice/timesheet tables, PDFs and receipts remained unchanged.
- Responsive settings, preparation, preview and maintenance at a 390-pixel viewport. Thunderbird editable EML opening with attachment was confirmed manually.

Native acceptance is currently an operator-run suite, not entirely reproduced by public CI. Repeat these cases on a separate installation when changing Kimai versions, host storage, permissions or mail transport.

## Operating boundaries

- SMTP acceptance cannot prove inbox delivery, later bounces or Sent-folder behavior. No automatic retries or mailbox monitoring.
- Receipt files are required operational state. Keep them with the database backup; never delete them to bypass a warning. Old restored backups require checking later sends with the provider.
- Maintenance removes only selected personal receipt details, never invoices, customers, configuration or PDFs. Minimal duplicate markers remain.
- Unsupported for this release: distributed/multi-instance receipt storage, Windows hosting, Kimai Cloud, API/failover/queue transports and untested Kimai versions.
- No production data was used in public examples or tests. Credentials remain in Kimai's existing configuration.
