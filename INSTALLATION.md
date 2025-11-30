# homeadmin24 - Production Deployment Guide

Dieses Dokument beschreibt das Production-Deployment von homeadmin24 auf DigitalOcean Droplets. Für die empfohlene lokale Docker-Installation siehe [README.md](README.md).

---

## DigitalOcean Droplet (VPS) Deployment

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

---

## Support & Dokumentation

- **Projektdokumentation:** [CLAUDE.md](CLAUDE.md)
- **Deployment-Guide:** [.droplet/README.md](.droplet/README.md)
- **Issues & Bug Reports:** [GitHub Issues](https://github.com/homeadmin24/homeadmin24/issues)
- **Lizenz:** [GNU AGPL v3](LICENSE)
