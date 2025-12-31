# Lokale Entwicklungsumgebung Setup

## Voraussetzungen

- Docker Desktop installiert und gestartet
- Git installiert
- Node.js 20.x installiert (für Frontend-Entwicklung)

## Schnellstart

### Option 1: Automatisches Setup (Empfohlen)

```bash
# Repository klonen
git clone https://github.com/homeadmin24/homeadmin24.git
cd homeadmin24

# Backend Setup (Docker)
./setup.sh

# Frontend Setup (LOKAL - außerhalb des Containers!)
npm install
npm run dev
```

Das Script führt automatisch aus:
1. Docker Container starten
2. Auf MySQL warten
3. Composer Dependencies installieren
4. Datenbank erstellen
5. Schema anlegen
6. Demo-Daten laden
7. Cache leeren

**WICHTIG:** Nach `./setup.sh` MUSS `npm run dev` ausgeführt werden, sonst fehlen die Frontend-Assets!

### Option 2: Manuelles Setup

```bash
# Backend Setup (Docker)
# 1. Container starten
docker compose up -d

# 2. Auf MySQL warten (10 Sekunden)
sleep 10

# 3. Composer Dependencies installieren
docker compose exec web composer install

# 4. Datenbank erstellen
docker compose exec web php bin/console doctrine:database:create --if-not-exists

# 5. Schema anlegen
docker compose exec web php bin/console doctrine:schema:update --force

# 6. Demo-Daten laden
docker compose exec web php bin/console doctrine:fixtures:load --group=demo-data --no-interaction

# 7. Cache leeren
docker compose exec web php bin/console cache:clear

# Frontend Setup (LOKAL - außerhalb des Containers!)
# 8. Node.js Dependencies installieren
npm install

# 9. Frontend Assets bauen (Development Mode)
npm run dev
```

**WICHTIG:** Schritte 8-9 MÜSSEN lokal ausgeführt werden, NICHT im Container!

## Zugriff auf die Anwendung

### URLs
- **Hauptanwendung:** http://127.0.0.1:8000
- **Alternative (mit DNS):** http://web.homeadmin24.orb.local/

### Demo-Login-Daten

| E-Mail | Rolle | Passwort |
|--------|-------|----------|
| `wegadmin@demo.local` | ROLE_ADMIN | `ChangeMe123!` |
| `buchhalter@demo.local` | ROLE_ACCOUNTANT | `ChangeMe123!` |
| `viewer@demo.local` | ROLE_VIEWER | `ChangeMe123!` |

### MySQL Zugriff

- **Host:** 127.0.0.1
- **Port:** 3307
- **User:** root
- **Passwort:** rootpassword
- **Datenbank:** homeadmin24

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
docker compose up -d

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
# MySQL kaputt? → docker compose down -v && ./setup.sh
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
./setup.sh
npm install && npm run dev
```

## Weiterführende Dokumentation

- [Core System Documentation](core_system.md) - CSV import, payment categorization, auth
- [AI Integration](ai_integration.md) - AI-powered features
- [Production Deployment](setup_setup_production.md) - Deployment guides
- [Development Guide](setup_setup_development.md) - Developer workflows
- [Fixture Strategy](fixture_strategy.md) - Database setup reference
