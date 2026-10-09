# ADR 0006: AI Provider Boundary

Status: Entwurf, 2026-10-08. Vorbereitung Phase 3/5/8; keine neue KI-Funktion in Phase 0/2.

## Context

Es gibt `AIProviderInterface` für Finanzfragen und `DocIntelInterface` für Rechnungsbilder. Rechnungsextraktion, Kategorisierung und Qualitätsprüfung hängen darüber hinaus direkt von Ollama/Claude ab. JSON-Decoding ersetzt keine fachliche Schemavalidierung. Confidence wird mit Domainstatus vermischt, Prompts können sensible Daten enthalten und vollständig geloggt werden. DocIntel/Ollama sind bereits optionale Bestandskomponenten.

## Decision

Anwendungsfälle bekommen eigene fachliche Verträge: `DocumentClassifierInterface`, `DocumentExtractorInterface`, später `GovernanceRuleExtractorInterface` und `FindingExplanationInterface`. Provideradapter kapseln Transport/Authentifizierung/Modelle. Bestehende Regex-/Rechnungsparser können über Adapter dieselben DTO-Verträge liefern. DTOs statt persistierter Entities an der Extraktionsgrenze.

Jede Komponente dokumentiert und implementiert:

```text
minimaler autorisierter Input
  → Provideroperation mit deklarierter Fähigkeit/Version
  → strukturierter Output mit Schema- und Providermetadaten
  → Schema-, Werte-, Evidence- und WEG-Validierung
  → proposed Kandidat / getrennte Erklärung
  → explizite Domainentscheidung und ggf. menschliche Bestätigung
```

Classifier: Typ, Confidence, Evidence, Provider-/Classifier-Version. Extractor: CanonicalDocumentData mit typisierten Feldern, Herkunft pro Feld und Fehlern/fehlenden Werten. Governance: nur proposed. Erklärung: feste Finding-ID/-Version plus erklärender Inhalt/Quellen, keine schreibbare Bewertung. Unbekannte Felder/Enums, nicht endliche Beträge, ungültige Datums-/Währungswerte und fremde WEG-Referenzen werden zurückgewiesen oder als Reviewkandidat markiert.

Dokumenttext ist untrusted input; eingebettete Anweisungen dürfen keine Tools, Netzaufrufe, Fremddokumente oder Bestätigungen auslösen. Extraktionsprompts und strukturierte Validierung müssen dies als eigene Testfälle behandeln. Prüfung von Quellenbezug und Plausibilität bleibt Anwendungscode; Confidence ist eine Schätzung, kein Beweis.

Ein gemeinsamer AI-Ausschalter blockiert jede AI-Netzaktivität, zusätzlich Provider-/Fähigkeitsflags. Verfügbarkeitsprüfung soll bei deaktivierter AI keine HTTP-Abfrage auslösen. Modellnamen, Endpunkte, Timeouts und Budgetgrenzen werden konfigurierbar; kein versteckter Fallback auf einen anderen externen Anbieter. Anfragen sind explizite autorisierte Operationen. Payloads minimieren und nicht in reguläre Logs schreiben; Debuginformationen privat, berechtigt und begrenzt.

Spätere Messenger-Jobs enthalten WEG/Objekt-/Inputversion, sind idempotent und starten keine Doppelimporte bei Retry. Jobbeginn und Ergebnisannahme prüfen Version/Berechtigung. Normale CI verwendet Fakes/Golden-Outputs, keine Live-Provider.

## Alternatives

- Ein untypisierter `prompt(string): string` als universelle Domain-API: zu schwach für Validierung/Autorisierung.
- Nur einen Anbieter direkt verwenden: verstärkt Kopplung und verhindert identische Benchmarks.
- Sofortige lokale Modell-/Python-Neuimplementierung: ohne Ground Truth kein begründeter Nutzen.

## Consequences

Neue Adapter müssen bestehende Konfiguration respektieren. JSON Schema/Feldprüfung und Evidence werden Pflicht vor Übernahme; fehlerhafte Antworten werden nicht durch Null-/0-Defaults zu Fakten. Bestandsprovider bleiben nutzbar, ihre konkreten Modell-/Preisannahmen werden nicht als dauerhaft gültig vorausgesetzt.

## Migration Strategy

Zuerst synthetische Golden-Fälle für heutige Parser. Dann vorhandenen `ParserInterface::parse()`-Output in einen Canonical-Kandidaten überführen, ohne den alten Invoice-Pfad abzuschalten. Extractor-Adapter ruft nicht `InvoiceProcessingService` mit Persistenzeffekt auf. Providergrenze schrittweise pro Anwendungsfall einsetzen; Confidence-/Zahlungsstatuskopplung in separat getestetem Ticket beseitigen. Alte AI-Qualitätsanzeige bleibt bis zum Ersatz unterscheidbar von der deterministischen Audit Engine.
