# ADR 0007: Zunächst keine Vektordatenbank

Status: Entwurf, 2026-10-08. Gilt für erste Releases; erneute Bewertung in Phase 12.

## Context

Accounting benötigt exakte Beträge, Relationen und Perioden. Governance benötigt bestätigte Parameter und Fundstellen. Audit benötigt feste Rule-Versionen. Für diese Anforderungen ist semantische Ähnlichkeit weder ausreichendes Datenmodell noch verlässliche Entscheidungsgrundlage. Im untersuchten App-Code gibt es keine Vector Search oder RAG-Pipeline, die erhalten werden müsste.

## Decision

Relationale Modelle und versionierte Knowledge-Pakete bilden die Grundlage. Quellen zunächst über IDs, Kategorien, Zeitintervalle, Dokument-/Seitenreferenzen und einfache Textsuche auffinden. Keine Qdrant-/Elasticsearch-/Graph-/Embedding-Infrastruktur in Phase 1/2. Auch vorhandene Symfony-Komponenten begründen für sich keinen neuen Suchdienst.

Vor Phase 12 konkrete Retrievalfragen und Ground Truth definieren, z. B. Auffinden eines relevanten Beschlussabschnitts oder einer Quellenpassage für ein Finding. Ein Prototyp muss gegen die einfache Baseline gemessen werden: Trefferquote der richtigen Passage, Quellenpräzision, Laufzeit, Kosten, Berechtigungsisolation und Verhalten bei fehlender Quelle. Erst bei belegtem Nutzen wird eine einfache Technologie ausgewählt.

Embeddings sind gegebenenfalls ein abgeleiteter, wiederaufbaubarer Suchindex. Treffer verweisen auf unveränderliche autorisierte Quellenrevisionen. Private Governance und öffentliches Knowledge bleiben getrennt; Tenantfilter sind zwingend. Embeddings bestimmen weder Rechtsstand, Regelvorrang noch Sollbeträge.

## Alternatives

- Sofortiger externer Vector Store: zusätzliche Infrastruktur ohne getesteten Use Case.
- Alle Regeln als Text-RAG: verwechselt Retrieval mit ausführbarer, versionierter Regeldefinition.
- Vector Search grundsätzlich ausschließen: verhindert eventuell nützliche spätere Quellenrecherche.

## Consequences

Erste Releases können semantische Fragen nur begrenzt beantworten, bleiben aber offline prüfbar und betrieblich überschaubar. Datenverträge müssen Quellenrevisionen/Fundstellen tragen, damit Retrieval später additiv ergänzt werden kann.

## Migration Strategy

Keine Bestandsmigration nötig. In Phase 1 Quellenmetadaten, in Phase 2 Evidence vorbereiten. In Phase 11 synthetische/zulässige Benchmarks aufbauen; Phase 12 entscheidet anhand dieser Fälle über einen isolierten Prototyp. Abschalten/Löschen eines späteren Suchindexes darf Accounting-/Governance-/Auditdaten nicht verändern.
