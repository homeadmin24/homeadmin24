# HomeAdmin24 – langfristige Zielarchitektur

Konzeptstand 2026-10-09, keine Implementierungsfreigabe. Ergänzt [Bestandsaufnahme](accounting-audit-current-state.md), [ADRs](adr/README.md) und [Implementierungsplan](accounting-audit-phase-1-2-plan.md).

![HomeAdmin24: Accounting, Governance, Knowledge und gemeinsame Audit Engine](accounting-audit-target.svg)

[Zeichnung als skalierbare SVG öffnen](accounting-audit-target.svg).

Die beiden Workflows nutzen dieselben Prüfverträge. Beim Erstellen erzeugt der Statement Builder zunächst einen unveränderlichen Entwurf. Der Self Audit prüft genau diesen Entwurf; erst nach erfolgreicher Freigabeprüfung wird daraus die Jahresabrechnung als PDF. Bei fremden Abrechnungen prüft die Engine ein separates Kandidatenmodell, ohne dessen Werte automatisch in die eigene Buchhaltung zu übernehmen.

Governance entsteht aus WEG-Dokumenten und menschlicher Bestätigung. Allgemeines Rechtswissen kommt aus einem fest versionierten `weg-knowledge`-Release. Diese Modelle entstehen nicht aus dem Accounting Model. Bestätigte Governance steuert sowohl die Verteilung als auch den Sollvergleich im Audit. Anwendbarkeit und Konflikte zwischen Regeln bleiben ausdrücklich zu prüfen; ein ungeklärter Pflichtcheck ist kein PASS.

## Bearbeitbare Diagrammquelle

```mermaid
flowchart TB
    documents["Rechnungen, Bankdaten und weitere Belege"] --> intelligence
    wegSources["Teilungserklärung, Gemeinschaftsordnung, Beschlüsse"] --> intelligence
    foreign["Fremde Jahresabrechnung + Nachweise"] --> intelligence

    intelligence["Document Intelligence<br/>Klassifikation → Extraktion → Schemavalidierung<br/>Evidence und Confidence pro Feld"]
    intelligence --> reconciliation["Abgleich und Review"]
    reconciliation --> accounting["Accounting Model<br/>WEG, Periode, bestätigte Fakten"]
    intelligence --> proposed["Governance-Vorschläge"]
    proposed --> human["Menschliche Prüfung und Bestätigung"]
    human --> governance["Governance Model<br/>Bestätigte, gültige Regelversionen"]
    intelligence --> imported["Imported Statement<br/>Separate Kandidaten + Feld-Evidence"]

    knowledge["Legal Knowledge<br/>weg-knowledge<br/>Versionierte allgemeine Regeln + Quellen"]
    accounting --> builder["Allocation Engine + Statement Builder<br/>Unveränderlicher Entwurf"]
    governance -->|"Verteilungsvorgaben"| builder
    builder -->|"A: Entwurf"| audit
    accounting -->|"Accounting-Snapshot"| audit
    imported -->|"B: importierte Werte"| audit
    governance --> audit
    knowledge --> audit

    audit["Deterministische Audit Engine<br/>A: Self Audit · B: Fremdprüfung<br/>Versionierter Run + Evidence + Rechenweg"]
    audit --> report["Audit Report<br/>Findings, Soll, Ist, Differenz, Quellen"]
    audit -->|"A: eigene Abrechnung"| gate{"Pflichtchecks vollständig<br/>und bestanden?"}
    gate -->|"Ja"| pdf["Jahresabrechnung als PDF<br/>Genau der geprüfte Entwurf"]
    gate -->|"Nein / nicht prüfbar"| review["Review + nachvollziehbare Korrektur<br/>Neue Input- oder Regelversion"]
    review -.-> reconciliation
    review -.-> human
    report -.-> explanation["Optional: KI-Erklärung<br/>Prüfergebnis unveränderlich"]

    classDef ai fill:#eaf3ff,stroke:#83b2e6,color:#142a43;
    classDef model fill:#fff,stroke:#a9bcd0,color:#142a43;
    classDef rules fill:#effaf6,stroke:#8fcbb7,color:#142a43;
    classDef legal fill:#f1edfc,stroke:#b6a2dd,color:#142a43;
    classDef engine fill:#173955,stroke:#173955,color:#fff;
    classDef success fill:#168268,stroke:#168268,color:#fff;
    classDef attention fill:#fff5e7,stroke:#e5c18b,color:#142a43;
    class intelligence,explanation ai;
    class accounting,imported,builder,report model;
    class governance rules;
    class knowledge legal;
    class audit engine;
    class pdf success;
    class human,review,gate attention;
```

## Leitlinien der Zeichnung

- KI liest, klassifiziert und interpretiert; strukturierte Outputs werden validiert. Unsichere Werte bleiben Vorschläge.
- Software berechnet Beträge, Verteilungen und Prüfergebnisse deterministisch. KI-Erklärungen verändern keine Findings.
- Evidence, Input-/Regelversionen und WEG-Berechtigungen gelten über alle Schritte hinweg. Nicht jede Verbindung ist zur besseren Lesbarkeit als eigene Linie gezeichnet.
- Die neue Erstellung muss zusätzlich den im Plan festgelegten lokalen Vergleich gegen die privaten 2025-HGA-Baselines bestehen. Diese Zeichnung enthält keine privaten Fixture-Daten.
- Die Darstellung beschreibt den langfristigen Ausbau des bestehenden Symfony-Monolithen, keinen vollständigen Rewrite. Vector Search und Modelltraining sind keine Voraussetzung.
