<?php
/**
 * File di Configurazione della Dashboard YSF
 * Modifica i parametri sottostanti per personalizzare l'intestazione,
 * i pulsanti di navigazione e i contatti.
 */

return [
    // Parametri Intestazione Principale
    'header' => [
        'title'       => 'YSF REFLECTOR',
        'sub_title'   => 'YSFReflector Dashboard',
        'reflector_id'=> '222222',
    ],

    // Informazioni di Contatto (Footer & Sysop)
    'sysop' => [
        'email' => 'your_mail',
        'name'  => 'name',
    ],

    // Pulsanti di Navigazione Personalizzati nell'Header
    // Puoi aggiungere, rimuovere o modificare i pulsanti a tuo piacimento.
    'nav_buttons' => [
        [
            'label' => 'Pulsante1',
            'url'   => 'insert your link',
            'icon'  => 'fa-solid fa-network-wired',
            'color' => 'emerald', // emerald, blue, amber, purple, rose, slate
        ],
        [
            'label' => 'Pulsante2',
            'url'   => 'insert your link',
            'icon'  => 'fa-solid fa-walkie-talkie',
            'color' => 'amber',
        ],
        /* Esempio per aggiungere un terzo pulsante:
        [
            'label' => 'QUADNET LIVE',
            'url'   => 'https://www.openquad.net/lhv4.html',
            'icon'  => 'fa-solid fa-globe',
            'color' => 'purple',
        ],
        */
    ],
];