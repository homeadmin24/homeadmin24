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

📚 **Vollständige Projektdokumentation**: [CLAUDE.md](CLAUDE.md)

Die Dokumentation ist in drei Hauptkategorien organisiert:
- **BusinessLogic/** - WEG-Geschäftslogik, Finanzberechnungen, Steuerrecht (Rücklagenzuführung, §35a EStG)
- **CoreSystem/** - Anwendungsfunktionen (CSV-Import, Zahlungskategorien, Authentifizierung)
- **TechnicalArchitecture/** - Implementierung, Datenbankschema, Architektur-Entscheidungen

## Installation & Bereitstellung

### Option 1: Mit Docker (Empfohlen für lokale Entwicklung)

1. Repository klonen:
   ```bash
   git clone https://github.com/homeadmin24/homeadmin24.git
   cd homeadmin24
   ```

2. Docker-Container starten:
   ```bash
   docker-compose up -d
   ```

3. Datenbank-Migrationen ausführen:
   ```bash
   docker exec homeadmin24-web-1 php bin/console doctrine:migrations:migrate --no-interaction
   ```

4. Frontend-Assets erstellen:
   ```bash
   docker exec homeadmin24-web-1 npm run build
   ```

5. Systemkonfiguration laden:
   ```bash
   docker exec homeadmin24-web-1 php bin/console doctrine:fixtures:load --group=system-config --no-interaction
   ```

6. Admin-Benutzer erstellen:
   ```bash
   docker exec homeadmin24-web-1 php bin/console app:create-admin
   ```

7. Anwendung öffnen:
   - Web: http://localhost:8000
   - MySQL: localhost:3307 (root/rootpassword)

**Docker-Befehle:**
```bash
# Container starten
docker-compose up -d

# Container stoppen
docker-compose down

# Logs anzeigen
docker logs homeadmin24-web-1 -f

# In Web-Container Shell
docker exec -it homeadmin24-web-1 bash

# Datenbank-Backup
./bin/backup_db.sh "beschreibung"
```

---

### Option 2: DigitalOcean App Platform (One-Click Cloud Deployment)

Klicken Sie auf den Button oben, um homeadmin24 mit einem Klick auf DigitalOcean bereitzustellen:

1. Klicken Sie auf den "Deploy to DO" Button oben im README
2. Verbinden Sie Ihr GitHub-Repository
3. DigitalOcean erstellt automatisch:
   - PHP-Web-Service mit Nginx
   - MySQL 8.0 Datenbank
   - Automatische SSL-Zertifikate
   - HTTPS-Zugriff mit eigener Domain
4. Nach der Bereitstellung via DigitalOcean Console:
   ```bash
   # Admin-Benutzer erstellen
   php bin/console app:create-admin

   # Systemkonfiguration laden
   php bin/console doctrine:fixtures:load --group=system-config --no-interaction
   ```

**Vorteile:**
- ✅ Keine Serverkonfiguration notwendig
- ✅ Automatische Backups
- ✅ SSL-Zertifikate inklusive
- ✅ Skalierbar (bei Bedarf mehr Ressourcen)
- ✅ Automatische Updates bei Git-Push

**Kosten:** Ab ~$12/Monat (App: $5 + MySQL Production DB: $7)

**Konfiguration:** Die Bereitstellung verwendet das Repository `homeadmin24/homeadmin24` auf GitHub.

---

### Option 3: DigitalOcean Droplet (VPS - $6/Monat)

Günstigste Option für Production-Deployment mit voller Kontrolle.

📖 **Detaillierte Deployment-Dokumentation:** [.droplet/DEPLOYMENT.md](.droplet/DEPLOYMENT.md)
- Erklärung aller `.env` Dateien
- GitHub Actions Workflow
- Automatisierte Demo- und Production-Deployments

**Schritt 1: Droplet erstellen**
1. DigitalOcean Droplet erstellen (Ubuntu 22.04/24.04, $6/Monat Basic Droplet)
2. Domain A-Record auf Droplet IP setzen
3. SSH-Zugriff einrichten

**Schritt 2: Server einrichten**
```bash
# Setup-Script herunterladen und ausführen
wget https://raw.githubusercontent.com/homeadmin24/homeadmin24/main/.droplet/setup.sh
chmod +x setup.sh
sudo ./setup.sh yourdomain.com your@email.com
```

**Schritt 3: Repository klonen und konfigurieren**
```bash
cd /opt/homeadmin24
git clone https://github.com/homeadmin24/homeadmin24.git .
cp .env.example .env
nano .env  # Datenbank-Zugangsdaten anpassen
```

**Schritt 4: Deployment ausführen**
```bash
sudo bash .droplet/deploy.sh yourdomain.com your@email.com
```

**Schritt 5: Admin-Benutzer erstellen**
```bash
docker-compose exec web php bin/console app:create-admin
```

**Vorteile:**
- ✅ Günstigste Option ($6/Monat für unbegrenzte Apps)
- ✅ Volle Root-Kontrolle über Server
- ✅ Docker-basiert (identisch mit lokaler Entwicklung)
- ✅ Automatische SSL-Zertifikate (Let's Encrypt)
- ✅ Automatische Deployments via GitHub Actions

**Automated Deployment:**
Nach Setup GitHub Repository Secrets konfigurieren:
- `DROPLET_HOST`: Droplet IP-Adresse
- `DROPLET_USER`: SSH-Benutzer (meist `root`)
- `DROPLET_SSH_KEY`: Private SSH-Key für Zugriff

Dann deployed jeder Push auf `main` automatisch via GitHub Actions.

**Updates:**
```bash
cd /opt/homeadmin24
sudo bash .droplet/deploy.sh yourdomain.com your@email.com
```

---

### Option 4: Ohne Docker (Manuelle Installation)

1. Repository klonen:
   ```bash
   git clone https://github.com/homeadmin24/homeadmin24.git
   cd homeadmin24
   ```

2. Abhängigkeiten installieren:
   ```bash
   composer install
   npm install
   ```

3. Datenbank in `.env` konfigurieren:
   ```
   DATABASE_URL="mysql://app:changeme@127.0.0.1:3306/homeadmin24?serverVersion=8.0.32&charset=utf8mb4"
   ```

4. Datenbank erstellen und Migrationen ausführen:
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   ```

5. Systemkonfiguration laden:
   ```bash
   php bin/console doctrine:fixtures:load --group=system-config
   ```

6. Admin-Benutzer erstellen:
   ```bash
   php bin/console app:create-admin
   ```

7. Frontend-Assets erstellen:
   ```bash
   npm run build
   ```

8. Entwicklungsserver starten:
   ```bash
   symfony server:start
   ```

**Voraussetzungen:**
- PHP 8.2+
- Composer
- Node.js 18+
- MySQL 8.0+
- Symfony CLI (optional)
