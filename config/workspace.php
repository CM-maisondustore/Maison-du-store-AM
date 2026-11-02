<?php
return [
    'whatsapp_number' => '+2250712449319',
    'labels' => ['civility' => 'Civilité', 'firstName' => 'Prénom', 'lastName' => 'Nom', 'email' => 'E-mail', 'phone' => 'Téléphone', 'address' => 'Adresse', 'city' => 'Ville', 'model' => 'Modèle', 'mount' => 'Pose', 'orderType' => 'Prestation', 'control' => 'Commande', 'powerSource' => 'Alimentation', 'color' => 'Couleur', 'finish' => 'Finition', 'fabric' => 'Tissu', 'pattern' => 'Motif', 'lining' => 'Doublure', 'hanging' => 'Fixation', 'appointmentType' => 'Type de rendez-vous', 'project' => 'Projet', 'message' => 'Message'],
    'mail_enabled' => (bool) env('COMMERCIAL_MAIL_ENABLED', false),
    'imap_host' => env('IMAP_HOST', 'imap.hostinger.com'),
    'imap_port' => (int) env('IMAP_PORT', 993),
    'issuer' => [
        'name' => env('BILLING_NAME', 'Maison du Store AM'),
        'address' => env('BILLING_ADDRESS', ''),
        'registration' => env('BILLING_REGISTRATION', ''),
        'tax_note' => env('BILLING_TAX_NOTE', ''),
        'payment_terms' => env('BILLING_PAYMENT_TERMS', ''),
        'email' => 'contact@maisondustoream.com',
    ],
];
