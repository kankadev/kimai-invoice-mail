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

Ein Speichern-Button speichert alle Einstellungen der Seite gemeinsam. Nach Erfolg erscheint eine sichtbare Bestätigung. Ungültige Vorlagen verhindern die gesamte Änderung. Bei den Kundenfeldern steht eine kopierbare Platzhalterhilfe.

Unter **Administration → Kunden → Bearbeiten** stehen im gemeinsamen Bereich **Rechnungs-E-Mail** drei optionale Felder in dieser Reihenfolge:

- **Abweichender E-Mail-Betreff**.
- **Abweichende E-Mail-Anrede**, z. B. `Liebe Alex,`.
- **Abweichende E-Mail-Nachricht**, einschließlich Grußformel.

Leer bedeutet immer Standard verwenden. **Billing Information bleibt ausschließlich für die Rechnungs-PDF zuständig.** Contact wird nur als vollständiger Kontaktname angeboten, nicht in Vor- und Nachname zerlegt.

Alle Platzhalter funktionieren in **Betreff, Anrede und Nachricht** – sowohl in den Sprachvorlagen als auch in den Kundenüberschreibungen. Nur den Code links einschließlich geschweifter Klammern kopieren. Die Erklärung rechts gehört nicht dazu. Beispiel: `Hallo {contact},`.

| Platzhalter zum Kopieren | Bedeutung |
| --- | --- |
| `{customer_name}` | Das Feld Name des Kunden in Kimai |
| `{company}` | Firma; bei leerem Firmenfeld der Kundenname |
| `{contact}` | Vollständiger Kontaktname, ohne Aufteilung |
| `{invoice_number}` | Nummer der ausgewählten gespeicherten Rechnung |
| `{invoice_date}` | Rechnungsdatum im Format JJJJ-MM-TT |
| `{due_date}` | Fälligkeitsdatum im Format JJJJ-MM-TT |
| `{total}` | Rechnungsbetrag einschließlich Währung, in der Kundensprache formatiert |
| `{currency}` | Währungskürzel, z. B. EUR oder USD |

Ein unbekannter Platzhalter wie `{unbekannt}` verhindert das Speichern der globalen Vorlage. Ein gültiger Platzhalter, dessen Wert fehlt, verhindert die Vorbereitung: Bei `{contact}` muss beispielsweise der Kontakt ausgefüllt sein. Vorlagen sind Klartext, kein ausführbarer Twig-Code.

## Ablauf

1. Unter **Rechnungen → Rechnungshistorie** im Menü einer gespeicherten Rechnung **E-Mail vorbereiten** wählen. Es gibt dort keinen Versand mit nur einem Klick.
2. In **E-Mail vorbereiten** Rechnung, Absender und PDF-Link prüfen. Empfänger, Betreff, Anrede und Nachricht sind vorausgefüllt und können für diese einzelne E-Mail geändert werden. Kundenfelder und Standardvorlagen bleiben dadurch unverändert.
3. **E-Mail prüfen** öffnet die schreibgeschützte Vorschau. Dort Empfänger, Text und PDF-Anhang kontrollieren.
4. Die gewünschte Aktion wählen:

| Aktion | Ergebnis |
| --- | --- |
| **Jetzt senden / Send now** | Sendet über Kimais konfigurierten Mailer. Der Direktversand muss in den Einstellungen aktiviert sein. |
| **E-Mail herunterladen (.eml)** | Lädt eine ungesendete E-Mail mit der endgültigen Rechnungs-PDF herunter. Kimai versendet dabei nichts. |
| **Neu beginnen / Start over** | Lädt die Kundenvorgaben erneut. Änderungen nur für diese E-Mail werden verworfen. |

Für manuellen Versand die EML in Thunderbird öffnen, prüfen oder bearbeiten und dort senden. Nur die E-Mail ist ungesendet; die PDF erhält keine Entwurfsmarkierung. Die Datei bleibt bis zum Löschen im Downloadordner. Das bearbeitbare Öffnen einschließlich PDF-Anhang wurde während der Entwicklung in Thunderbird bestätigt; andere Client-Versionen können abweichen.

Die Vorschau ist 30 Minuten gültig. Ändern sich Absenderkonfiguration oder PDF, muss die E-Mail neu vorbereitet werden. Das Plugin hängt die vorhandene gespeicherte PDF an, ohne eine Rechnung neu zu erzeugen oder zu verändern.

### Wiederholungswarnung

Die Checkbox für absichtlichen erneuten Versand erscheint nach einem früheren Direktversandversuch, den der Mailer angenommen hat. Sie basiert auf dem **eigenen Versandbeleg des Plugins** – nicht auf dem Rechnungsstatus New/Pending und nicht auf einer Postfachsuche. Bei einer Rechnung ohne solchen Beleg fehlt die Checkbox.

Ein EML-Download erzeugt keinen Versandbeleg. Einen späteren manuellen Versand über Thunderbird erkennt das Plugin nicht. Vor einem erneuten Versand deshalb selbst die gesendeten Nachrichten prüfen. Die Annahme durch den Mailer bestätigt außerdem noch keine Zustellung im Posteingang.

Rechnungsnummer, Status, Zahlungsdatum, Beträge, PDF und Zeiten bleiben unverändert. Der allgemeine Kunden-E-Mail-Wert ersetzt eine fehlende Rechnungs-E-Mail nicht automatisch. Zunächst wird genau eine Empfängeradresse unterstützt.

Bei unklarem SMTP-Ergebnis erfolgt kein automatischer Neuversand. Zuerst beim Anbieter prüfen. Auch die Ablage im Gesendet-Ordner hängt vom Mailanbieter ab. Hinweise zu absichtlichem Neuversand, Wiederherstellung, Datenspeicherung, Tests und Mehrinstanzbetrieb stehen in der [englischen Hauptanleitung](../README.md).

Updates erhalten Einstellungen, Kundenfelder und Versandbelege. Deaktivierung und Entfernung löschen keine Daten automatisch. Vor produktiver Nutzung zuerst eine vollständig getrennte Testumgebung verwenden.

Support und individuelle Anpassungen: **mail@kanka.dev** · **https://kanka.dev**. Keine echten Rechnungen oder Kundendaten in öffentlichen GitHub-Issues hinterlegen.
