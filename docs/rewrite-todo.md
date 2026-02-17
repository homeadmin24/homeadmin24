# HGA Rewrite - TODO

Basierend auf den rechtlichen Anforderungen und Best Practices für WEG-Abrechnungen.

## Grundprinzip: Zufluss-/Abfluss-Prinzip

Die WEG-Jahresabrechnung **muss** nach dem Zufluss-/Abfluss-Prinzip erstellt werden (BGH V ZR 271/12):
- **Einnahme** = Geldeingang auf Konto
- **Ausgabe** = Geldabgang vom Konto
- **Keine periodengerechte Abgrenzung erlaubt** (auch nicht per Beschluss)

---

## TODO: Template-Verbesserungen

### 1. Hinweis zum Abrechnungsprinzip hinzufügen

**Wo:** Nach dem Header, vor der Summary-Box

**Inhalt:**
```html
<div class="info-box">
    <p><strong>Abrechnungsprinzip:</strong> Diese Abrechnung folgt dem gesetzlich
    vorgeschriebenen Zufluss-/Abfluss-Prinzip (BGH V ZR 271/12). Maßgeblich ist der
    Zahlungszeitpunkt, nicht der Leistungszeitraum.</p>
</div>
```

**Begründung:** Eigentümer verstehen oft nicht, warum z.B. Versicherung 2025 in Abrechnung 2024 erscheint.

---

### 2. Kostenverschiebungs-Warnung bei Auffälligkeiten

**Problem:** Bei starken Schwankungen (z.B. Strom 1.000€ vs. 0€) sind Eigentümer verwirrt.

**Lösung:** Automatische Erkennung und Hinweis

**Wo:** Bei jeder Kostenart mit > 50% Abweichung zum Vorjahr

**Implementierung in `HgaService`:**
```php
// Prüfen ob Kostenart > 50% vom Vorjahr abweicht
if ($currentYearCost > $previousYearCost * 1.5 || $currentYearCost < $previousYearCost * 0.5) {
    $warnings[] = [
        'kostenart' => $item['bezeichnung'],
        'current' => $currentYearCost,
        'previous' => $previousYearCost,
        'type' => $currentYearCost > $previousYearCost ? 'increase' : 'decrease'
    ];
}
```

**Template-Anzeige:**
```html
{% if item.hasVariance %}
<tr class="warning-row">
    <td colspan="4">
        <small>⚠️ Hinweis: Abweichung zum Vorjahr ({{ item.variancePercent }}%).
        Ursache: {{ item.varianceReason|default('Zahlungszeitpunkt-Verschiebung') }}</small>
    </td>
</tr>
{% endif %}
```

---

### 3. Neue Sektion: "Erläuterungen zu Kostenverschiebungen"

**Wo:** Nach der Kostenübersicht, vor Zahlungsübersicht

**Template:**
```html
{% if reportData.costShiftWarnings is not empty %}
<div class="section">
    <h2>Erläuterungen zu Kostenschwankungen</h2>
    <p>Folgende Kostenpositionen weichen erheblich vom Vorjahr ab. Dies liegt am
    gesetzlich vorgeschriebenen Zufluss-/Abfluss-Prinzip:</p>
    <table>
        <thead>
            <tr>
                <th>Kostenart</th>
                <th>{{ year - 1 }}</th>
                <th>{{ year }}</th>
                <th>Erläuterung</th>
            </tr>
        </thead>
        <tbody>
        {% for warning in reportData.costShiftWarnings %}
            <tr>
                <td>{{ warning.kostenart }}</td>
                <td class="number">{{ warning.previous|number_format(2, ',', '.') }} €</td>
                <td class="number">{{ warning.current|number_format(2, ',', '.') }} €</td>
                <td>{{ warning.explanation }}</td>
            </tr>
        {% endfor %}
        </tbody>
    </table>
</div>
{% endif %}
```

---

### 4. Vermietete Wohnungen: Hinweis für Vermieter

**Wo:** Am Ende der Abrechnung

**Template:**
```html
<div class="info-box">
    <h3>Hinweis für Vermieter</h3>
    <p>Diese WEG-Abrechnung folgt dem Zufluss-/Abfluss-Prinzip. Für Ihre
    Betriebskostenabrechnung an Mieter dürfen Sie periodengerecht abrechnen
    (§ 556 BGB). Die Kosten können dem Verbrauchszeitraum zugeordnet werden.</p>

    <table>
        <tr>
            <td><strong>WEG-Abrechnung:</strong></td>
            <td>Zufluss-/Abfluss (Zahlungszeitpunkt)</td>
            <td>❌ Keine Änderung möglich</td>
        </tr>
        <tr>
            <td><strong>Mieter-Abrechnung:</strong></td>
            <td>Periodengerecht möglich</td>
            <td>✅ Vermieter darf anpassen</td>
        </tr>
    </table>
</div>
```

---

### 5. Vorjahresvergleich in Kostenübersicht

**Aktuell:** Nur aktuelles Jahr
**Neu:** Spalte für Vorjahr hinzufügen

**Template-Änderung:**
```html
<table>
    <tr>
        <td width="40%"><strong>Kostenart</strong></td>
        <td width="15%" class="number"><strong>Vorjahr</strong></td>
        <td width="15%" class="number"><strong>Gesamt</strong></td>
        <td width="15%" class="number"><strong>Differenz</strong></td>
        <td width="15%" class="number"><strong>Ihr Anteil</strong></td>
    </tr>
    <!-- ... -->
</table>
```

**Datenquelle:** `reportData` muss Vorjahresdaten enthalten

---

### 6. Eigentümerwechsel-Hinweis

**Wenn:** Eigentümer hat < 365 Tage im Abrechnungsjahr

**Template:**
```html
{% if reportData.ownershipDays < 365 %}
<div class="warning-box">
    <h3>Hinweis: Eigentümerwechsel</h3>
    <p>Sie waren {{ reportData.ownershipDays }} Tage Eigentümer im Abrechnungsjahr
    ({{ reportData.ownershipStart }} - {{ reportData.ownershipEnd }}).</p>
    <p>Die Kosten sind anteilig berechnet. Beachten Sie, dass durch das
    Zufluss-/Abfluss-Prinzip Kostenverschiebungen zwischen Jahren auftreten können,
    die nicht dem tatsächlichen Verbrauch entsprechen.</p>
    <p><strong>Tipp:</strong> Klären Sie im Kaufvertrag einen eventuellen Ausgleich
    für bekannte Kostenverschiebungen.</p>
</div>
{% endif %}
```

---

## TODO: Backend-Änderungen

### 1. Vorjahresdaten laden

**Datei:** `src/Service/Hga/HgaService.php`

```php
// Vorjahresdaten für Vergleich laden
$previousYearData = $this->loadPreviousYearData($weg, $year - 1, $einheit);
$reportData['previousYear'] = $previousYearData;
$reportData['costShiftWarnings'] = $this->detectCostShifts($reportData, $previousYearData);
```

### 2. Kostenverschiebungs-Erkennung

**Neue Methode:**
```php
private function detectCostShifts(array $currentData, array $previousData): array
{
    $warnings = [];
    $threshold = 0.5; // 50% Abweichung

    foreach ($currentData['umlagefaehigeKosten'] as $item) {
        $prevCost = $this->findPreviousCost($previousData, $item['bezeichnung']);
        if ($prevCost === null) continue;

        $variance = abs($item['gesamtkosten'] - $prevCost) / max($prevCost, 1);

        if ($variance > $threshold) {
            $warnings[] = [
                'kostenart' => $item['bezeichnung'],
                'current' => $item['gesamtkosten'],
                'previous' => $prevCost,
                'variancePercent' => round($variance * 100),
                'explanation' => $this->generateExplanation($item, $prevCost)
            ];
        }
    }

    return $warnings;
}
```

### 3. Erklärungstexte generieren

**Logik:**
```php
private function generateExplanation(array $item, float $prevCost): string
{
    if ($item['gesamtkosten'] > $prevCost * 1.5) {
        return 'Im Abrechnungsjahr fielen zusätzliche Zahlungen an (z.B. Nachzahlung Vorjahr).';
    }

    if ($item['gesamtkosten'] < $prevCost * 0.5) {
        return 'Im Abrechnungsjahr gab es weniger Zahlungen (z.B. Guthaben/Erstattung).';
    }

    return 'Zahlungszeitpunkt-Verschiebung zwischen den Jahren.';
}
```

---

## TODO: Datenbank-Erweiterungen

### 1. Vorjahres-Cache (optional)

Für schnelleren Zugriff auf Vorjahresdaten:

```sql
ALTER TABLE hausgeldabrechnung
ADD COLUMN previous_year_data JSON DEFAULT NULL;
```

### 2. Kostenart-Hinweise (optional)

Manuelle Erklärungen für spezifische Kostenarten:

```sql
CREATE TABLE hga_cost_explanation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    weg_id INT NOT NULL,
    year INT NOT NULL,
    kostenart VARCHAR(255) NOT NULL,
    explanation TEXT,
    FOREIGN KEY (weg_id) REFERENCES weg(id)
);
```

---

## Priorisierung

| Prio | Aufgabe | Aufwand | Nutzen |
|------|---------|---------|--------|
| 1 | Hinweis zum Abrechnungsprinzip | Klein | Hoch |
| 2 | Vorjahresvergleich in Tabelle | Mittel | Hoch |
| 3 | Kostenverschiebungs-Warnung | Mittel | Hoch |
| 4 | Erläuterungen-Sektion | Mittel | Mittel |
| 5 | Vermieter-Hinweis | Klein | Mittel |
| 6 | Eigentümerwechsel-Hinweis | Klein | Mittel |

---

## Rechtliche Grundlagen

| Thema | Rechtsgrundlage |
|-------|-----------------|
| Zufluss-/Abfluss-Prinzip | BGH V ZR 271/12 |
| Keine periodengerechte Abgrenzung | BGH, ständige Rechtsprechung |
| Mieter-Abrechnung periodengerecht | § 556 BGB, BetrKV |
| Prüfbarkeit anhand Kontoauszüge | § 28 WEG |

---

## Beispiel: Vollständige neue Summary-Box

```html
<div class="summary-box">
    <p class="info-text">
        <em>Diese Abrechnung folgt dem Zufluss-/Abfluss-Prinzip (BGH V ZR 271/12).
        Maßgeblich ist der Zahlungszeitpunkt.</em>
    </p>

    <table>
        <tr>
            <td width="40%"><strong>Position</strong></td>
            <td width="15%" class="number"><strong>Vorjahr</strong></td>
            <td width="15%" class="number"><strong>Objekt gesamt</strong></td>
            <td width="15%" class="number"><strong>Differenz</strong></td>
            <td width="15%" class="number"><strong>Ihr Anteil</strong></td>
        </tr>
        <tr>
            <td>Gesamtkosten</td>
            <td class="number">{{ reportData.previousYear.grossTotalCosts|number_format(2, ',', '.') }} €</td>
            <td class="number">{{ reportData.grossTotalCosts|number_format(2, ',', '.') }} €</td>
            <td class="number {% if reportData.costDifference > 0 %}text-red{% else %}text-green{% endif %}">
                {{ reportData.costDifference|number_format(2, ',', '.') }} €
            </td>
            <td class="number"><strong>{{ reportData.ownerShare|number_format(2, ',', '.') }} €</strong></td>
        </tr>
        <!-- ... weitere Zeilen ... -->
    </table>
</div>
```

http://127.0.0.1:8000/abrechnung/2025/preview/0003/eigentümer 
http://127.0.0.1:8000/abrechnung/2025/preview/0003/mieter (== aktuelle hqa) 
