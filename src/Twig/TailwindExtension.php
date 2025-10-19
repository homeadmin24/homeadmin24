<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class TailwindExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('tailwind_merge', [$this, 'tailwindMerge']),
        ];
    }

    /**
     * Simple implementation of tailwind class merging
     * In a production environment, you might want to use a more robust solution.
     */
    public function tailwindMerge(string $classes): string
    {
        // Remove duplicate classes while preserving order
        $classesArray = array_filter(explode(' ', $classes));
        $uniqueClasses = [];

        // Track which utility prefixes have been used
        $utilityGroups = [];

        foreach ($classesArray as $class) {
            // Extract prefix (like 'text-', 'bg-', etc.)
            $prefix = $this->getUtilityPrefix($class);

            if ($prefix) {
                // If this utility prefix already exists, remove the old class
                if (isset($utilityGroups[$prefix])) {
                    unset($uniqueClasses[$utilityGroups[$prefix]]);
                }

                // Store the position for this utility prefix
                $utilityGroups[$prefix] = $class;
            }

            // Add the class
            $uniqueClasses[$class] = $class;
        }

        return implode(' ', $uniqueClasses);
    }

    /**
     * Extract the Tailwind utility prefix from a class.
     */
    private function getUtilityPrefix(string $class): ?string
    {
        // Common Tailwind utility prefixes
        $prefixes = [
            'text-', 'bg-', 'border-', 'rounded-', 'p-', 'px-', 'py-',
            'm-', 'mx-', 'my-', 'mt-', 'mr-', 'mb-', 'ml-', 'flex-',
            'grid-', 'w-', 'h-', 'max-w-', 'max-h-', 'min-w-', 'min-h-',
            'font-', 'tracking-', 'leading-', 'outline-', 'shadow-',
            'focus:', 'hover:', 'dark:', 'sm:', 'md:', 'lg:', 'xl:',
        ];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($class, $prefix)) {
                return $prefix;
            }
        }

        return null;
    }
}
