<?php

/*
 * Site public (refonte V2) : boutiques Stores & Rideaux.
 * Électroménager et Meubles restent affichés comme « bientôt disponibles ».
 * Le catalogue, les réalisations et les demandes viennent de l'espace pro (même base).
 */
return [
    'contact' => [
        'phones' => [
            ['label' => '07 12 44 93 19', 'tel' => '+2250712449319'],
            ['label' => '07 67 49 58 04', 'tel' => '+2250767495804'],
            ['label' => '01 73 82 63 76', 'tel' => '+2250173826376'],
        ],
        'whatsapp' => 'https://wa.me/2250712449319',
        'email' => 'contact@maisondustoream.com',
        'address' => ['Cocody, Marché Saint Jean', 'Abidjan, Côte d’Ivoire'],
        'maps' => 'https://maps.google.com/?q=Maison+du+Store+AM+Cocody',
    ],

    'communes' => ['Cocody', 'Plateau', 'Marcory', 'Yopougon', 'Treichville', 'Bingerville', 'Abobo', 'Adjamé', 'Koumassi', 'Port-Bouët', 'Attécoubé', 'Anyama', 'Songon', 'Grand-Bassam', 'Autre'],

    // Ordre d'affichage dans la navigation : les deux premières sont ouvertes.
    'shops_order' => ['rideaux', 'stores', 'electro', 'meubles'],

    'shops' => [
        'rideaux' => [
            'open' => true, 'type' => 'rideau',
            'name' => 'Rideaux & voilages', 'short' => 'Rideaux', 'boutique' => 'Stores & Rideaux',
            'line' => 'Confection sur mesure en atelier',
            'featTitle' => 'Confection d’atelier',
            'featText' => 'Chaque rideau est coupé, ourlé et monté à vos mesures, puis posé par nos équipes.',
            'featImage' => 'site/images/realisations/wave-luxe.jpg',
            'hero' => 'Rideaux et voilages confectionnés dans notre atelier d’Abidjan, à vos mesures, posés par nos équipes.',
            'end' => ['Un projet de rideaux ou de voilages ?', 'Un conseiller vient prendre les mesures chez vous et vous présente nos tissus.', 'Demander un devis'],
        ],
        'stores' => [
            'open' => true, 'type' => 'store',
            'name' => 'Stores', 'short' => 'Stores', 'boutique' => 'Stores & Rideaux',
            'line' => 'Intérieurs et extérieurs, sur mesure',
            'featTitle' => 'Prise de mesures à domicile',
            'featText' => 'Un conseiller relève les cotes et vous présente les toiles chez vous.',
            'featImage' => 'site/images/realisations/store-zebra.webp',
            'hero' => 'Le métier d’origine de la maison : des stores intérieurs et extérieurs fabriqués sur mesure et posés chez vous.',
            'end' => ['Un projet de stores ?', 'Un conseiller vient prendre les mesures chez vous et vous présente nos toiles.', 'Demander un devis'],
        ],
        'electro' => [
            'open' => false,
            'name' => 'Électroménager', 'short' => 'Électro', 'boutique' => 'Électroménager',
            'line' => 'Bientôt disponible',
            'soonText' => 'Téléviseurs, climatisation, froid, cuisine et lavage, livrés et installés chez vous. La boutique ouvre bientôt.',
        ],
        'meubles' => [
            'open' => false,
            'name' => 'Meubles', 'short' => 'Meubles', 'boutique' => 'Meubles',
            'line' => 'Bientôt disponible',
            'soonText' => 'Salon, salle à manger, chambre et bureau, livrés et montés chez vous. La boutique ouvre bientôt.',
        ],
    ],

    // Photos de secours des réalisations, utilisées tant que l'espace pro n'a pas de photo pour une entrée.
    'realisations' => [
        ['t' => 'Habillage complet du salon', 's' => 'Rideaux occultants et voilages', 'img' => 'site/images/realisations/habillage-salon.webp'],
        ['t' => 'Rideaux occultants sur mesure', 's' => 'Élégance contemporaine', 'img' => 'site/images/realisations/rideaux-occultants.webp'],
        ['t' => 'Store Zebra sur mesure', 's' => 'Maîtrise de la lumière', 'img' => 'site/images/realisations/store-zebra.webp'],
        ['t' => 'Voilage sur baie vitrée', 's' => 'Lumière naturelle et finition élégante', 'img' => 'site/images/realisations/voilage-baie.webp'],
        ['t' => 'Stores Zebra motorisés', 's' => 'Pour de grandes baies vitrées', 'img' => 'site/images/realisations/zebra-motorises.png'],
        ['t' => 'Rideaux et voilages Wave', 's' => 'Finition haut de gamme', 'img' => 'site/images/realisations/wave-luxe.jpg'],
    ],

    // Informations légales (mentions légales, CGV, confidentialité).
    'legal' => [
        'company' => 'Maison du Store AM SARL',
        'capital' => '1 000 000 F CFA',
        'address' => 'Cocody, Saint-Jean, Marché des Arts, Abidjan, Côte d’Ivoire',
        'rccm' => 'CI-ABJ-03-2026-B13-09023',
        'ncc' => '2604078 L',
        'director' => 'Emile Bah, responsable digital et communication',
        // Numéro de déclaration ARTCI : à renseigner une fois obtenu.
        'artci' => env('MDS_ARTCI_NUMBER'),
        'updated' => '11 novembre 2026',
    ],

    // Identifiant du pixel Meta, chargé seulement avec le consentement « publicité ».
    'meta_pixel' => env('MDS_META_PIXEL', '1407495181585849'),

    // Codes couleur du catalogue de l'espace pro.
    'colors' => [
        'blanc' => ['Blanc', '#f4f1ea'], 'ecru' => ['Écru', '#ece3cf'], 'beige' => ['Beige', '#d8c3a0'],
        'taupe' => ['Taupe', '#8b7d6b'], 'gris' => ['Gris', '#a3a19c'], 'anthracite' => ['Anthracite', '#3a3d40'],
        'bleu' => ['Bleu', '#3d5a80'], 'vert' => ['Vert', '#5f7a5a'], 'bordeaux' => ['Bordeaux', '#6d2433'],
    ],
    'features' => ['occultant' => 'Occultant', 'tamisant' => 'Tamisant', 'motorise' => 'Motorisable', 'decoratif' => 'Décoratif'],
];
