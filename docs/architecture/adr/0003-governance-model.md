# ADR 0003: Versionierte und bestätigte WEG-Governance

Status: Entwurf, 2026-10-08. Architekturvorbereitung für Phase 5/6, keine Implementierung in Phase 2.

## Context

Umlageschlüssel, Einheitsanteile, Kostenkonten und Plan-/Override-JSON steuern heute Ergebnisse, besitzen jedoch keinen Beschluss-/Dokumentnachweis, Bestätigungsworkflow oder historische Gültigkeit. Globale Schlüssel sind nicht automatisch individuelle WEG-Regeln. Die fachliche Wirksamkeit extrahierter Klauseln kann nicht aus Confidence abgeleitet werden.

## Decision

`GovernanceRule` hält WEG und stabile fachliche Identität. `GovernanceRuleRevision` hält Version, Typ, Kategorie, strikt validierte Parameter, valid_from/valid_to, Priorität, Quelle/Fundstelle/Originalauszug als private Evidence, Confidence, Reviewstatus und Zeitstempel. `GovernanceReview` hält handelnde Person, Entscheidung, Zeitpunkt und Begründung. Status: `proposed`, `confirmed`, `rejected`, `superseded`.

LLM-Extraktion erstellt ausschließlich `proposed`. Änderung eines Vorschlags/Bestands erzeugt eine neue Revision. Nur berechtigte Personen können bestätigen; alte Revisionen bleiben für historische Runs verfügbar. Teilnehmer sind stabile Einheits-IDs derselben WEG, keine frei geratenen Nummern. Gültigkeitszeit und Bestätigungs-/Erfassungszeit werden separat gespeichert. Rückwirkende Korrektur überschreibt keinen alten AuditRun.

Nur bestätigte, im Prüfkontext gültige Revisionen können verbindliche Sollwerte steuern. Unbestätigte Kandidaten führen ggf. zu `review_required` oder unvollständiger Prüfbarkeit. Sie ersetzen keine bestätigte Regel.

Die Registry kombiniert interne Mathematik, LegalKnowledge und bestätigte Governance. Prioritäten dienen nur einer explizit definierten Reihenfolge innerhalb zulässiger Regelgruppen. Es gibt keinen universellen „höchste Zahl gewinnt“-Mechanismus und keinen pauschalen Vorrang individueller Governance vor zwingenden rechtlichen Grenzen. Widersprüche/überlappende Regeln werden als Konflikt mit beiden Quellen ausgegeben; konfliktabhängige Checks bleiben `not_evaluated`. Ein neueres Datum allein beweist keine wirksame Ablösung.

## Alternatives

- Bestehende Umlageschlüssel um Freitext ergänzen: kein belastbarer Review-/Versionsvertrag.
- Prompt als Policy-Speicher: weder reproduzierbar noch deterministisch.
- Sofortige automatische Bestätigung bei hoher Confidence: widerspricht Human-in-the-loop.

## Consequences

Review-UI muss erkannte Regel, Parameter, Quelle, Seite/Auszug, Confidence und Änderungen sichtbar machen. Freigabe braucht eine WEG-Berechtigung. Regelkonflikte können automatische Statement-Freigabe blockieren. Bestehende Konfiguration ist ein Migrationskandidat, kein Beweis rechtlicher Wirksamkeit.

## Migration Strategy

In Phase 2 nur Evidence-/WEG-Verträge vorbereiten. Später Legacy-Schlüssel/Anteile zunächst als `proposed` mit Legacy-Herkunft importieren; Bestätigung erfordert eine nachvollziehbare Grundlage. Danach bestätigte Governance im Vergleichsmodus gegen Legacy verwenden. Keine rückwirkende Neuinterpretation alter Abrechnungen ohne neuen Run und explizite Versionen.

Offen: Rollen für fachliche Bestätigung, Vier-Augen-Anforderung, Gültigkeits-/Konfliktmatrix je Regeltyp. Zwingendes Recht bzw. zulässige Abweichungen müssen in Phase 1/5 anhand offizieller Quellen geprüft werden.
