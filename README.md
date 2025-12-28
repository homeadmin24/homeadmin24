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
./setup.sh
```

**Access:** http://127.0.0.1:8000
**Login:** `wegadmin@demo.local` / `ChangeMe123!`

📖 **Ausführliche Anleitung:** [docs/local-setup.md](docs/local-setup.md)

### Production Deployment

[![Deploy to DigitalOcean](https://img.shields.io/badge/Deploy%20to-DigitalOcean-0080FF?logo=digitalocean&logoColor=white)](https://cloud.digitalocean.com/apps/new?repo=https://github.com/homeadmin24/homeadmin24/tree/main)

📖 **Deployment-Optionen:** [docs/production.md](docs/production.md)
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
- **PDF**: DomPDF

---

## 📚 Dokumentation

### Getting Started
- **[Lokales Setup](docs/local-setup.md)** - Docker-Entwicklungsumgebung, Troubleshooting
- **[Production Deployment](docs/production.md)** - App Platform, Droplets, Multi-Droplet
- **[Developer Guide](docs/development.md)** - Dokumentationsindex, Development Workflows

### Detailed Documentation
- **[docs/business-logic/](docs/business-logic/)** - WEG-Geschäftslogik, Finanzberechnungen, Rücklagenzuführung
- **[docs/core-system/](docs/core-system/)** - CSV-Import, Zahlungskategorien, Auth-System, Fixtures
- **[docs/technical/](docs/technical/)** - Database Schema, Parser Architecture, Migrations

---

## 📦 Demo-Daten

Nach `./setup.sh` verfügbar:
- 3 WEG (Musterhausen, Berlin, Hamburg)
- 12 Wohneinheiten mit Eigentümern
- 145 Zahlungen (Einnahmen/Ausgaben)
- 8 Dienstleister, 22 Rechnungen
- 6 Demo-Benutzer mit verschiedenen Rollen

**Demo-Logins:**
- `wegadmin@demo.local` (ROLE_ADMIN)
- `buchhalter@demo.local` (ROLE_ACCOUNTANT)
- `viewer@demo.local` (ROLE_VIEWER)

Alle Passwörter: `ChangeMe123!`

---

## 🤝 Contributing

Contributions welcome! Dieses Projekt ist Open Source unter AGPL v3.0.

- **Issues**: [GitHub Issues](https://github.com/homeadmin24/homeadmin24/issues)
- **Developer Guide**: [docs/development.md](docs/development.md)
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
