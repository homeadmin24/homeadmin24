# ADR 0002: Evidence und Provenance

Status: Entwurf, 2026-10-08. Betrifft H2.3 und alle späteren Extraktions-/Auditpfade.

## Context

Dokumente referenzieren Dateien und Rechnungen, aber keine unveränderlichen Dateiversionen oder einzelnen extrahierten Felder. CSV-Zeilen verlieren ihre Verbindung zur Zahlung. Confidence steht global oder in Freitext. HGA-PDF/JSON werden bei erneuter Erzeugung überschrieben. Damit ist „woher stammt dieser Wert?“ nicht zuverlässig beantwortbar.

## Decision

`Dokument` bleibt die bestehende Dokumentidentität. Additiv erhält sie `DocumentVersion`: WEG, Dokument-FK, Versionsnummer, unveränderlicher Storage-Key, SHA-256 über Dateibytes, MIME, Größe, Erfassungszeit. Historischer Erfassungszeitpunkt und heute berechneter Hash werden nicht mit dem ursprünglichen Uploadzeitpunkt verwechselt. Derselbe Inhalt kann mehrere autorisierte Dokumentreferenzen haben; ein Hash ersetzt weder Berechtigung noch Identität.

`EvidenceReference` enthält WEG, Quellart, DocumentVersion optional, Seite (1-basiert), Text-/Feldreferenz, optional Bounding Box mit Koordinatensystem, Extraktionsmethode, Provider-/Modell-/Parser-/Prompt-/Schemaversion, Confidence nullable, typisierten extrahierten Wert, Quellhash/-version und Erfassungszeit. CSV-Evidence benutzt Zeile/Spalte statt einer erfundenen PDF-Seite. Manuelle Angaben besitzen Autor/Zeit/Grund; Altwerte können `legacy_unverified` ohne fingierte Fundstelle sein.

Phase 2 bindet Evidence über eine explizite Entry↔Evidence-Verknüpfung mit `field_path` und Rolle `supports/contradicts/derived_from` an Entries. Weitere Aggregate bekommen später eigene FK-gesicherte Zuordnungstabellen. Keine freie Kombination aus `target_type/target_id` ohne referenzielle Integrität. Eine Entry kann mehrere Belege und ein Beleg mehrere Entries stützen.

Abgeleitete Werte verweisen auf die Input-Evidence und auf Algorithmus-/Regelversion plus Parameter. Beispiel: Einheitsanteil referenziert Gesamtkosten, MEA-Zähler/Nenner, Teilnehmerkreis und Rundungspolitik. Evidence speichert Herkunft; menschliche Bestätigung wird als eigene Reviewhandlung am fachlichen Ergebnis erfasst. Confidence allein bestätigt nichts.

Unveränderliche Audit-/Statement-Snapshots behalten die tatsächlich verwendeten Werte und Quellversionen. Ein Hash ohne aufbewahrten Input reicht nicht für Replay. Private Dokumente bleiben im autorisierten Storage, Knowledge-Pakete enthalten nie deren Texte oder Hash-/Identitätsverknüpfungen.

## Alternatives

- Nur `document_id`/Seite: Quelle kann überschrieben werden, Feldherkunft bleibt unklar.
- Vollständiger OCR-/Prompttext in jeder Buchung oder Logs: Duplikation und unnötige sensible Datenspeicherung.
- Beliebige polymorphe JSON-Verknüpfung: schnell erweiterbar, aber keine belastbaren FK-/Tenant-Grenzen.

## Consequences

Versionierte Dateien und Evidence benötigen Speicher- und Löschkonzept. „Unveränderlich“ gilt innerhalb der festgelegten Aufbewahrung; bei berechtigter Löschung werden fehlende Originalquellen und eingeschränkte Reproduzierbarkeit ausdrücklich kenntlich gemacht. Evidence kann auch unvollständig sein. UI/Audit dürfen fehlende Fundstellen nicht als gesicherte Beweiskette darstellen.

## Migration Strategy

Bestehende Pfade nicht umbenennen. Erst zusätzliche Version/Evidence für neu übernommene Quellen anlegen. Altdateien bei explizitem Backfill hashen und geschützt versionieren; unbekannte Seiten oder Extraktionsversionen als unbekannt belassen. Synthetische PDF-/CSV-/manuelle Quellen und absichtlich veränderte Bytes testen. Originale und referenzierte Versionen dürfen nicht durch Rechnung-/Dienstleisterlöschung kaskadierend verschwinden. Bestehendes `orphanRemoval` ist bei späterem Storage-Cutover entsprechend zu berücksichtigen.

Offen: Aufbewahrungsfristen/-zuständigkeit und genaue Koordinatenkonvention; kein Hindernis für das Schema mit explizit getyptem Locator.
