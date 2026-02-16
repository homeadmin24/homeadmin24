# homeadmin24 - WEG-Verwaltungssystem

[![Deploy to DigitalOcean](https://img.shields.io/badge/Deploy%20to-DigitalOcean-0080FF?logo=digitalocean&logoColor=white)](https://cloud.digitalocean.com/apps/new?repo=https://github.com/homeadmin24/homeadmin24/tree/main)
[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://www.gnu.org/licenses/agpl-3.0)

Umfassendes Immobilienverwaltungssystem für deutsche Wohnungseigentümergemeinschaften (WEG) mit Finanzverfolgung, Zahlungsverwaltung, Rechnungsverarbeitung und automatisierter Hausgeldabrechnung.

**Open Source & Copyleft**: Lizenziert unter [GNU AGPL v3.0](LICENSE) - Freie Nutzung, Änderung und kommerzielle Verwendung erlaubt.

---

## 🚀 Quick Start

### Lokale Entwicklung (Docker)

```bash
git clone https://github.com/homeadmin24/homeadmin24.git
cd homeadmin24
docker compose up -d --build
sleep 10
docker compose exec web composer install --no-interaction
docker compose exec web php bin/console doctrine:database:create --if-not-exists
docker compose exec web php bin/console doctrine:schema:update --force
docker compose exec web php bin/console doctrine:fixtures:load --group=demo-data --no-interaction
docker compose exec web php bin/console cache:clear
```

**Access:** http://127.0.0.1:8000
**Login:** `wegadmin@demo.local` / `demo123`

📖 **Ausführliche Anleitung:** [docs/setup_local.md](docs/setup_local.md)

### Production Deployment

[![Deploy to DigitalOcean](https://img.shields.io/badge/Deploy%20to-DigitalOcean-0080FF?logo=digitalocean&logoColor=white)](https://cloud.digitalocean.com/apps/new?repo=https://github.com/homeadmin24/homeadmin24/tree/main)

📖 **Deployment-Optionen:** [docs/setup_production.md](docs/setup_production.md)
- **App Platform** (Managed, $12/mo)
- **Droplet** (VPS Self-Hosted, $6/mo)
- **Multi-Droplet** (Production + Auto-Reset Demo)

---

## ✨ Kernfunktionen

### Immobilienverwaltung
- **WEG-Verwaltung**: Zentrale Verwaltung mehrerer Eigentumseinheiten
- **Eigentümerverwaltung**: Details, Stimmrechte, Miteigentumsanteile

### Finanzverwaltung
- **Zahlungsverfolgung**: Einnahmen/Ausgaben mit Auto-Kategorisierung
- **Kostenkonten**: Kontenplan mit umlagefähigen Kosten
- **Kontostandsverwaltung**: Automatisierte Saldenberechnung

### Dienstleister & Rechnungen
- **Dienstleisterverwaltung**: Handwerker, Verträge, Kontaktdaten
- **Rechnungsverarbeitung**: Fälligkeiten, Steuerinformationen, §35a EStG

### Hausgeldabrechnungen
- **Automatisierte PDF/TXT-Generierung**
- **Eigentümeranteile** nach Miteigentumsanteilen (MEA)
- **§35a EStG**: Steuerlich absetzbare Leistungen
- **Wirtschaftsplan** für Folgejahr

### CSV-Import & Automation
- **Kontoauszug-Import** (Sparkasse SEPA-Format)
- **Auto-Kategorisierung** mit Pattern-Matching
- **Duplikatserkennung** (3-stufiges Fallback-System)

---

## 🛠️ Tech Stack

- **Backend**: Symfony 8.0 (PHP 8.4+)
- **Datenbank**: MySQL 9
- **Frontend**: Tailwind CSS, Flowbite, Stimulus.js, Webpack Encore
- **PDF**: Headless Chrome (Puppeteer)

---

## 📚 Dokumentation

### Getting Started
- **[Lokales Setup & Development](docs/setup_local.md)** - Docker-Entwicklungsumgebung, Development Workflows, Troubleshooting
- **[Production Deployment](docs/setup_production.md)** - App Platform, Droplets, Multi-Droplet

### Detailed Documentation
- **[Core System](docs/core_system.md)** - CSV-Import, Zahlungskategorien, Auth-System, Fixtures, Rücklagenzuführung
- **[AI Integration](docs/ai_01_integration.md)** - AI overview, configuration, privacy, learning
- **[Technical Documentation](docs/technical.md)** - Parser Architecture, HGA Migration, Calculation Improvements

---

## 📦 Demo-Daten

Nach dem Setup verfügbar:
- 3 WEG (Musterhausen, Berlin, Hamburg)
- 12 Wohneinheiten mit Eigentümern
- 145 Zahlungen (Einnahmen/Ausgaben)
- 8 Dienstleister, 22 Rechnungen
- 6 Demo-Benutzer mit verschiedenen Rollen

**Demo-Logins:**
- `wegadmin@demo.local` (ROLE_ADMIN)
- `buchhalter@demo.local` (ROLE_ACCOUNTANT)
- `viewer@demo.local` (ROLE_VIEWER)

Alle Passwörter: `demo123`

---

## 🤝 Contributing

Contributions welcome! Dieses Projekt ist Open Source unter AGPL v3.0.

- **Issues**: [GitHub Issues](https://github.com/homeadmin24/homeadmin24/issues)
- **Developer Guide**: [docs/setup_local.md](docs/setup_local.md)
- **License**: [GNU AGPL v3](LICENSE)

---

## 📄 Lizenz

homeadmin24 ist lizenziert unter der [GNU Affero General Public License v3.0](LICENSE).

**Das bedeutet:**
- ✅ Freie Nutzung, Änderung und Verteilung
- ✅ Kommerzielle Nutzung erlaubt (Hosting, Beratung, Support)
- ✅ Transparenz und Community-Beiträge willkommen
- ⚠️ Änderungen müssen unter derselben Lizenz veröffentlicht werden
- ⚠️ Netzwerk-Nutzer haben Anspruch auf den Quellcode (AGPL §13)
