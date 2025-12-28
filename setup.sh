#!/bin/bash
set -e

echo "🏠 homeadmin24 - Lokale Entwicklungsumgebung Setup"
echo "=================================================="
echo ""

# Farben für Output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Prüfen ob Docker läuft
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker läuft nicht. Bitte Docker Desktop starten."
    exit 1
fi

echo -e "${BLUE}[1/7]${NC} Docker Container werden gestartet..."
docker-compose down 2>/dev/null || true
docker-compose up -d

echo -e "${BLUE}[2/7]${NC} Warte auf MySQL (10 Sekunden)..."
sleep 10

echo -e "${BLUE}[3/7]${NC} Composer Dependencies werden installiert..."
docker-compose exec -T web composer install --no-interaction

echo -e "${BLUE}[4/7]${NC} Datenbank wird erstellt..."
docker-compose exec -T web php bin/console doctrine:database:create --if-not-exists

echo -e "${BLUE}[5/7]${NC} Datenbank-Schema wird angelegt..."
docker-compose exec -T web php bin/console doctrine:schema:update --force

echo -e "${BLUE}[6/7]${NC} Demo-Daten werden geladen..."
docker-compose exec -T web php bin/console doctrine:fixtures:load --group=demo-data --no-interaction

echo -e "${BLUE}[7/7]${NC} Cache wird geleert..."
docker-compose exec -T web php bin/console cache:clear

echo ""
echo -e "${GREEN}✅ Setup abgeschlossen!${NC}"
echo ""
echo "🌐 Anwendung: http://127.0.0.1:8000"
echo "🌐 Anwendung: http://web.homeadmin24.orb.local/ (wenn DNS konfiguriert)"
echo ""
echo "🔐 Demo-Login:"
echo "   E-Mail:   wegadmin@demo.local"
echo "   Passwort: ChangeMe123!"
echo ""
echo "📊 Demo-Daten umfassen:"
echo "   • 3 WEG (Musterhausen, Berlin, Hamburg)"
echo "   • 12 Wohneinheiten mit Eigentümern"
echo "   • 145 Zahlungen"
echo "   • 8 Dienstleister"
echo "   • 22 Rechnungen"
echo ""
echo "🔧 Nützliche Befehle:"
echo "   docker-compose logs -f web          # Logs anzeigen"
echo "   docker-compose exec web bash        # Shell öffnen"
echo "   docker-compose down                 # Container stoppen"
echo ""
