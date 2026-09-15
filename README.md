# Invoice Mail for Kimai

Prepare, review and send invoice emails from Kimai, or download an unsent email with the invoice attached for your desktop mail application.

Developed by **[kanka.dev](https://kanka.dev)**. [Deutsche Anleitung](docs/README.de.md)

**Development preview — not a stable release.** Tested on self-hosted Kimai 2.66.0. Later versions require verification; Kimai Cloud is not supported. Keep your production installation unchanged until you have tested the plugin on a separate instance.

## What it does

- Adds **Prepare email** to the actions of a saved invoice.
- Uses the customer's **invoice email**, with no silent fallback to their general email address.
- Provides an editable preparation form followed by a separate review screen.
- Sends only after **Send now**, through Kimai's existing mail configuration.
- Downloads an `.eml` file with `X-Unsent: 1` for manual sending. The attached PDF remains the existing final invoice; it is not marked as a draft.
- Supports English and German interface text independently of the customer's language.
- Offers greeting, subject and message templates for the languages used by customers, with optional overrides per customer.
- Optionally changes a new invoice to Pending after accepted direct sending; enabled by default. Paid and canceled invoices, payment dates, invoice numbers, PDFs and timesheets are preserved.

## Installation

Requires PHP 8.2 or newer and Kimai 2.66.0. No additional Composer packages, database tables or paid custom-fields plugin are required.

1. Back up your Kimai database and data directory.
2. Place this repository in `var/plugins/KankaInvoiceMailBundle` so that the bundle class is at `var/plugins/KankaInvoiceMailBundle/KankaInvoiceMailBundle.php`.
3. From your Kimai directory, run `bin/console kimai:reload --env=prod` using the normal application user. Allow sufficient PHP memory for cache warming.
4. Open **System → Invoice Mail**. The menu requires Kimai's `system_configuration` permission.
5. Set the sender display name and review your language templates, including your signature. Direct sending starts disabled. Enable it only after confirming your mail configuration and completing a test.

For an experimental checkout:

```sh
git clone https://github.com/kankadev/kimai-invoice-mail.git var/plugins/KankaInvoiceMailBundle
bin/console kimai:reload --env=prod
```

Kimai's `MAILER_URL` and `MAILER_FROM` remain authoritative. The plugin does not store another SMTP password. For direct sending, it opens a synchronous SMTP transport from that URL through Symfony’s transport factory; it does not enqueue messages. A single SMTP transport is supported. Failover, API, null and queue-only transports are not supported for this workflow. See [Kimai's email documentation](https://www.kimai.org/documentation/emails.html).

## Templates and customer overrides

The settings page lists languages actually assigned to customers, with a customer count. To add a language, change a customer's language in Kimai. Previously saved templates remain available if no customer currently uses that language. Archived customers are included in the count, since their saved invoices can still require correspondence.

English and German start with neutral editable defaults. A locale variant can inherit its own base language. An unsupported language requires a template before preparation; the plugin does not silently switch to English. Updates do not replace saved templates.

All settings on the page are saved together using one Save button. A visible confirmation appears after success. Invalid templates prevent the entire update. Customer fields include a copyable placeholder reference.

Under **Administration → Customers → Edit**, the **Invoice Mail** group contains three optional fields, in subject, greeting, message order:

| Field | Empty value | Example override |
| --- | --- | --- |
| Email greeting | Language default | `Hello Alex,` |
| Email subject | Language default | `Invoice {invoice_number} — service agreement` |
| Email message | Language default | Your complete message and closing signature |

The greeting is placed above the message with a blank line. There are no inferred first-name or last-name fields. **Billing information continues to control the invoice PDF only.**

**All placeholders work in subject, greeting and message**, both in language defaults and customer overrides. Copy only the code from the left column, including `{` and `}`. The explanation is not part of the placeholder. For example, enter `Hello {contact},`; do not append the words “Complete contact name”.

Available placeholders:

| Placeholder | Value |
| --- | --- |
| `{customer_name}` | Kimai customer name |
| `{company}` | Company, falling back to customer name |
| `{contact}` | Complete contact name, unchanged |
| `{invoice_number}` | Existing invoice number |
| `{invoice_date}` | Invoice date, `YYYY-MM-DD` |
| `{due_date}` | Due date, `YYYY-MM-DD` |
| `{total}` | Total formatted in the customer's language, including currency |
| `{currency}` | Currency code |

Unknown placeholders and referenced empty values are rejected. Templates are plain text, not executable Twig or HTML. One recipient address is supported in this initial version.

## Sending and manual email download

1. Open **Invoices → Invoice history** and choose **Prepare email** from the saved invoice’s actions. There is no one-click send action in the history.
2. On **Prepare email**, check the invoice, sender and PDF link. Recipient, subject, greeting and message are filled from the customer and language template. You can change them for this email; those edits do not change customer settings or defaults.
3. Select **Review email**. This screen is read-only. Check the recipient, subject, message and attached PDF before continuing.
4. Choose one of the following actions:

| Action | Result |
| --- | --- |
| **Send now** | Sends through Kimai’s configured mailer after explicit confirmation on this screen. Direct sending must be enabled in settings. |
| **Download email (.eml)** | Downloads an unsent email containing the same final PDF for manual sending in a compatible mail client. No email is sent by Kimai. |
| **Start over** | Returns to preparation and reloads customer defaults. Edits made only for the current email are discarded. |

For manual sending, open the `.eml` in Thunderbird, check or edit the message, and send it there. The email is unsent; the attached invoice is not a draft and receives no draft marking. The downloaded file remains in Downloads until you delete it. Editable opening with the attachment has been confirmed in Thunderbird during development; behavior can vary between client versions.

A preview expires after 30 minutes. If the sender configuration or PDF changes, prepare a new preview. The plugin attaches the existing saved PDF; it does not generate or modify the invoice.


Direct sending requires both `create_invoice` and access to the invoice/customer. The mailer accepting a message does not prove inbox delivery. Sent-folder behavior depends on the mail provider; SMTP alone does not promise a Sent copy. Do not configure an additional Sent copy without checking the provider's existing behavior.

## Invoice status

**Set new invoices to Pending after accepted direct sending** is enabled by default in plugin settings. It applies only after SMTP acceptance has been recorded, and requires the sender’s native `edit_invoice` permission. Paid, canceled and already pending invoices are preserved. A conditional database update also preserves a payment or cancellation saved concurrently. Disabling the option keeps the status unchanged.

Downloading an EML does not change the status; a later Thunderbird send cannot be detected. If email acceptance succeeds but updating the invoice fails or is not permitted, the result explicitly says that the email was accepted and asks you to update the status in Invoice history. **Do not resend to fix a status problem.**

## Errors and safe retries

| Result | What happens | Next step |
| --- | --- | --- |
| SMTP connection, TLS or login fails before message submission | Recorded as failed; no message was submitted | Correct Kimai’s mail settings or provider permissions, then prepare and review a fresh attempt |
| SMTP explicitly rejects the message with a 4xx/5xx response | Recorded as failed; the message was not accepted | Follow the displayed recipient, capacity, policy or temporary-limit guidance and prepare again |
| Connection breaks after sending begins, with no definite result | Recorded as uncertain; direct retry is blocked | Check provider records, then use administrator recovery below |
| SMTP accepts the message | Recorded as accepted; optional Pending update follows | A deliberate repeat requires a new preview and the repeat-send checkbox |
| Storage fails before sending | Sending is prevented | Check free space, permissions and the previous record |
| Storage fails after SMTP acceptance | Acceptance is explicitly reported, with recovery required | Do not resend; fix storage and confirm the outcome through recovery |

No automatic retries are performed. Raw SMTP diagnostics are not displayed or stored, since they can contain account details. Error descriptions suggest checks but cannot determine the exact provider-side cause. A mailbox-full condition may be rejected immediately, or may arrive later as a bounce **after** SMTP acceptance. The plugin does not read mailboxes, detect bounces, or prove inbox delivery. See [Symfony Mailer](https://symfony.com/doc/6.4/mailer.html) and [RFC 5321](https://www.rfc-editor.org/rfc/rfc5321).

The repeat-send checkbox is based on the plugin’s own receipt, not New/Pending or a mailbox search. An EML download creates no send receipt. Before repeating a manually sent email, check your sent messages yourself.

### Resolve an uncertain delivery

1. Open **Prepare email → Review email** for that invoice, or follow the link on its error result.
2. A user with **system settings permission** and access to that invoice/customer can open **Resolve delivery**. Other users see guidance to contact an administrator.
3. Check the provider’s sent messages or logs against invoice, recipient and time.
4. Choose either **provider acceptance verified** or **new attempt authorized**, and enter a brief explanation of the check. Do not enter secrets or email contents.
5. Save the decision. This action sends nothing. Confirmed acceptance applies the optional Pending update. A retry authorization requires a fresh preparation and review before sending.

The decision records actor, time, reason and outcome. Stale forms, concurrent attempts and duplicate submissions are rejected. Do not delete receipt files to bypass a warning. Corrupt records require restoring a valid backup or operator investigation; they are not silently treated as never sent.

### Storage and maintenance

The plugin keeps one small JSON record and one lock file per invoice under Kimai’s data directory, `kanka-invoice-mail/`. The record is replaced on each attempt and keeps limited previous-attempt/recovery context; it is **not** an unlimited delivery history. No email body or extra PDF is stored there. At 100 invoices per month this is 1,200 records plus small lock files per year, rather than 1,200 duplicated PDFs.

Open **System → Invoice Mail → Delivery record maintenance** to remove personal details from final records older than a chosen number of days (default 365, minimum 30). This removes recipient, user identifiers and notes while retaining the minimal outcome/nonce marker and lock for duplicate protection. Uncertain and retry-authorized records are excluded. A run processes at most 1,000 eligible records. Repeat if necessary. This is manual; no cron task is installed.

This does not delete invoices, customer data or configuration from Kimai’s database, and does not alter backups. Configure backup retention separately. Minimal markers remain even after an invoice is deleted; complete marker removal requires an offline, coordinated cleanup and is deliberately not exposed as a routine button. Multi-instance deployments must share the receipt directory on storage supporting reliable `flock` and atomic rename. Distributed storage has not been validated.

## Safe testing

Use a separate database, credentials, data directory and mail catcher. Do not test a production-data clone until its identities, free text, credentials and files have been sanitized.

An optional operator-controlled environment variable restricts direct-send recipients with an anchored regular expression, without surrounding delimiters:

```dotenv
KANKA_INVOICE_MAIL_TEST_RECIPIENT_PATTERN=^qa\+[a-z0-9-]+@example\.invalid$
```

This is an additional check, not a substitute for transport/network isolation. Configure an actual test mailbox pattern for external test delivery. `.eml` files are manually sendable outside the server's control. No test credentials or copied customer data belong in this repository.

## Updates and removal

Replace plugin files and reload Kimai. Preserve the data directory and database: language settings are stored as `kanka_invoice_mail.*` configuration keys, customer overrides as `kanka_mail_*` metadata, and delivery receipts as files. Disabling/removing the plugin does not delete those values. There is no automatic uninstall cleanup.

## Development and support

Run `php Tests/TemplateTextTest.php` for standalone template tests. Run `php Tests/SmtpSenderTest.php /path/to/vendor/autoload.php` with Symfony Mailer available for local-only SMTP protocol tests. These tests start disposable loopback servers and never relay mail. `python tools/package.py` builds a ZIP from an explicit source allowlist. See [TODO.md](TODO.md) for remaining release work and [CHANGELOG.md](CHANGELOG.md) for changes.

Report reproducible issues on [GitHub](https://github.com/kankadev/kimai-invoice-mail/issues), using synthetic examples. For implementation support or custom integration work, contact **[kanka.dev](https://kanka.dev)** or **mail@kanka.dev**. Please do not include invoices, credentials or private customer information in public issues.

Licensed under AGPL-3.0-or-later. This is an independent plugin, not an official Kimai product.
