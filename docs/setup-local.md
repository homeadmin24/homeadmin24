# Lokale Entwicklungsumgebung

## Voraussetzungen

- Docker Desktop oder OrbStack
- Git
- Node.js 22 für die Asset-Entwicklung

## Quick Start

```bash
git clone https://github.com/homeadmin24/homeadmin24.git
cd homeadmin24
cp .env.local.example .env.local
docker compose up -d --build
sleep 10
docker compose exec web composer install --no-interaction
docker compose exec web php bin/console doctrine:database:create --if-not-exists
docker compose exec web php bin/console doctrine:schema:update --force
docker compose exec web php bin/console doctrine:fixtures:load --group=demo-data --no-interaction
docker compose exec web php bin/console cache:clear
npm install
npm run dev
```

Die Anwendung läuft unter <http://127.0.0.1:8000>. Anmeldung:
`wegadmin@demo.local` / `demo123`.

`.env.local` ist nur lokal und darf keine Production-Secrets enthalten. Die
Vorlage enthält zueinander passende lokale MySQL-Zugangsdaten.

## Häufige Befehle

```bash
# Container und Logs
docker compose up -d
docker compose down
docker compose logs -f web

# Symfony
docker compose exec web php bin/console cache:clear
docker compose exec web php bin/console doctrine:schema:update --force
docker compose exec web php bin/console doctrine:fixtures:load --group=demo-data --no-interaction

# Qualität
docker compose exec web composer cs-fix-dry
docker compose exec web composer phpstan
docker compose exec web composer test

# Frontend
npm run watch
npm run build

# MySQL-Konsole
docker compose exec mysql sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" homeadmin24'
```

## Optional: AI und DocIntel

AI ist standardmäßig deaktiviert. Der Compose-Stack startet keinen
Ollama-Container. Für Claude oder einen externen Ollama-Host konfiguriere die
nicht versionierte `.env.local`. DocIntel kann bei Bedarf mit dem normalen
Compose-Stack gebaut werden; ohne den Dienst bleibt die Text-Extraktion aktiv.

## Troubleshooting

```bash
# Vollständiger lokaler Reset – löscht die lokale Datenbank
docker compose down -v
docker compose up -d --build

# Nach Änderungen an Symfony-Konfiguration
docker compose exec web php bin/console cache:clear
```

Wenn `127.0.0.1:8000` belegt ist, ändere in `docker-compose.yaml` die linke
Portseite, zum Beispiel auf `127.0.0.1:8001:80`.

## Weiterführend

- [Core System](core-system.md)
- [AI-Übersicht](al-overview.md)
- [Production Deployment](setup-production.md)
