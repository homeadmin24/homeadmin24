# ADR 0001: Canonical Accounting Model

Status: Entwurf, 2026-10-08. Betrifft H2.1–H2.4; abhängig von Baseline- und WEG-Zuordnungsprüfung.

## Context

`Zahlung` vermischt Bankbewegung und wirtschaftliche Klassifizierung. `Rechnung` ist ein Belegkopf, `WegEinheit` enthält aktuelle Eigentümer-/MEA-Strings. Jahreszuordnungen und Saldenstichtage sind verteilt; einige Abfragen ignorieren WEG oder enden am 30.12. Das bisherige HGA-Array ist weder Buchungsjournal noch versionierter Inputvertrag. Die Strangler-Migration muss bestehende Ergebnisse und Identitäten erhalten.

## Decision

Symfony und MySQL bleiben führend. Neue Fachservices unter `src/Accounting/`, später `src/Document/`, `src/Governance/`, `src/Audit/`, `src/Knowledge/`, `src/Statement/`. Neue Entities zunächst unter `src/Entity/Accounting/` bzw. `src/Entity/Document/`, solange das aktuelle ORM-Mapping nur `App\Entity` erfasst. Keine Umordnung des Bestands; Namespace-/Mapping-Tests sind Teil jeder Erweiterung.

| Modell | Mindestvertrag |
| --- | --- |
| `AccountingPeriod` | WEG, Start/Ende (inklusive fachlicher Datumswerte), Status `open/reconciliation/ready/closed`, locked_at, created_at, Revisionszähler; unique WEG/Start/Ende |
| `AccountingEntry` | WEG, Periode, exakter Betrag/Währung, Vorgangsart, Buchungsdatum, optionaler Leistungszeitraum, Kategorie/Kostenkonto, optional Dienstleister, source_type, Reviewstatus, Confidence nullable, Zeitstempel, Revision |
| `AccountingEntryRevision` | Unveränderlicher Werte-/Quellensnapshot mit Autor/Zeit/Änderungsgrund; bestätigte Fakten bleiben historisch nachvollziehbar |
| `BankAccount` | WEG, Währung, Kontotyp, interne Identität; Bankkennung nur bei Bedarf geschützt speichern |
| `BankTransaction` | WEG/Konto, exakter vorzeichenbehafteter Betrag, Buchungsdatum/Valuta, verfügbare Bankreferenz, Importquelle/-zeile/-Hash, Version; Legacy-Zahlung optional referenziert |
| `AccountingEntryInvoiceLink` | Entry↔Rechnung n:m, Rolle/ggf. zugeordneter Belegbetrag, explizite WEG-Konsistenz; ermöglicht Rechnungssplitting |
| `AccountingEntryPaymentAllocation` | Entry↔BankTransaction n:m, zugeordneter Teilbetrag/Währung, Status `proposed/confirmed/rejected/superseded`, Bestätiger/Zeit/Begründung/Revision |
| `LegacySourceMapping` | Quelltyp/-ID/-Version, WEG-Zuordnungsnachweis, Zielidentität, Importlauf; eindeutiger Idempotenzschlüssel |

Eine Entry steht für eine wirtschaftliche Position mit einer Kategorie. Gemischte Rechnungen können mehrere Entries begründen. Eine Rechnung und deren Bezahlung werden nicht zweimal als Aufwand erfasst. Bankgebühren können eine Entry ohne Rechnung mit Bank-Evidence haben. Gutschriften, Erstattungen und Transfers behalten ihre Semantik und Vorzeichen.

Vorschlag für Geldvertrag: EUR in Phase 2, Integer-Minor-Units im Rechenkern; Persistenz BIGINT mit expliziten Grenzen, API-/JSON-Werte als sichere Integer-/Dezimalstrings. Keine Float-Konvertierung an neuen fachlichen Grenzen. MEA und Verteilungsquoten als Zähler/Nenner bzw. exakte Dezimalwerte. Kosten positiv, Kostenminderungen negativ; Bankzufluss positiv/Abfluss negativ. Ein expliziter Settlement-Adapter übersetzt die Richtungen, statt blind `abs()` zu verwenden. Kategorie/Vorgangsart entscheidet über Kostenwirksamkeit; eine Reservekontobewegung ist nicht automatisch Aufwand.

Rundungsvorschlag für spätere Allocation Engine: exakte Quoten berechnen, Summe auf Cent erhalten, Restcents nach größtem Rest und stabiler Einheits-ID verteilen; für negative Beträge symmetrisch. Algorithmus und Parameter versionieren. Noch keine Änderung an Legacy-Floatberechnungen.

Perioden überschneiden sich innerhalb derselben WEG im neuen aktiven Modell nicht. Prüfen in Transaktion mit WEG-Lock; Unique Constraint allein verhindert keine Intervallüberschneidung. `ready` setzt erfüllte fachliche Voraussetzungen voraus; `closed` sperrt Änderungen. Korrekturen nach Abschluss benötigen einen nachvollziehbaren neuen Revisions-/Korrekturvorgang. Bankzahlungen außerhalb der Leistungs-/Abrechnungsperiode dürfen verknüpft werden und bleiben als solche sichtbar.

Tenant-Invariante: Entry.WEG = Period.WEG = verknüpfte Quelle.WEG; BankTransaction.WEG = BankAccount.WEG. Neue FK-/Serviceprüfungen und Zwei-WEG-Negativtests erzwingen dies. Legacy-Rechnungen ohne belegbare WEG werden vor Verknüpfung explizit zugeordnet. Keine Ableitung allein aus Dienstleister, Jahr oder „erste WEG“.

## Alternatives

- `Zahlung` direkt zum universellen Journal erweitern: schneller Start, aber unklare Identität und Doppelzählungsrisiko.
- Sofortiges vollständiges Double-Entry-Ledger: mögliches späteres Ziel, derzeit unnötige Scope-Erweiterung. AccountingEntry ist zunächst kein behauptetes Hauptbuch mit Soll/Haben-Balancierung.
- Nur JSON-Snapshots: einfach, aber schwache referenzielle Integrität und schlechte relationale Abfragbarkeit.
- Microservices/Event-Sourcing für alles: zusätzlicher Betriebsaufwand ohne nachgewiesenen Bedarf.

## Consequences

Mehrere kleine Tabellen sind nötig; dafür bleiben Beleg, Vorgang und Settlement trennbar. Ownership-/Flächen-/MEA-Historie wird vor verbindlicher neuer Abrechnung benötigt, jedoch nicht vollständig in Phase 2 implementiert. Fehlende Altinformationen werden als unbekannt gekennzeichnet, nicht rekonstruiert behauptet. Rollen-/WEG-Zugriff ist eine Freigabevoraussetzung für produktive neue Datenpfade.

## Migration Strategy

Zunächst Baseline reparieren und Schemaabweichungen auf synthetischem MySQL klären. Dann additive Perioden/Entries, Quellen und Bank-/Settlement-Tabellen. Backfill getrennt und idempotent, mit Dry-run, Herkunft und privatem Ablehnungsbericht. Zahlungsquelle und Rechnungsquelle erhalten unterschiedliche Rollen; referenzierte Zahlung erzeugt nicht zusätzlich eine zweite Kostenentry. Bestehende HGA liest weiter Legacy. Neue Reads laufen im Vergleichsmodus; Unterschiede bei Stichtagen, Gutschriften und Rundung sind fachlich zu prüfen. Umschaltung später je WEG/Periode hinter Feature-Flag, Rückfall auf Legacy ohne Datenlöschung.

Offen: Geldpersistenz endgültig bestätigen, Rundungsvertrag freigeben, historische Perioden-/Soll-Semantik festlegen, produktiven Tenant-Zugriff entscheiden.
