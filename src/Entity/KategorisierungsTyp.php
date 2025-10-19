<?php

namespace App\Entity;

enum KategorisierungsTyp: string
{
    public function getDisplayName(): string
    {
        return match ($this) {
            self::UMLAGEFAEHIG_HEIZUNG => 'Umlagefähig - auf Mieter umlegbar (Heizung)',
            self::UMLAGEFAEHIG_WASSER => 'Umlagefähig - auf Mieter umlegbar (Wasser)',
            self::UMLAGEFAEHIG_SONSTIGE => 'Umlagefähig - auf Mieter umlegbar (Sonstige)',
            self::NICHT_UMLAGEFAEHIG => 'Nicht umlagefähig - nur Eigentümer',
            self::RUECKLAGENZUFUEHRUNG => 'Rücklagenzuführung',
        };
    }

    public function isUmlagefaehig(): bool
    {
        return match ($this) {
            self::UMLAGEFAEHIG_HEIZUNG,
            self::UMLAGEFAEHIG_WASSER,
            self::UMLAGEFAEHIG_SONSTIGE => true,
            self::NICHT_UMLAGEFAEHIG,
            self::RUECKLAGENZUFUEHRUNG => false,
        };
    }

    public function getReportSection(): string
    {
        return match ($this) {
            self::UMLAGEFAEHIG_HEIZUNG => 'HEIZUNG/WARMWASSER',
            self::UMLAGEFAEHIG_WASSER => 'WASSER',
            self::UMLAGEFAEHIG_SONSTIGE => 'SONSTIGE UMLAGEFÄHIGE KOSTEN',
            self::NICHT_UMLAGEFAEHIG => 'NICHT UMLAGEFÄHIGE KOSTEN',
            self::RUECKLAGENZUFUEHRUNG => 'RÜCKLAGENZUFÜHRUNG',
        };
    }
    case UMLAGEFAEHIG_HEIZUNG = 'umlagefaehig_heizung';
    case UMLAGEFAEHIG_WASSER = 'umlagefaehig_wasser';
    case UMLAGEFAEHIG_SONSTIGE = 'umlagefaehig_sonstige';
    case NICHT_UMLAGEFAEHIG = 'nicht_umlagefaehig';
    case RUECKLAGENZUFUEHRUNG = 'ruecklagenzufuehrung'; // For reserve contributions
}
