<?php
/**
 * File di Configurazione della Dashboard YSF
 */

return [
    // Parametri Intestazione Principale
    'header' => [
        'title'       => 'description your reflector',
        'sub_title'   => 'YSFReflector Dashboard',
        'reflector_id'=> '123456',
    ],

    // Informazioni di Contatto (Footer & Sysop)
    'sysop' => [
        'email' => 'your mail',
        'name'  => 'your name o callsign',
    ],

    // Pulsanti di Navigazione Personalizzati nell'Header
    'nav_buttons' => [
        [
            'label' => 'Pulsante1',
            'url'   => 'your link button',
            'icon'  => 'fa-solid fa-network-wired',
            'color' => 'emerald',
        ],
        [
            'label' => 'Pulsante2',
            'url'   => 'your link button',
            'icon'  => 'fa-solid fa-walkie-talkie',
            'color' => 'amber',
        ],
    ],

    // Configurazione del Grafico Statistiche Traffico (Doughnut)
    'chart' => [
        'enabled' => true, // Metti a false se vuoi nascondere del tutto il box del grafico
        'items'   => [
            'bridge1' => [
                'label'   => 'Bridge YSF to DMR',
                'keyword' => 'CALLSIGN', // Parola chiave da cercare nei log/gateway
                'color'   => '#3b82f6',    // Colore fetta (Blu)
            ],
            'bridge2' => [
                'label'   => 'Bridge YSF to D-STAR',
                'keyword' => 'XLX123',
                'color'   => '#8b5cf6',    // Colore fetta (Viola)
            ],
            'direct' => [
                'label'   => 'Diretti YSF',
                'keyword' => 'DIRECT',     // Gestito come fallback per gli altri
                'color'   => '#10b981',    // Colore fetta (Verde)
            ],
        ],
    ],
];