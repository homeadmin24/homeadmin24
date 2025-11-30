# homeadmin24 - WEG-Verwaltungssystem

[![Deploy to DigitalOcean](https://img.shields.io/badge/Deploy%20to-DigitalOcean-0080FF?logo=digitalocean&logoColor=white)](https://cloud.digitalocean.com/apps/new?repo=https://github.com/homeadmin24/homeadmin24/tree/main)
[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://www.gnu.org/licenses/agpl-3.0)

## Überblick

homeadmin24 ist ein umfassendes Immobilienverwaltungssystem für deutsche Wohnungseigentümergemeinschaften (WEG). Es bietet Finanzverfolgung, Zahlungsverwaltung, Rechnungsverarbeitung und automatisierte Erstellung von Hausgeldabrechnungen.

**Open Source & Copyleft**: homeadmin24 ist unter der [GNU Affero General Public License v3.0](LICENSE) lizenziert. Das bedeutet:
- ✅ Freie Nutzung, Änderung und Verteilung
- ✅ Kommerzielle Nutzung erlaubt (Hosting, Beratung, Support)
- ✅ Transparenz und Community-Beiträge willkommen
- ⚠️ Änderungen müssen unter derselben Lizenz veröffentlicht werden
- ⚠️ Netzwerk-Nutzer haben Anspruch auf den Quellcode (AGPL §13)

## Tech Stack

- **Backend**: Symfony 7.2 (PHP 8.2+)
- **Datenbank**: MySQL 8.0 
- **Frontend**: Tailwind CSS, Flowbite (UI Components), Stimulus.js (via Symfony UX), Webpack Encore
- **PDF-Generierung**: DomPDF

## Kernfunktionen

### 1. Immobilienverwaltung
- **WEG (Wohnungseigentümergemeinschaft)**: Zentrale Verwaltung mehrerer Eigentumseinheiten
- **WEG-Einheiten**: Einzelne Eigentumseinheiten mit Eigentümerdetails, Stimmrechten und Miteigentumsanteilen

### 2. Finanzverwaltung
- **Zahlungsverfolgung**: Erfassung aller Finanztransaktionen (Einnahmen/Ausgaben)
- **Zahlungskategorien**: Klassifizierung von Zahlungen als Einnahmen oder Ausgaben
- **Kostenkonten**: Kontenplan mit Kennzeichnung umlagefähiger Kosten
- **Kontostandsverwaltung**: Automatisierte Saldenberechnung und Kontostandsentwicklung

### 3. Dienstleisterverwaltung
- **Dienstleister**: Verwaltung von Handwerkern und Dienstleistern mit Vertragsdetails
- **Rechnungsverwaltung**: Verknüpfung von Rechnungen mit Dienstleistern, Fälligkeitsdaten und Steuerinformationen
- **Arbeits-/Fahrtkosten**: Erfassung für §35a EStG Steuerabzug

### 4. Dokumentenverwaltung
- Speicherung und Organisation wichtiger Dokumente (Eigentümerdokumente, Beschlüsse, Jahresabschlüsse)
- Kategorisierung nach Typ: eigentuemer, umlaufbeschluss, jahresabschluss

### 5. Hausgeldabrechnungen
- Automatisierte Generierung von Hausgeldabrechnungs-PDFs und TXT-Dateien
- Berechnung der Eigentümeranteile basierend auf Miteigentumsanteilen (MEA)
- Trennung von umlagefähigen und nicht umlagefähigen Kosten
- Steuerlich absetzbare Leistungen nach §35a EStG
- Wirtschaftsplan für das Folgejahr
- Kontostandsentwicklung und Vermögensübersicht

### 6. CSV-Import & Auto-Kategorisierung
- Automatischer Import von Kontoauszügen (Sparkasse SEPA-Format)
- Intelligente Auto-Kategorisierung mit Pattern-Matching
- Fuzzy-Matching für Eigentümer-Zuordnung
- Duplikatserkennung (3-stufiges Fallback-System)
- Automatische Erstellung neuer Dienstleister

### 7. Wichtige Services

#### HausgeldabrechnungGenerator
Generiert Hausgeldabrechnungen als PDF und TXT mit:
- Gesamtkostenberechnung mit Rücklagenzuführung
- Eigentümeranteilsberechnung basierend auf MEA
- Trennung von umlagefähigen und nicht umlagefähigen Kosten
- Steuerlich absetzbare Leistungen nach §35a EStG
- Zahlungsübersicht und Kontostandsentwicklung
- Vermögensübersicht und Wirtschaftsplan

#### CalculationService
Zentrale Berechnungslogik für:
- Kostenverteilung nach Umlageschlüsseln
- Dynamische Einheitenverteilung
- Hebeanlage-Spezialverteilung
- Externe Heiz- und Wasserkosten

## Dokumentation

📚 **Developer Documentation**: [DEVELOPMENT.md](DEVELOPMENT.md)

Die Dokumentation ist in drei Hauptkategorien organisiert:
- **BusinessLogic/** - WEG-Geschäftslogik, Finanzberechnungen, Steuerrecht (Rücklagenzuführung, §35a EStG)
- **CoreSystem/** - Anwendungsfunktionen (CSV-Import, Zahlungskategorien, Authentifizierung)
- **TechnicalArchitecture/** - Implementierung, Datenbankschema, Architektur-Entscheidungen

## Installation & Bereitstellung

### Schnellstart: Docker (Empfohlen für lokale Entwicklung)

**Voraussetzungen:** Docker & Docker Compose installiert

1. **Repository klonen:**
   ```bash
   git clone https://github.com/homeadmin24/homeadmin24.git
   cd homeadmin24
   ```

2. **Container starten:**
   ```bash
   docker compose -f docker-compose.yaml -f docker-compose.dev.yml up -d
   ```

3. **Auf Datenbank warten (ca. 10 Sekunden):**
   ```bash
   # Warten bis MySQL bereit ist
   sleep 10
   ```

4. **Datenbank-Setup:**
   ```bash
   # Migrationen ausführen
   docker compose exec web php bin/console doctrine:migrations:migrate --no-interaction

   # Demo-Daten laden (identisch mit demo.homeadmin24.de)
   docker compose exec web php bin/console doctrine:fixtures:load --group=demo-data --no-interaction
   ```

5. **✅ Fertig! Anwendung öffnen:**
   - 🌐 **Web:** http://127.0.0.1:8000
   - 🔐 **Login (Demo-Admin):**
     - E-Mail: `wegadmin@demo.local`
     - Passwort: `demo123`
   - 🗄️ **MySQL:** `127.0.0.1:3307` (root/rootpassword)

**Häufige Docker-Befehle:**
```bash
# Container stoppen
docker compose down

# Logs anzeigen
docker compose logs -f web

# In Web-Container Shell
docker compose exec web bash

# Neuen Admin-Benutzer erstellen
docker compose exec web php bin/console app:create-admin

# Cache leeren
docker compose exec web php bin/console cache:clear

# Code-Qualität prüfen
docker compose exec web composer quality-services
```

**Hinweis:** Die Installation lädt automatisch Demo-Daten mit 3 WEG-Beispielen, Zahlungen und Rechnungen - identisch mit https://demo.homeadmin24.de

---

## Production Deployment

Für Production-Deployments (DigitalOcean, VPS, Cloud):

📖 **[INSTALLATION.md](INSTALLATION.md)** - Production Deployment Guide
- **DigitalOcean App Platform** - One-Click Deployment ($12/Monat, managed)
- **DigitalOcean Droplet (VPS)** - Volle Kontrolle ($6/Monat, self-hosted)
- Automatische Deployments via GitHub Actions
- SSL-Zertifikate, Backups & Monitoring
