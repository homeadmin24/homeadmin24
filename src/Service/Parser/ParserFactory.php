<?php

namespace App\Service\Parser;

use App\Entity\Dienstleister;

class ParserFactory
{
    private ?string $projectDir = null;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    /**
     * Create appropriate parser for given Dienstleister.
     */
    public function createParser(Dienstleister $dienstleister): ParserInterface
    {
        // Check if custom parser class is specified
        if ($dienstleister->getParserClass()) {
            $parserClass = $dienstleister->getParserClass();

            // Ensure class exists and implements ParserInterface
            if (!class_exists($parserClass)) {
                throw new \InvalidArgumentException("Parser class not found: $parserClass");
            }

            if (!\in_array(ParserInterface::class, class_implements($parserClass), true)) {
                throw new \InvalidArgumentException("Parser class must implement ParserInterface: $parserClass");
            }

            return new $parserClass($this->projectDir);
        }

        // Check for hardcoded parsers by Dienstleister name
        $bezeichnung = $dienstleister->getBezeichnung();
        if (false !== mb_stripos($bezeichnung, 'maaß') || false !== mb_stripos($bezeichnung, 'maass')) {
            return new MaassParser($this->projectDir);
        }

        // Default to generic regex parser with configuration
        return new GenericRegexParser($dienstleister, $this->projectDir);
    }
}
