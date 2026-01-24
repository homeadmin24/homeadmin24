# Lokale Entwicklungsumgebung Setup

## Voraussetzungen

- Docker Desktop oder Orbstack installiert und gestartet
- Git installiert
- Node.js 20.x installiert (für Frontend-Entwicklung)
- Chrome/Chromium + Puppeteer (für PDF-Renderer)

## Schnellstart

```bash
# Repository klonen
git clone https://github.com/homeadmin24/homeadmin24.git
cd homeadmin24

# Backend Setup (Docker)
docker compose up -d --build web mysql ollama doc-intel
sleep 10
docker compose exec web composer install --no-interaction
docker compose exec web php bin/console doctrine:database:create --if-not-exists
docker compose exec web php bin/console doctrine:schema:update --force
docker compose exec web php bin/console doctrine:fixtures:load --group=demo-data --no-interaction
docker compose exec web php bin/console cache:clear

# Frontend Setup (LOKAL - außerhalb des Containers!)
npm install
npm run dev
```

**WICHTIG:** Die Frontend-Assets müssen lokal gebaut werden, nicht im Container.

## AI Services (Nur lokal)

Für lokale Entwicklung werden **Ollama** und **DocIntel** verwendet. Demo/Prod werden aktuell **nicht** eingesetzt.

### Ollama (lokal, via docker-compose.yaml)

Ollama wird automatisch mit `docker compose up -d --build` gestartet.

```bash
# Modell einmalig laden (falls noch nicht vorhanden)
docker exec -it hausman-ollama ollama pull llama3.1:8b
```

### DocIntel (lokal, externes OCR/LayoutLM)

DocIntel ist **optional** und wird lokal per `docker compose up -d --build` mitgestartet.

```bash
# Beispiel .env.local Ergänzungen
DOCINTEL_ENABLED=true
DOCINTEL_URL=http://doc-intel:8000
```

Wenn DocIntel nicht läuft, bleibt der Parser bei Text-Extraktion/LLM-Fallback.

## Zugriff auf die Anwendung

### URLs
- **Hauptanwendung:** http://127.0.0.1:8000
- **Alternative (mit DNS):** http://web.homeadmin24.orb.local/

### Demo-Login-Daten

| E-Mail | Rolle | Passwort |
|--------|-------|----------|
| `wegadmin@demo.local` | ROLE_ADMIN | `demo123` |
| `buchhalter@demo.local` | ROLE_ACCOUNTANT | `demo123` |
| `viewer@demo.local` | ROLE_VIEWER | `demo123` |

### MySQL Zugriff

**Verbindungsdaten:**
- **Host:** 127.0.0.1
- **Port:** 3307
- **User:** root
- **Passwort:** rootpassword
- **Datenbank:** homeadmin24

**MySQL Console öffnen:**
```bash
cd /path/to/homeadmin24

# Direkt in die homeadmin24 Datenbank einloggen
docker compose exec mysql mysql -uroot -prootpassword homeadmin24

# Vom Host aus (wenn MySQL Client installiert ist)
mysql -h127.0.0.1 -P3307 -uroot -prootpassword homeadmin24
```

## Demo-Daten Übersicht

Nach dem Setup stehen folgende Demo-Daten zur Verfügung:

- **3 WEG** (Musterhausen, Berlin, Hamburg)
- **12 Wohneinheiten** mit Eigentümern
- **145 Zahlungen** (Einnahmen/Ausgaben)
- **8 Dienstleister**
- **22 Rechnungen**
- **6 Demo-Benutzer** mit verschiedenen Rollen

## Häufige Befehle

### Container-Verwaltung

```bash
# Container starten
docker compose up -d --build

# Container stoppen
docker compose down

# Container stoppen und Datenbank löschen
docker compose down -v

# Logs anzeigen
docker compose logs -f web

# Shell im Web-Container öffnen
docker compose exec web bash
```

### Symfony-Befehle

```bash
# Cache leeren
docker compose exec web php bin/console cache:clear

# Routen anzeigen
docker compose exec web php bin/console debug:router

# Demo-Daten neu laden (löscht alte Daten!)
docker compose exec web php bin/console doctrine:fixtures:load --group=demo-data

# Datenbank-Schema prüfen
docker compose exec web php bin/console doctrine:schema:validate

# Datenbank-Schema aktualisieren
docker compose exec web php bin/console doctrine:schema:update --force
```

### Datenbank-Befehle

```bash
# Datenbank leeren und neu aufsetzen
docker compose exec web php bin/console doctrine:database:drop --force
docker compose exec web php bin/console doctrine:database:create
docker compose exec web php bin/console doctrine:schema:update --force
docker compose exec web php bin/console doctrine:fixtures:load --group=demo-data --no-interaction

# Backup erstellen
docker exec homeadmin24-mysql-1 mysqldump -uroot -prootpassword homeadmin24 > backup_$(date +%Y%m%d_%H%M%S).sql

# Backup importieren
docker exec -i homeadmin24-mysql-1 mysql -uroot -prootpassword homeadmin24 < backup.sql
```

## Entwicklung

### Code-Änderungen

Dank der Volume-Mounts in `docker-compose.yaml` werden Änderungen sofort übernommen:

```yaml
volumes:
  - ./src:/var/www/html/src              # Controller, Services, Entities
  - ./config:/var/www/html/config        # Konfiguration
  - ./templates:/var/www/html/templates  # Twig-Templates
  - ./var:/var/www/html/var              # Cache, Logs
  - ./vendor:/var/www/html/vendor        # Composer Dependencies
  - ./assets:/var/www/html/assets        # Frontend: JS, CSS
  - ./public/build:/var/www/html/public/build  # Compiled frontend assets
```

Nach Code-Änderungen:
```bash
# Cache leeren (nur bei Config-Änderungen nötig)
docker compose exec web php bin/console cache:clear
```

### Frontend Development (JavaScript/CSS)

**WICHTIG:** Frontend-Assets (JavaScript, CSS) müssen **außerhalb des Containers** gebaut werden!

#### Einmalige Setup

```bash
# 1. Node.js Dependencies installieren (lokal, NICHT im Container!)
npm install

# 2. Prüfen ob Webpack Encore funktioniert
npm run dev
```

#### Development Workflow

**Option A: Watch Mode (empfohlen für aktive Entwicklung)**
```bash
# Assets automatisch bei Änderungen neu bauen
npm run watch

# In separatem Terminal: Development Server mit Hot Reload
npm run dev-server
```

**Option B: Manuelle Builds**
```bash
# Development Build (schnell, mit Source Maps, OHNE Versionierung)
npm run dev

# Production Build (optimiert, minified, MIT Versionierung)
npm run build
```

**⚠️ WICHTIG: Development vs Production Build**
- **Für lokale Entwicklung:** IMMER `npm run dev` oder `npm run watch` verwenden!
- **`npm run build`** (production) erstellt versionierte Dateien → 404 Fehler in dev!

#### Häufige Frontend-Probleme

**Assets laden nicht (404 Fehler)**
```bash
npm run dev                                          # NICHT npm run build!
docker compose exec web php bin/console cache:clear
# Browser: Cmd+Shift+R (hard refresh)
```

**Stimulus Controller nicht gefunden**
```bash
npm run dev                   # Assets neu bauen
# Browser: Cache leeren + hard refresh
```

**"Module not found" beim Build**
```bash
rm -rf node_modules package-lock.json
npm install
npm run dev
```

#### Stimulus Controller hinzufügen

```bash
# 1. Datei erstellen: assets/controllers/my_feature_controller.js
# 2. Controller wird automatisch registriert als: my-feature
# 3. Assets neu bauen: npm run dev
# 4. Template: <div data-controller="my-feature">...</div>
```

#### Debugging Frontend

```javascript
// Browser Console (F12):
window.Stimulus.controllers.map(c => c.identifier)  // Alle Controller
document.querySelectorAll('script[src*="app"]')     // Geladene Scripts
```

## PDF-Renderer (Chrome/Puppeteer)

Standard ist **Headless Chrome (Puppeteer)**.

### Chrome-Renderer aktivieren

1. **Puppeteer installieren (lokal oder im Server-Container):**
```bash
cd homeadmin24
npm install puppeteer
```

2. **Optional: Standard-Renderer per Env setzen:**
```bash
export HGA_PDF_RENDERER=chrome
```

3. **Optional: Pfade setzen (wenn Node/Puppeteer nicht im Standardpfad liegt):**
```bash
export HGA_NODE_PATH=/pfad/zum/node
export HGA_PUPPETEER_MODULE_PATH=/pfad/zum/node_modules/puppeteer
```

4. **Renderer per URL testen (Preview):**
```
http://127.0.0.1:8000/abrechnung/2025/preview/0003?renderer=chrome
```

**Hinweis:** Wenn der PDF-Export mit `Cannot find module 'puppeteer'` fehlschlägt, ist `HGA_PUPPETEER_MODULE_PATH` nicht gesetzt oder Puppeteer fehlt im Node-Environment.

### Neue Entity erstellen

```bash
# Entity generieren
docker compose exec web php bin/console make:entity

# Migration erstellen (optional)
docker compose exec web php bin/console make:migration

# Oder direkt Schema aktualisieren
docker compose exec web php bin/console doctrine:schema:update --force
```

## Troubleshooting

**Routing-Fehler ("Unable to generate a URL")**
```bash
docker compose exec web php bin/console cache:clear
docker compose restart web
```

**Datenbank-Fehler ("Column not found")**
```bash
docker compose exec web php bin/console doctrine:schema:update --force
```

**Container startet nicht**
```bash
# Port 8000 belegt? → docker-compose.yaml ändern: "8001:80"
# MySQL kaputt? → docker compose down -v && docker compose up -d
```

**Permission Denied**
```bash
docker compose exec web chown -R www-data:www-data /var/www/html/var
docker compose exec web chmod -R 755 /var/www/html/var
```

**System komplett zurücksetzen**
```bash
docker compose down -v
rm -rf var/cache/* var/log/*
docker compose up -d
sleep 10
docker compose exec web composer install --no-interaction
docker compose exec web php bin/console doctrine:database:create --if-not-exists
docker compose exec web php bin/console doctrine:schema:update --force
docker compose exec web php bin/console doctrine:fixtures:load --group=demo-data --no-interaction
docker compose exec web php bin/console cache:clear
npm install && npm run dev
```

## Weiterführende Dokumentation

- [Core System Documentation](core_system.md) - CSV import, payment categorization, auth
- [AI Integration](ai_integration.md) - AI-powered features
- [Production Deployment](setup_setup_production.md) - Deployment guides
- [Development Guide](setup_setup_development.md) - Developer workflows
- [Fixture Strategy](fixture_strategy.md) - Database setup reference
