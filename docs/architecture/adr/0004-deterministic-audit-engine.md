# ADR 0004: Deterministische, reproduzierbare Audit Engine

Status: Entwurf, 2026-10-08. Vorbereitung Phase 7–10; noch keine Audit-Implementierung.

## Context

Die bestehende HGA-Qualitätsprüfung liefert Heuristiken, unverbindliche AI-Ergebnisse und einen durch AI veränderbaren Gesamtstatus. Ein Report-Array ohne Versionierung reicht nicht für einen späteren Nachweis. Fremde Abrechnungen sind keine bestätigte eigene Buchhaltung.

## Decision

Audits laufen als reine fachliche Auswertung eines normalisierten, versionierten Inputs. Interne mathematische Regeln rechnen in Anwendungscode. Knowledge liefert geprüfte Parameter/Anwendbarkeit und Referenzen; Governance liefert bestätigte individuelle Vorgaben. Kein LLM entscheidet einen deterministischen Vergleich.

`AuditRun` speichert WEG/Periode, Zeitpunkt, application-/engine-Version, Packageversion und Checksumme, Rule-IDs/-Versionen, Governance-Revisionen, Rechtsstand soweit bekannt, Inputschema und vollständigen geschützten Snapshot plus kanonischen Hash. Run-Status `pending/running/completed/failed` ist getrennt vom fachlichen Gesamtergebnis. Snapshot enthält auch Rundungspolitik, Teilnehmer, zeitbezogene MEA/Flächen, Soll/Ist, Evidenzversionen und relevante Konfiguration. Gleiche Bytes/Semantik werden stabil serialisiert; volatile Laufzeitfelder gehören nicht in den fachlichen Inputhash.

Jede Regel erzeugt eine `RuleEvaluation`: `pass`, `fail`, `not_applicable`, `not_evaluated` oder technischer Fehler. Fehlende Inputs, unbekannte Versionen und ungelöste Konflikte erzeugen niemals stilles PASS. `review_required` ist ein Prüf-/Reviewzustand, keine fünfte Severity.

`AuditFinding` enthält Rule-ID/-Version, Run, Typ, Severity `info/warning/error/critical`, Titel/Beschreibung, actual/expected/difference, optional Geldwirkung, betroffene Position/Einheit, Evidence, Rechenweg und Findingstatus `open/confirmed/dismissed/resolved`. Das ursprüngliche Rechenergebnis bleibt unveränderlich; spätere Bearbeitung erfolgt als historisierte Entscheidung. Dismissal macht einen fehlgeschlagenen mathematischen Check nicht erfolgreich. Geldwirkungen mehrerer Findings können denselben Fehler betreffen und dürfen nicht blind addiert werden.

V1 prüft Summen, Einheits-/MEA-Gesamtheit, vollständige Kostenverteilung, Anteilrechnung, Soll-/Ist-/Abrechnungsspitzenidentitäten, Rücklagenarithmetik und Rundung. Identitätsbasierte doppelte Imports sind deterministisch; gleicher Betrag/Datum/Text allein ist nur ein Duplikatverdacht. Heiz-/Steuer-/Governancechecks prüfen ausschließlich ausreichend definierte Anwendbarkeit und bekannte Inputs.

LLM-Erklärungen erhalten nur strukturierte Finding-Daten und freigegebene Quellen. Sie sind getrennt versioniert und sichtbar gekennzeichnet. Sie dürfen RuleEvaluation, Betrag, Severity oder Status nicht schreiben.

Self Audit: später erstellt der Statement Builder einmal eine unveränderliche StatementVersion, auditiert genau diese und rendert genau diese. Veröffentlichbare neue Abrechnungen benötigen vollständige erforderliche deterministische Checks ohne verletzte Invarianten. Fehler, technische Ausfälle und unprüfbare Pflichtchecks führen in Review. Eine interne Vorschau muss als Entwurf kenntlich sein. Optionale Prüfungen haben ein gesondertes Coverage-Ergebnis; kein umfassendes PASS bei unbekannter Prüfabdeckung.

ImportedStatementCandidate bleibt separat, mit Confidence/Evidence je Feld. Der Auditadapter erzeugt denselben Inputvertrag, ohne Kandidaten in AccountingEntries umzuwandeln. Bestätigung/Übernahme ist ein eigener Vorgang.

## Alternatives

- `HgaQualityCheckService` nur um Prompts erweitern: keine reproduzierbare Autorität.
- Rules als beliebiger ausführbarer Code aus Knowledge: unsichere Ausführungsgrenze.
- Nur Findings/Hashes speichern: Ergebnisse lesbar, aber nicht nachrechenbar.

## Consequences

Snapshots und Evaluations erfordern private Persistenz und Versionsaufbewahrung. Engine-/Paketversionen müssen später verfügbar bleiben. Rechenregeln brauchen unabhängige Sollwerte, damit Builder und Audit nicht denselben Fehler unbemerkt teilen. Bestehende UI-/Severitybezeichnungen brauchen einen Adapter, keine stille Umdeutung.

## Migration Strategy

Legacy-Qualitätsprüfung zunächst erhalten und als separate Funktion betrachten. Neue arithmetische Engine offline testen, dann parallel auf Legacy-Snapshots anwenden. Erst nach Golden-/Mandanten-/Grenzfalltests UI anbinden; anschließend Self-Audit-Gate beim neuen Statement-Pfad. Alle Erzeugungswege (CLI, beide Controller, Preview) beim Cutover berücksichtigen. Historische Ergebnisse werden nicht als versionierte AuditRuns ausgegeben, wenn ihre Inputs nicht rekonstruierbar sind.
