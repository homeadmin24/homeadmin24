# Produktivbetrieb

Diese Anleitung beschreibt den Betrieb von homeadmin24 auf einem DigitalOcean
Droplet. Für die lokale Entwicklung siehe [Lokales Setup](setup-local.md).
Die Demo und die Produktion sind getrennte Installationen; in der Demo dürfen
keine echten oder vertraulichen Daten verwendet werden.

## Überblick

| Variante | Geeignet für | Betrieb |
| --- | --- | --- |
| DigitalOcean App Platform | Managed Hosting ohne Serververwaltung | Einrichtung und Umgebungsvariablen in DigitalOcean verwalten |
| DigitalOcean Droplet | Der empfohlene, Docker-basierte Betrieb | Volle Kontrolle über Server, Backups und Updates |
| Zwei Droplets | Produktivsystem und öffentliche Demo | Strikte Trennung der Daten und automatischer Demo-Reset |

Die Skripte in `.droplet/` sind auf den Droplet-Betrieb ausgelegt. Preise und
verfügbare DigitalOcean-Pläne ändern sich; wähle einen aktuellen Ubuntu- oder
Debian-Droplet mit ausreichend Arbeitsspeicher für Docker und MySQL.

## Produktiv-Droplet einrichten

Voraussetzungen: Eine frische Ubuntu- oder Debian-Instanz, ein
DNS-A-Record auf deren IP-Adresse sowie ein erreichbarer Port 80 für die
Zertifikatsausstellung.

```bash
# Als root auf dem neuen Droplet anmelden
ssh root@<droplet-ip>

# Grundsystem einrichten: Docker, Compose-Plugin, Nginx, Certbot und Firewall
wget https://raw.githubusercontent.com/homeadmin24/homeadmin24/main/.droplet/setup-production.sh
chmod +x setup-production.sh
./setup-production.sh deine-domain.tld admin@deine-domain.tld

# Anwendung auschecken und erstmals bereitstellen
cd /opt/homeadmin24-prod
git clone https://github.com/homeadmin24/homeadmin24.git .
bash .droplet/deploy-production.sh deine-domain.tld admin@deine-domain.tld

# Ersten Administrator anlegen
docker compose exec web php bin/console app:create-admin
```

`setup-production.sh` installiert nur das Grundsystem; es klont weder das
Repository noch startet es die Anwendung. Der erste Aufruf von
`deploy-production.sh` ist deshalb zwingend.

## Zugangsdaten und Konfiguration

Die Produktionszugangsdaten liegen ausschließlich auf dem Server in
`/opt/homeadmin24-prod/.env.prod.local`. Die Datei wird beim ersten Deployment
erzeugt, erhält die Berechtigung `0600` und wird nicht eingecheckt.

Bei einer Neuinstallation erzeugt das Deployment automatisch passende Werte
für `APP_SECRET`, `MYSQL_ROOT_PASSWORD` und `DATABASE_URL`. Bei einer bereits
laufenden Installation übernimmt es vorhandene Werte aus den aktiven Containern
oder einer älteren lokalen Env-Datei. Dabei bleiben insbesondere das
MySQL-Passwort und der bisherige Datenbankname erhalten.

Diese Werte gehören daher **nicht** in GitHub Actions Secrets. GitHub benötigt
nur die Zugangsdaten zum Droplet:

- `PRODUCTION_DROPLET_HOST` – öffentliche IP-Adresse des Produktiv-Droplets
- `DROPLET_SSH_KEY` – privater SSH-Schlüssel für den Benutzer `root`
- `PRODUCTION_DOMAIN` – Domain ohne `https://`
- `PRODUCTION_EMAIL` – E-Mail-Adresse für Let's Encrypt

Die Datenbank ist nur im Docker-Netzwerk unter `mysql:3306` erreichbar. Öffne
keinen Firewall-Port für MySQL.

## Deployment mit GitHub Actions

Das Produktionsworkflow **Deploy to Production** wird ausschließlich manuell
über GitHub Actions gestartet. Ein Push auf `main` veröffentlicht automatisch
nur die Demo.

Für das erste Deployment sowie nach Änderungen an Dockerfile, Composer- oder
Node-Abhängigkeiten wähle bei `quick_mode` den Wert `false`. Für reine
Codeänderungen kann `true` verwendet werden. Die Option `push_local_db` sollte
auf `false` bleiben, sofern ein bewusster, separat vorbereiteter Datenimport
nicht erforderlich ist.

Beim Umzug auf ein neues Droplet:

1. DNS-A-Record auf die neue IP ändern.
2. `PRODUCTION_DROPLET_HOST` in GitHub auf die neue IP setzen.
3. Das neue Droplet wie oben initialisieren.
4. Das Produktionsworkflow mit `quick_mode=false` starten.

## Betrieb und Kontrolle

```bash
cd /opt/homeadmin24-prod

# Container- und Anwendungsprotokolle
docker compose logs -f web
docker compose logs -f mysql

# Gesundheits- und Datenbankprüfung
curl -fsS https://deine-domain.tld/login
docker compose exec web php bin/console doctrine:query:sql "SELECT 1"

# Konfiguration ohne Ausgabe von Secrets prüfen
docker compose -f docker-compose.yaml -f docker-compose.prod.yml config --quiet

# Zertifikate prüfen bzw. erneuern
certbot certificates
certbot renew

# Ressourcen prüfen
df -h
docker system df
```

Der Einrichtungsprozess installiert einen täglichen Backup-Job. Ein Backup kann
auch manuell gestartet werden:

```bash
/usr/local/bin/homeadmin24-backup.sh
ls -lh /opt/homeadmin24-prod/backups/
```

Vor einer Wiederherstellung die Anwendung stoppen oder in Wartung versetzen und
ein aktuelles Backup der Daten sichern. Verwende beim Import den tatsächlichen
Datenbanknamen – bei neuen Installationen `homeadmin24`, bei übernommenen
Installationen gegebenenfalls einen bestehenden Namen:

```bash
cd /opt/homeadmin24-prod
zcat backups/homeadmin24_prod_YYYYMMDD_HHMMSS.sql.gz | \
  docker compose exec -T mysql sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" homeadmin24'
```

Ersetze `homeadmin24` im Befehl durch den Namen einer übernommenen
Bestandsdatenbank.

## Demo-Droplet

Die öffentliche Demo läuft getrennt unter `/opt/homeadmin24-demo` und wird
standardmäßig alle 30 Minuten mit Fixtures neu aufgebaut. Ihre Daten sind nicht
dauerhaft und dürfen niemals Produktionsdaten enthalten.

```bash
ssh root@<demo-droplet-ip>
wget https://raw.githubusercontent.com/homeadmin24/homeadmin24/main/.droplet/setup-demo.sh
chmod +x setup-demo.sh
./setup-demo.sh demo.deine-domain.tld admin@deine-domain.tld

cd /opt/homeadmin24-demo
git clone https://github.com/homeadmin24/homeadmin24.git .
bash .droplet/deploy-demo.sh demo.deine-domain.tld admin@deine-domain.tld
```

Für die Demo werden in GitHub Actions diese Secrets benötigt:

- `DEMO_DROPLET_HOST`
- `DROPLET_SSH_KEY`
- `DEMO_DOMAIN`
- `DEMO_EMAIL`

Ein Push auf `main` startet einen schnellen Demo-Deploy. Nach Änderungen an
Dockerfile oder Abhängigkeiten das Workflow **Deploy to Demo** manuell mit
`full_rebuild=true` ausführen.

```bash
# Demo sofort zurücksetzen
/usr/local/bin/homeadmin24-demo-reset.sh

# Reset-Protokoll ansehen
tail -f /var/log/homeadmin24-demo-reset.log
```

## Fehlerbehebung

```bash
# Docker- und Nginx-Zustand prüfen
cd /opt/homeadmin24-prod
docker compose ps
docker compose logs --tail=200 web
nginx -t
tail -f /var/log/nginx/error.log

# Vollständiger Neubau (keine Volumes löschen)
docker compose down
docker compose build --no-cache
docker compose up -d
```

Lösche auf dem Produktivsystem keine Docker-Volumes ohne geprüftes Backup. Bei
Problemen mit HTTPS müssen Domain-DNS, Port 80/443, Nginx-Konfiguration und die
Certbot-Protokolle unter `/var/log/letsencrypt/` geprüft werden.

## Weiterführend

- [Lokales Setup](setup-local.md)
- [GitHub Issues](https://github.com/homeadmin24/homeadmin24/issues)
- [GNU AGPL v3.0](../LICENSE)
