# Lokale Entwicklungsumgebung Setup

## Voraussetzungen

- Docker Desktop installiert und gestartet
- Git installiert

## Schnellstart

### Option 1: Automatisches Setup (Empfohlen)

```bash
# Repository klonen
git clone https://github.com/homeadmin24/homeadmin24.git
cd homeadmin24

# Automatisches Setup ausführen
./setup.sh
```

Das Script führt automatisch aus:
1. Docker Container starten
2. Auf MySQL warten
3. Composer Dependencies installieren
4. Datenbank erstellen
5. Schema anlegen
6. Demo-Daten laden
7. Cache leeren

### Option 2: Manuelles Setup

```bash
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
```

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
```

Nach Code-Änderungen:
```bash
# Cache leeren (nur bei Config-Änderungen nötig)
docker compose exec web php bin/console cache:clear
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

### Problem: "Unable to generate a URL for the named route"

**Ursache:** Routen werden nicht gefunden (meist nach Symfony-Upgrade)

**Lösung:**
```bash
# Cache leeren
docker compose exec web php bin/console cache:clear

# Container neu starten
docker compose restart web
```

### Problem: "Column not found" Fehler

**Ursache:** Datenbank-Schema veraltet

**Lösung:**
```bash
# Schema aktualisieren
docker compose exec web php bin/console doctrine:schema:update --force
```

### Problem: Container startet nicht

**Ursache:** Port 8000 bereits belegt

**Lösung:** Anderen Port in `docker-compose.yaml` verwenden:
```yaml
ports:
  - "8001:80"  # Statt 8000:80
```

### Problem: MySQL-Container startet nicht

**Ursache:** Volumes beschädigt

**Lösung:**
```bash
# Alle Container und Volumes löschen
docker compose down -v

# Neu aufsetzen
./setup.sh
```

### Problem: Permission Denied Fehler

**Ursache:** Falsche Dateirechte im Container

**Lösung:**
```bash
# Rechte korrigieren
docker compose exec web chown -R www-data:www-data /var/www/html/var
docker compose exec web chmod -R 755 /var/www/html/var
```

## System komplett zurücksetzen

```bash
# 1. Container und Volumes löschen
docker compose down -v

# 2. Cache und Logs lokal löschen
rm -rf var/cache/* var/log/*

# 3. Setup neu durchführen
./setup.sh
```

## Weiterführende Dokumentation

- [Core System Documentation](core_system.md) - CSV import, payment categorization, auth
- [AI Integration](ai_integration.md) - AI-powered features
- [Production Deployment](setup_setup_production.md) - Deployment guides
- [Development Guide](setup_setup_development.md) - Developer workflows
- [Fixture Strategy](fixture_strategy.md) - Database setup reference
