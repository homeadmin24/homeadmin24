# CLAUDE.md

Guidance for Claude Code when working in this repository.

## Project

homeadmin24 is a Symfony application for German WEG property management. It
handles properties and units, payments, invoices, document processing and
Hausgeldabrechnungen (HGA).

- Symfony 8.1 on PHP 8.4
- MySQL 9
- Webpack Encore 7, Babel 8 and Node.js 22
- Twig, Stimulus, Tailwind and Flowbite
- PDF generation through Puppeteer and Chromium

## Local development

The normal local environment runs in Docker:

```bash
cp .env.local.example .env.local   # only if no local configuration exists
docker compose up -d --build
docker compose exec web php bin/console doctrine:schema:update --force
docker compose exec web php bin/console doctrine:fixtures:load --group=demo-data --no-interaction
```

Open `http://127.0.0.1:8000`. The demo login is
`wegadmin@demo.local` / `demo123`.

Use the `web` and `mysql` services, never a non-existent `db` service:

```bash
docker compose exec web php bin/console cache:clear
docker compose exec web composer test
docker compose exec web composer phpstan
docker compose exec web composer cs-fix-dry
docker compose exec web npm run build
docker compose exec mysql mysql -uroot -p
```

## Application structure

- `src/Entity/`: Doctrine entities
- `src/Controller/`: HTTP endpoints
- `src/Service/Hga/`: HGA calculation, configuration and report generation
- `src/Service/Parser/`: invoice parser implementations
- `src/Service/AI/`: optional Claude, Ollama and document-intelligence adapters
- `templates/`: Twig UI and PDF templates
- `assets/`: JavaScript, Stimulus controllers and styles
- `config/`: Symfony and service configuration

For HGA changes, keep calculations database-driven and use the existing
calculation services. Add or update tests for calculation changes; do not
hardcode units, ownership shares or distribution keys.

## Environments and deployments

- Keep local credentials in `.env.local` and production credentials only in
  server-local `.env.prod.local`.
- Never commit API keys, database passwords, session cookies, backups or data
  exports.
- Demo deploys automatically from `main`; production deploys are started
  manually through GitHub Actions.
- Use `.droplet/deploy-production.sh` and `.droplet/deploy-demo.sh` for server
  deployments. Do not run local development commands against production.

See [docs/setup-local.md](docs/setup-local.md) and
[docs/setup-production.md](docs/setup-production.md) for the complete setup.
