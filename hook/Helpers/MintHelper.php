<?php

namespace Bastivan\UniversalApi\Hook\Helpers;

class MintHelper
{
    public const ORDER = [
        'BasicWorker',
        'AdvancedWorker',
        'RareWorker',
        'EliteWorker',
        'SupremeQueen',
    ];

    public const FUNCTION_ORDER = [
        'Healer',
        'Purifier',
        'Aerator',
        'Collector',
        'Protector',
        'Explorer',
    ];

    // Mapped from EItemExcellence in TS
    public const EXCELLENCE = [
        'BasicWorker' => 'BasicWorker',
        'AdvancedWorker' => 'AdvancedWorker',
        'RareWorker' => 'RareWorker',
        'EliteWorker' => 'EliteWorker',
        'SupremeQueen' => 'SupremeQueen',
    ];

    // Mapped from EDuckFunction in TS
    public const DUCK_FUNCTION = [
        'Healer' => 'Healer',
        'Purifier' => 'Purifier',
        'Aerator' => 'Aerator',
        'Collector' => 'Collector',
        'Protector' => 'Protector',
        'Explorer' => 'Explorer',
        'Scout' => 'Scout',
    ];

    private static function keyPair($a, $b)
    {
        $ai = array_search($a, self::ORDER);
        $bi = array_search($b, self::ORDER);
        return $ai <= $bi ? "$a|$b" : "$b|$a";
    }

    private static function keyPairFunction($a, $b)
    {
        $ai = array_search($a, self::FUNCTION_ORDER);
        if ($ai === false) $ai = 999; // Handle Scout or unknown
        $bi = array_search($b, self::FUNCTION_ORDER);
        if ($bi === false) $bi = 999;
        return $ai <= $bi ? "$a|$b" : "$b|$a";
    }

    private static function D(array $p): array
    {
        return array_merge([
            'BasicWorker' => 0,
            'AdvancedWorker' => 0,
            'RareWorker' => 0,
            'EliteWorker' => 0,
            'SupremeQueen' => 0,
        ], $p);
    }

    private static function F(array $p): array
    {
        return array_merge([
            'Healer' => 0,
            'Purifier' => 0,
            'Aerator' => 0,
            'Collector' => 0,
            'Protector' => 0,
            'Explorer' => 0,
        ], $p);
    }

    private static function getDuckTable(): array
    {
        return [
            'BasicWorker' => self::D([
                'BasicWorker' => 96,
                'AdvancedWorker' => 4,
            ]),
            'AdvancedWorker' => self::D([
                'BasicWorker' => 27,
                'AdvancedWorker' => 70,
                'RareWorker' => 3,
            ]),
            'RareWorker' => self::D([
                'AdvancedWorker' => 27,
                'RareWorker' => 70,
                'EliteWorker' => 3,
            ]),
            'EliteWorker' => self::D([
                'RareWorker' => 27,
                'EliteWorker' => 70,
                'SupremeQueen' => 3,
            ]),
            'SupremeQueen' => self::D([
                'EliteWorker' => 35,
                'SupremeQueen' => 65,
            ]),
        ];
    }

    private static function getBreedTable(): array
    {
        return [
            // Base / *
            self::keyPair('BasicWorker', 'BasicWorker') => self::D(['BasicWorker' => 100]),
            self::keyPair('BasicWorker', 'AdvancedWorker') => self::D(['BasicWorker' => 50, 'AdvancedWorker' => 49, 'RareWorker' => 1]),
            self::keyPair('BasicWorker', 'RareWorker') => self::D(['BasicWorker' => 50, 'RareWorker' => 49, 'EliteWorker' => 1]),
            self::keyPair('BasicWorker', 'EliteWorker') => self::D(['BasicWorker' => 50, 'EliteWorker' => 49, 'SupremeQueen' => 1]),
            self::keyPair('BasicWorker', 'SupremeQueen') => self::D(['BasicWorker' => 50, 'SupremeQueen' => 50]),

            // Advanced / *
            self::keyPair('AdvancedWorker', 'AdvancedWorker') => self::D(['AdvancedWorker' => 98, 'RareWorker' => 2]),
            self::keyPair('AdvancedWorker', 'RareWorker') => self::D(['AdvancedWorker' => 49, 'RareWorker' => 50, 'EliteWorker' => 1]),
            self::keyPair('AdvancedWorker', 'EliteWorker') => self::D(['AdvancedWorker' => 49, 'RareWorker' => 1, 'EliteWorker' => 49, 'SupremeQueen' => 1]),
            self::keyPair('AdvancedWorker', 'SupremeQueen') => self::D(['AdvancedWorker' => 49, 'RareWorker' => 1, 'SupremeQueen' => 50]),

            // Rare / *
            self::keyPair('RareWorker', 'RareWorker') => self::D(['RareWorker' => 98, 'EliteWorker' => 2]),
            self::keyPair('RareWorker', 'EliteWorker') => self::D(['RareWorker' => 49, 'EliteWorker' => 50, 'SupremeQueen' => 1]),
            self::keyPair('RareWorker', 'SupremeQueen') => self::D(['RareWorker' => 49, 'EliteWorker' => 1, 'SupremeQueen' => 50]),

            // Elite / *
            self::keyPair('EliteWorker', 'EliteWorker') => self::D(['EliteWorker' => 98, 'SupremeQueen' => 2]),
            self::keyPair('EliteWorker', 'SupremeQueen') => self::D(['EliteWorker' => 49, 'SupremeQueen' => 51]),

            // Supreme / Supreme
            self::keyPair('SupremeQueen', 'SupremeQueen') => self::D(['SupremeQueen' => 100]),
        ];
    }

    private static function getFunctionTable(): array
    {
        // Replicating FUNCTION_TABLE from TS. Scout handled separately or mapped if present.
        // Assuming Scout logic in keyPairFunction handles order.
        // Scout is not in FUNCTION_ORDER but is in the keys in TS.

        $table = [];
        // Healer / *
        $table[self::keyPairFunction('Healer', 'Healer')] = self::F(['Healer' => 95, 'Purifier' => 1, 'Aerator' => 1, 'Collector' => 1, 'Protector' => 1, 'Explorer' => 1]);
        $table[self::keyPairFunction('Healer', 'Purifier')] = self::F(['Healer' => 6, 'Purifier' => 90, 'Aerator' => 1, 'Collector' => 1, 'Protector' => 1, 'Explorer' => 1]);
        $table[self::keyPairFunction('Healer', 'Aerator')] = self::F(['Healer' => 6, 'Purifier' => 1, 'Aerator' => 90, 'Collector' => 1, 'Protector' => 1, 'Explorer' => 1]);
        $table[self::keyPairFunction('Healer', 'Collector')] = self::F(['Healer' => 6, 'Purifier' => 1, 'Aerator' => 1, 'Collector' => 90, 'Protector' => 1, 'Explorer' => 1]);
        $table[self::keyPairFunction('Healer', 'Protector')] = self::F(['Healer' => 6, 'Purifier' => 1, 'Aerator' => 1, 'Collector' => 1, 'Protector' => 90, 'Explorer' => 1]);
        $table[self::keyPairFunction('Healer', 'Explorer')] = self::F(['Healer' => 6, 'Purifier' => 1, 'Aerator' => 1, 'Collector' => 1, 'Protector' => 1, 'Explorer' => 90]);
        $table[self::keyPairFunction('Healer', 'Scout')] = self::F(['Healer' => 25, 'Purifier' => 15, 'Aerator' => 15, 'Collector' => 15, 'Protector' => 15, 'Explorer' => 15]);

        // Purifier / *
        $table[self::keyPairFunction('Purifier', 'Purifier')] = self::F(['Healer' => 1, 'Purifier' => 79, 'Aerator' => 5, 'Collector' => 5, 'Protector' => 5, 'Explorer' => 5]);
        $table[self::keyPairFunction('Purifier', 'Aerator')] = self::F(['Healer' => 1, 'Purifier' => 45, 'Aerator' => 45, 'Collector' => 3, 'Protector' => 3, 'Explorer' => 3]);
        $table[self::keyPairFunction('Purifier', 'Collector')] = self::F(['Healer' => 1, 'Purifier' => 45, 'Aerator' => 3, 'Collector' => 45, 'Protector' => 3, 'Explorer' => 3]);
        $table[self::keyPairFunction('Purifier', 'Protector')] = self::F(['Healer' => 1, 'Purifier' => 45, 'Aerator' => 3, 'Collector' => 3, 'Protector' => 45, 'Explorer' => 3]);
        $table[self::keyPairFunction('Purifier', 'Explorer')] = self::F(['Healer' => 1, 'Purifier' => 45, 'Aerator' => 3, 'Collector' => 3, 'Protector' => 3, 'Explorer' => 45]);
        $table[self::keyPairFunction('Purifier', 'Scout')] = self::F(['Healer' => 15, 'Purifier' => 25, 'Aerator' => 15, 'Collector' => 15, 'Protector' => 15, 'Explorer' => 15]);

        // Aerator / *
        $table[self::keyPairFunction('Aerator', 'Aerator')] = self::F(['Healer' => 1, 'Purifier' => 5, 'Aerator' => 79, 'Collector' => 5, 'Protector' => 5, 'Explorer' => 5]);
        $table[self::keyPairFunction('Aerator', 'Collector')] = self::F(['Healer' => 1, 'Purifier' => 3, 'Aerator' => 45, 'Collector' => 45, 'Protector' => 3, 'Explorer' => 3]);
        $table[self::keyPairFunction('Aerator', 'Protector')] = self::F(['Healer' => 1, 'Purifier' => 3, 'Aerator' => 45, 'Collector' => 3, 'Protector' => 45, 'Explorer' => 3]);
        $table[self::keyPairFunction('Aerator', 'Explorer')] = self::F(['Healer' => 1, 'Purifier' => 3, 'Aerator' => 45, 'Collector' => 3, 'Protector' => 3, 'Explorer' => 45]);
        $table[self::keyPairFunction('Aerator', 'Scout')] = self::F(['Healer' => 15, 'Purifier' => 15, 'Aerator' => 25, 'Collector' => 15, 'Protector' => 15, 'Explorer' => 15]);

        // Collector / *
        $table[self::keyPairFunction('Collector', 'Collector')] = self::F(['Healer' => 1, 'Purifier' => 5, 'Aerator' => 5, 'Collector' => 79, 'Protector' => 5, 'Explorer' => 5]);
        $table[self::keyPairFunction('Collector', 'Protector')] = self::F(['Healer' => 1, 'Purifier' => 3, 'Aerator' => 3, 'Collector' => 45, 'Protector' => 45, 'Explorer' => 3]);
        $table[self::keyPairFunction('Collector', 'Explorer')] = self::F(['Healer' => 1, 'Purifier' => 3, 'Aerator' => 3, 'Collector' => 45, 'Protector' => 3, 'Explorer' => 45]);
        $table[self::keyPairFunction('Collector', 'Scout')] = self::F(['Healer' => 15, 'Purifier' => 15, 'Aerator' => 15, 'Collector' => 25, 'Protector' => 15, 'Explorer' => 15]);

        // Protector / *
        $table[self::keyPairFunction('Protector', 'Protector')] = self::F(['Healer' => 1, 'Purifier' => 5, 'Aerator' => 5, 'Collector' => 5, 'Protector' => 79, 'Explorer' => 5]);
        $table[self::keyPairFunction('Protector', 'Explorer')] = self::F(['Healer' => 1, 'Purifier' => 3, 'Aerator' => 3, 'Collector' => 3, 'Protector' => 45, 'Explorer' => 45]);
        $table[self::keyPairFunction('Protector', 'Scout')] = self::F(['Healer' => 15, 'Purifier' => 15, 'Aerator' => 15, 'Collector' => 15, 'Protector' => 25, 'Explorer' => 15]);

        // Explorer / Explorer
        $table[self::keyPairFunction('Explorer', 'Explorer')] = self::F(['Healer' => 1, 'Purifier' => 5, 'Aerator' => 5, 'Collector' => 5, 'Protector' => 5, 'Explorer' => 79]);
        $table[self::keyPairFunction('Explorer', 'Scout')] = self::F(['Healer' => 15, 'Purifier' => 15, 'Aerator' => 15, 'Collector' => 15, 'Protector' => 15, 'Explorer' => 25]);

        return $table;
    }

    public static function pickFromDistribution(array $order, array $dist, ?float $rng = null): array
    {
        $roll = ($rng ?? (mt_rand() / mt_getrandmax())) * 100;
        $acc = 0;

        foreach ($order as $k) {
            $acc += $dist[$k] ?? 0;
            if ($roll < $acc) {
                return ['result' => $k, 'roll' => $roll, 'cumulativeAtPick' => $acc, 'values' => $dist];
            }
        }

        $lastKey = end($order);
        return ['result' => $lastKey, 'roll' => $roll, 'cumulativeAtPick' => $acc, 'values' => $dist];
    }

    public static function getDuckDistributionForEgg($excellence): array
    {
        return self::getDuckTable()[$excellence] ?? [];
    }

    public static function getEggDistribution($parentA, $parentB): array
    {
        $dist = self::getBreedTable()[self::keyPair($parentA, $parentB)] ?? null;
        if (!$dist) {
            throw new \Exception("Combinaison non gérée: $parentA x $parentB");
        }
        return $dist;
    }

    public static function rollEggFromParents($parentA, $parentB): array
    {
        $dist = self::getEggDistribution($parentA, $parentB);
        return self::pickFromDistribution(self::ORDER, $dist);
    }

    public static function getFunctionDistribution($a, $b): array
    {
        $dist = self::getFunctionTable()[self::keyPairFunction($a, $b)] ?? null;
        if (!$dist) {
             throw new \Exception("Combinaison non gérée: $a x $b");
        }
        return $dist;
    }

    public static function rollFunctionFromParents($parentA, $parentB): array
    {
        $dist = self::getFunctionDistribution($parentA, $parentB);
        return self::pickFromDistribution(self::FUNCTION_ORDER, $dist);
    }
}
