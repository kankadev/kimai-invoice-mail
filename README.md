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
- Keeps invoice numbers, invoice status, payment dates, PDF content and timesheets unchanged.

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

Kimai's `MAILER_URL` and `MAILER_FROM` remain authoritative. The plugin does not store another SMTP password. See [Kimai's email documentation](https://www.kimai.org/documentation/emails.html).

## Templates and customer overrides

The settings page lists languages actually assigned to customers, with a customer count. To add a language, change a customer's language in Kimai. Previously saved templates remain available if no customer currently uses that language. Archived customers are included in the count, since their saved invoices can still require correspondence.

English and German start with neutral editable defaults. A locale variant can inherit its own base language. An unsupported language requires a template before preparation; the plugin does not silently switch to English. Updates do not replace saved templates.

Each customer has three optional fields:

| Field | Empty value | Example override |
| --- | --- | --- |
| Email greeting | Language default | `Hello Alex,` |
| Email subject | Language default | `Invoice {invoice_number} — service agreement` |
| Email message | Language default | Your complete message and closing signature |

The greeting is placed above the message with a blank line. There are no inferred first-name or last-name fields. **Billing information continues to control the invoice PDF only.**

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

Open **Invoice history**, select **Prepare email** from an invoice's actions, edit the recipient/text if necessary, then review. The final screen shows the sender, recipient, language, subject, message and stored PDF attachment. A preview expires after 30 minutes. If the sender configuration or PDF changes, prepare a new preview.

`Download email (.eml)` creates an email for a compatible mail client; it does not send anything or change the invoice. The download remains on your computer until you remove it. Thunderbird handling must be checked with your installed version.

Direct sending requires both `create_invoice` and access to the invoice/customer. The mailer accepting a message does not prove inbox delivery. Sent-folder behavior depends on the mail provider; SMTP alone does not promise a Sent copy. Do not configure an additional Sent copy without checking the provider's existing behavior.

## Duplicate and error handling

Only an explicit POST with a valid session and CSRF token can send. A per-invoice filesystem lock and persistent receipt prevent duplicate submissions, including simultaneous requests. A deliberately repeated send requires a new preview and explicit confirmation after a previously accepted attempt.

Before contacting SMTP, the plugin records an uncertain attempt. Transport errors or interrupted requests are not retried automatically, since the provider may already have accepted the message. Check your provider first. In this development version, clearing an uncertain receipt is an administrator recovery operation: stop sending, back up the receipt, verify delivery with the provider, then move that invoice's receipt out of the active receipt directory before intentionally preparing again. Never clear a receipt while sending is in progress.

Receipts are stored under Kimai's data directory in `kanka-invoice-mail/` and contain recipient, user ID, time and outcome. Include them in backups. Multi-instance deployments must share this directory on storage supporting reliable `flock` and atomic rename; distributed/cloud storage has not been validated.

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

Run `php Tests/TemplateTextTest.php` for standalone template tests. `python tools/package.py` builds a ZIP from an explicit source allowlist. See [TODO.md](TODO.md) for remaining release work and [CHANGELOG.md](CHANGELOG.md) for changes.

Report reproducible issues on [GitHub](https://github.com/kankadev/kimai-invoice-mail/issues), using synthetic examples. For implementation support or custom integration work, contact **[kanka.dev](https://kanka.dev)** or **mail@kanka.dev**. Please do not include invoices, credentials or private customer information in public issues.

Licensed under AGPL-3.0-or-later. This is an independent plugin, not an official Kimai product.

All settings on the page are saved together using one Save button. A visible confirmation appears after success. Invalid templates prevent the entire update. Customer fields include a copyable placeholder reference.
