# ADR 0005: Externes, versioniertes weg-knowledge

Status: Entwurf, 2026-10-08. Grundlage Phase 1; Repository wird erst nach Freigabe angelegt.

## Context

Allgemeines Wissen und Regeln dürfen keine WEG-Kundendaten enthalten. Heute liegen Plausibilitätsschwellen und Rechtsverweise im Anwendungscode/Prompts. Es fehlen unabhängig validierte Rule-/Source-Versionen, Rechteangaben und reproduzierbare Releases. HomeAdmin24 trägt `AGPL-3.0-or-later`; die Lizenz eines neuen eigenständigen Repos ist noch nicht entschieden.

## Decision

Eigenständiges `homeadmin24/weg-knowledge` mit `schemas/`, `rules/{accounting,allocation,heating,reserves,tax}/`, `sources/`, `tests/rules/`, `fixtures/`, `docs/`. Ausschließlich eigene allgemeine Regeln/Metadaten und synthetische Beispiele. Keine privaten Dokumente, personenbezogenen Fixtures oder automatisch exportierten Kundendaten.

Rule-Schema v1 (JSON Schema, Vorschlag Draft 2020-12) validiert YAML-Autorendateien: `id`, `title`, `description`, `category`, `jurisdiction`, `scope`, `valid_from`, `valid_to` (nullable), `severity`, `applies_to`, `conditions`, `expected_behavior`, `sources`, `notes`, `schema_version`, `rule_version`. Optional Beispiele, Ausnahmen und getypte Parameter. Zusätzlich `evaluation_mode` (`deterministic/review_required`) und Reviewmetadaten. Mathematische Projektregeln erhalten Projektquelle/Methodenbeschreibung statt erfundener Gesetzesreferenz; dafür im Source-Schema ausdrücklich `project_specification` neben den geforderten offiziellen Quellarten vorsehen.

Source-Schema umfasst `law`, `regulation`, `court_decision`, `official_guidance` und die separat gekennzeichnete Projektquelle. Felder: ID, Titel, Jurisdiktion, Identifier, Abschnitt, Dokumentdatum, zeitliche Gültigkeit, offizielle URL/Referenz, Wiederverwendungs-/Lizenzinformation, Abrufdatum und Quellenrevision. Unbekannte Daten explizit nullable mit Grund, keine geratenen Gültigkeitsdaten. Rule referenziert genaue Quellenrevision/Fundstelle. Juristischer Stand, Abrufdatum und Paketbuilddatum sind verschiedene Angaben.

Maschinenprüfbare Regeln benennen eine versionierte Operation aus einem begrenzten Operatorenkatalog plus typisierte Parameter. Kein `eval`, PHP/Python/YAML-Objektcode, keine frei geladenen externen `$ref`s. Regeln mit unvollständiger Anwendbarkeit oder rechtlich ungeklärter Interpretation bleiben `review_required` bzw. warning und dürfen keine rechtliche Verbindlichkeit behaupten.

Releaseartefakt: `weg-knowledge-0.1.0.json` mit `release_version`, `schema_version`, `built_at` (UTC), `source_commit`, sortierten Rules und Source-Metadaten. Zusätzlich SHA-256-Datei über die exakten Artefaktbytes. Build aus Tag; feste Eingaben und expliziter Buildzeitpunkt erlauben reproduzierbaren Payload. Keine Selbstreferenz durch eine im eigenen Byteinhalt berechnete Paketchecksumme. SemVer für Paket/Schema/Regeln mit dokumentierter Breaking-Change-Policy.

Späterer HomeAdmin24-Importer lädt ein ausdrücklich gewähltes Release, validiert Schema/IDs/Referenzen/Checksummen/Operatorversionen und speichert Packageversion, Schemaversion, imported_at, Checksumme und Paketbytes. AuditRun pinnt genau dieses Paket. Kein automatisches „latest“ und kein Nachladen von Rechtsquellen während eines Audits. Checksummen belegen Integrität, allein keine vertrauenswürdige Herkunft; erlaubter Publisher/Releasekanal und Berechtigung zum Import gehören zum Vertrag.

Lizenzvorschlag zur Entscheidung: eigene Tools/Schemas MIT; eigene redaktionelle Regeltexte/Metadaten CC BY 4.0 mit file-/directorybezogener Zuordnung. Keine Lizenzbehauptung für Drittmaterial. Bis zur geklärten Wiederverwendung nur Metadaten/Links statt Rechtsquellentexten. Keine Übernahme geschützter Kommentare. In K1.1 wird die tatsächliche Entscheidung dokumentiert und ein passender LICENSE-/NOTICE-Vertrag angelegt; dies ist noch keine juristische Freigabe des Vorschlags.

## Alternatives

- Wissen im App-Repository: einfach, aber Release-/Datengrenze und Wiederverwendung schlechter.
- Nur Web-Retrieval zur Laufzeit: Quellen ändern sich, Offline-Audit und Replay fehlen.
- Executable Rules als Plugins: mehr Flexibilität, unnötige Codeausführungs- und Kompatibilitätsrisiken.

## Consequences

Zwei Releasezyklen und ein klarer Importvertrag sind erforderlich. CI muss Schema, eindeutige IDs, referenzierte Quellen, Gültigkeitsintervalle und Rule-Testabdeckung prüfen. Rule-Tests brauchen Fallinputs und erwartete Evaluations, keine bloßen Metadatenassertions. Eine kleine Offline-Referenzauswertung der unterstützten Operationen dient den Knowledge-Tests; dieselben Konformitätsfälle müssen später gegen die PHP-Audit-Engine laufen, um Implementierungsdrift zu erkennen.

## Migration Strategy

Erst Repo-/Lizenz-/Schemafundament, dann etwa zehn sorgfältig begrenzte Regeln mit positiven, negativen, Grenz- und Nichtanwendungsfällen. Offizielle Quellen und Ausnahmen vor Freigabe prüfen; mathematische Regeln und rechtliche Hinweise getrennt kennzeichnen. Release 0.1.0 bleibt ohne App-Integration bis H6.1. Bestehende HGA-Schwellen nicht ungeprüft als Rechtsregeln exportieren.

Offen: Lizenzentscheidung, Reviewerzuständigkeit, konkretes Offline-Validierungswerkzeug nach kleinem Schema-Kompatibilitätstest. Kein zusätzlicher Laufzeitservice.
