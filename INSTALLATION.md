# homeadmin24 - Production Deployment Guide

Dieses Dokument beschreibt Production-Deployment-Optionen für homeadmin24. Für die lokale Docker-Installation siehe [README.md](README.md).

## Inhaltsverzeichnis

- [Option 1: DigitalOcean App Platform (Managed)](#option-1-digitalocean-app-platform-managed)
- [Option 2: DigitalOcean Droplet (VPS Self-Hosted)](#option-2-digitalocean-droplet-vps-self-hosted)
- [Vergleich der Deployment-Optionen](#vergleich-der-deployment-optionen)

---

## Option 1: DigitalOcean App Platform (Managed)

**One-Click Cloud Deployment** - Ideal für schnelles Production-Deployment ohne Serverkonfiguration.

[![Deploy to DigitalOcean](https://img.shields.io/badge/Deploy%20to-DigitalOcean-0080FF?logo=digitalocean&logoColor=white)](https://cloud.digitalocean.com/apps/new?repo=https://github.com/homeadmin24/homeadmin24/tree/main)

### Schritt 1: Deployment starten

1. Klicken Sie auf den "Deploy to DigitalOcean" Button oben
2. Verbinden Sie Ihr GitHub-Repository
3. DigitalOcean erstellt automatisch:
   - PHP-Web-Service mit Nginx
   - MySQL 8.0 Production-Datenbank
   - Automatische SSL-Zertifikate
   - HTTPS-Zugriff mit eigener Domain

### Schritt 2: Initiale Konfiguration

Nach der Bereitstellung via DigitalOcean App Platform Console:

```bash
# Systemkonfiguration laden
php bin/console doctrine:fixtures:load --group=system-config --no-interaction

# Admin-Benutzer erstellen
php bin/console app:create-admin
```

### Vorteile

- ✅ **Keine Serverkonfiguration** - Alles wird automatisch eingerichtet
- ✅ **Automatische Backups** - Tägliche Datenbank-Backups inklusive
- ✅ **SSL-Zertifikate** - Automatisch konfiguriert und erneuert
- ✅ **Skalierbar** - Bei Bedarf mehr Ressourcen hinzufügen
- ✅ **Auto-Deployments** - Automatische Updates bei Git-Push
- ✅ **Zero Downtime** - Automatische Rolling-Deployments

### Kosten

- **App Service:** $5/Monat (Basic)
- **MySQL Database:** $7/Monat (Production DB)
- **Gesamt:** ~$12/Monat

### Management

```bash
# App Console öffnen
# https://cloud.digitalocean.com/apps

# Logs anzeigen (in App Platform Console)
# Apps → Your App → Runtime Logs

# Umgebungsvariablen ändern
# Apps → Your App → Settings → Environment Variables

# Manuelles Deployment triggern
# Apps → Your App → Deploy
```

### Wann App Platform wählen?

✅ Empfohlen für:
- Schnelles Production-Deployment
- Keine Server-Management-Erfahrung
- Budget von $12+/Monat
- Automatische Backups gewünscht
- Skalierbarkeit wichtig

---

## Option 2: DigitalOcean Droplet (VPS Self-Hosted)

Günstigste Option für Production-Deployment mit voller Kontrolle ($6/Monat).

📖 **Detaillierte Deployment-Dokumentation:** [.droplet/README.md](.droplet/README.md)
- Erklärung aller `.env` Dateien
- GitHub Actions Workflow
- Automatisierte Demo- und Production-Deployments

### Schritt 1: Droplet erstellen

1. DigitalOcean Droplet erstellen (Ubuntu 22.04/24.04, $6/Monat Basic Droplet)
2. Domain A-Record auf Droplet IP setzen
3. SSH-Zugriff einrichten

### Schritt 2: Server einrichten

```bash
# Setup-Script herunterladen und ausführen
wget https://raw.githubusercontent.com/homeadmin24/homeadmin24/main/.droplet/setup-production.sh
chmod +x setup-production.sh
sudo ./setup-production.sh yourdomain.com your@email.com
```

### Schritt 3: Repository klonen und konfigurieren

```bash
cd /opt/homeadmin24
git clone https://github.com/homeadmin24/homeadmin24.git .
cp .env.example .env
nano .env  # Datenbank-Zugangsdaten und APP_SECRET anpassen
```

### Schritt 4: Deployment ausführen

```bash
sudo bash .droplet/deploy-production.sh yourdomain.com your@email.com
```

### Schritt 5: Admin-Benutzer erstellen

```bash
docker-compose exec web php bin/console app:create-admin
```

### Vorteile

- ✅ Günstigste Option ($6/Monat für unbegrenzte Apps)
- ✅ Volle Root-Kontrolle über Server
- ✅ Docker-basiert (identisch mit lokaler Entwicklung)
- ✅ Automatische SSL-Zertifikate (Let's Encrypt)
- ✅ Automatische Deployments via GitHub Actions
- ✅ Tägliche automatische Backups (3 AM)

### Automated Deployment

Nach Setup GitHub Repository Secrets konfigurieren:
- `PRODUCTION_DROPLET_HOST`: Droplet IP-Adresse
- `DROPLET_USER`: SSH-Benutzer (meist `root`)
- `DROPLET_SSH_KEY`: Private SSH-Key für Zugriff

Dann deployed jeder Push auf `main` automatisch via GitHub Actions.

### Updates

```bash
cd /opt/homeadmin24
git pull origin main
sudo bash .droplet/deploy-production.sh yourdomain.com your@email.com
```

### Management-Befehle

```bash
# Logs anzeigen
cd /opt/homeadmin24
docker-compose logs -f web

# Manuelles Backup erstellen
/usr/local/bin/homeadmin24-backup.sh

# Backups anzeigen
ls -lh /opt/homeadmin24/backups/

# SSL-Zertifikate prüfen
certbot certificates

# Container neu starten
docker-compose restart
```

### Wann Droplet wählen?

✅ Empfohlen für:
- Kostenoptimiertes Deployment ($6/Monat)
- Volle Server-Kontrolle gewünscht
- Server-Management-Erfahrung vorhanden
- Mehrere Apps auf einem Server
- Docker-basiertes Deployment bevorzugt

---

## Vergleich der Deployment-Optionen

| Kriterium | App Platform (Managed) | Droplet (VPS) |
|-----------|------------------------|---------------|
| **Kosten** | $12/Monat | $6/Monat |
| **Setup-Zeit** | 10 Minuten | 30 Minuten |
| **Server-Management** | ❌ Nicht nötig | ✅ Erforderlich |
| **Automatische Backups** | ✅ Inklusive | ⚠️ Manuell konfiguriert |
| **SSL-Zertifikate** | ✅ Automatisch | ✅ Automatisch (Let's Encrypt) |
| **Skalierung** | ✅ Ein-Klick | ⚠️ Manuelle Anpassung |
| **Auto-Deployments** | ✅ Git-Push | ✅ GitHub Actions |
| **Root-Zugriff** | ❌ Nein | ✅ Vollständig |
| **Docker-Support** | ❌ Nein | ✅ Ja |
| **Mehrere Apps** | ⚠️ Teuer | ✅ Unbegrenzt |

### Empfehlung

- **Einsteiger / Schnellstart:** → **App Platform**
- **Erfahrene Nutzer / Kostenoptimiert:** → **Droplet**
- **Mehrere WEG-Instanzen:** → **Droplet** (mehrere Apps auf einem Server)

---

## Support & Dokumentation

- **Lokale Installation:** [README.md](README.md)
- **Developer Documentation:** [DEVELOPMENT.md](DEVELOPMENT.md)
- **Droplet Deployment Details:** [.droplet/README.md](.droplet/README.md)
- **Issues & Bug Reports:** [GitHub Issues](https://github.com/homeadmin24/homeadmin24/issues)
- **Lizenz:** [GNU AGPL v3](LICENSE)
