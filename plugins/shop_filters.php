<?php
/**
 * Filtry sklepu — zarządzane przez plugins/products/import.php
 * Ostatnia aktualizacja: 2026-07-16 17:44:52
 * ID wartości są STABILNE (append-only) — NIE zmieniaj ręcznie istniejących ID,
 * bo pole sFilter zaimportowanych produktów odwołuje się do nich.
 */

$filters = [
    1 => [
        'key'   => 'brand',
        'label' => 'Marka',
        'values' => [
            22 => 'Carbonado',
            21 => 'Concaver',
        ],
    ],
    2 => [
        'key'   => 'size',
        'label' => 'Rozmiar',
        'values' => [
            18 => '13"',
            17 => '14"',
        ],
    ],
    3 => [
        'key'   => 'width',
        'label' => 'Szerokość',
        'values' => [
            19 => '5.5J',
            21 => '6.0J',
        ],
    ],
    4 => [
        'key'   => 'et',
        'label' => 'Offset ET',
        'values' => [
            13 => '15',
            14 => '20',
            22 => '21',
        ],
    ]

];
