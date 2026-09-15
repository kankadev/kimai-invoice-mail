# Invoice Mail für Kimai

Entwickelt von **[kanka.dev](https://kanka.dev)**. Experimenteller Entwicklungsstand, noch keine stabile Version. Aktuell auf selbst gehostetem Kimai 2.66.0 geprüft.

## Einrichtung

1. Datenbank und Kimai-Datenverzeichnis sichern.
2. Plugin unter `var/plugins/KankaInvoiceMailBundle` ablegen.
3. Im Kimai-Verzeichnis als Anwendungsbenutzer `bin/console kimai:reload --env=prod` ausführen.
4. **System → Rechnungs-E-Mail** öffnen. Dafür ist die Berechtigung `system_configuration` erforderlich.
5. Absender-Anzeigenamen sowie Sprachvorlagen einschließlich eigener Grußformel prüfen. Direktversand erst nach einem Test einschalten. Die vorhandene Kimai-SMTP-Konfiguration wird verwendet.

## Sprache und Kundenfelder

Die Oberfläche folgt der Kimai-Benutzersprache; Englisch und Deutsch werden mitgeliefert. Die Sprache der E-Mail wird unabhängig davon über den jeweiligen Kunden bestimmt.

Die Einstellungsseite zeigt die bei Kunden verwendeten Sprachen mit Kundenzahl. Fehlt Deutsch, zunächst beim betreffenden Kunden Deutsch einstellen. Bereits gespeicherte Vorlagen bleiben bei Sprachwechseln erhalten. Auch archivierte Kunden werden berücksichtigt.

Drei optionale Kundenfelder überschreiben jeweils den Sprachstandard:

- **Abweichende E-Mail-Anrede**, z. B. `Liebe Alex,`.
- **Abweichender E-Mail-Betreff**.
- **Abweichende E-Mail-Nachricht**, einschließlich Grußformel.

Leer bedeutet immer Standard verwenden. **Billing Information bleibt ausschließlich für die Rechnungs-PDF zuständig.** Contact wird nur als vollständiger Kontaktname angeboten, nicht in Vor- und Nachname zerlegt.

Platzhalter: `{customer_name}`, `{company}`, `{contact}`, `{invoice_number}`, `{invoice_date}`, `{due_date}`, `{total}`, `{currency}`. Unbekannte Platzhalter und verwendete leere Angaben verhindern die Vorbereitung. Vorlagen sind Klartext, kein ausführbarer Twig-Code.

## Ablauf

In der Rechnungshistorie **E-Mail vorbereiten** wählen, Empfänger und Texte bearbeiten, anschließend **E-Mail prüfen**. Die Vorschau zeigt den tatsächlichen Absender, Empfänger und die bestehende Rechnungs-PDF. Erst **Jetzt senden** löst den Versand aus.

**E-Mail herunterladen (.eml)** dient dem manuellen Versand im Mailprogramm. Nur die E-Mail ist ungesendet; die PDF bleibt eine endgültige Rechnung. Die heruntergeladene Datei bleibt bis zum Löschen im Downloadordner. Thunderbird muss mit der verwendeten Version geprüft werden.

Rechnungsnummer, Status, Zahlungsdatum, Beträge, PDF und Zeiten bleiben unverändert. Der allgemeine Kunden-E-Mail-Wert ersetzt eine fehlende Rechnungs-E-Mail nicht automatisch. Zunächst wird genau eine Empfängeradresse unterstützt.

Bei unklarem SMTP-Ergebnis erfolgt kein automatischer Neuversand. Zuerst beim Anbieter prüfen. Auch die Ablage im Gesendet-Ordner hängt vom Mailanbieter ab. Hinweise zu absichtlichem Neuversand, Wiederherstellung, Datenspeicherung, Tests und Mehrinstanzbetrieb stehen in der [englischen Hauptanleitung](../README.md).

Updates erhalten Einstellungen, Kundenfelder und Versandbelege. Deaktivierung und Entfernung löschen keine Daten automatisch. Vor produktiver Nutzung zuerst eine vollständig getrennte Testumgebung verwenden.

Support und individuelle Anpassungen: **mail@kanka.dev** · **https://kanka.dev**. Keine echten Rechnungen oder Kundendaten in öffentlichen GitHub-Issues hinterlegen.

Ein Speichern-Button speichert alle Einstellungen der Seite gemeinsam. Nach Erfolg erscheint eine sichtbare Bestätigung. Ungültige Vorlagen verhindern die gesamte Änderung. Bei den Kundenfeldern steht eine kopierbare Platzhalterhilfe.
