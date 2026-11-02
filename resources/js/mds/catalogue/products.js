/* ==========================================================================
   catalogue/products.js
   Uniquement les DONNÉES du catalogue : aucune logique d'interface.
   Ce fichier sera remplacé par la réponse de GET /api/products/ : les autres
   modules lisent les produits via MDS.api.getProducts() (js/utils/api.js).

   Règles du projet : tous les prix valent exactement "Prix" ; aucune image
   réelle (les visuels sont des placeholders).

   Champs d'un produit :
     id, type ("store" | "rideau"), name, category, reference, description,
     features (occultant | tamisant | motorise | decoratif), colors (ids),
     specs [{label, value}], quoteModel (libellé exact du formulaire de devis),
     available, featured, price
   ========================================================================== */
(function (MDS) {
  'use strict';

  MDS.data = MDS.data || {};

  MDS.data.types = [
    { id: 'store', label: 'Store', plural: 'Stores' },
    { id: 'rideau', label: 'Rideau', plural: 'Rideaux' }
  ];

  MDS.data.features = [
    { id: 'occultant', label: 'Occultant' },
    { id: 'tamisant', label: 'Tamisant' },
    { id: 'motorise', label: 'Motorisé' },
    { id: 'decoratif', label: 'Décoratif' }
  ];

  MDS.data.colors = [
    { id: 'blanc', label: 'Blanc', hex: '#FFFFFF', types: ['store', 'rideau'] },
    { id: 'ecru', label: 'Écru', hex: '#EFE8D8', types: ['store', 'rideau'] },
    { id: 'beige', label: 'Beige', hex: '#D9C9AA', types: ['store', 'rideau'] },
    { id: 'taupe', label: 'Taupe', hex: '#A89886', types: ['store', 'rideau'] },
    { id: 'gris', label: 'Gris perle', hex: '#C7C6C2', types: ['store', 'rideau'] },
    { id: 'anthracite', label: 'Anthracite', hex: '#3A3A3A', types: ['store', 'rideau'] },
    { id: 'bleu', label: 'Bleu nuit', hex: '#1F2E4A', types: ['rideau'] },
    { id: 'vert', label: 'Vert sauge', hex: '#8FA08A', types: ['rideau'] },
    { id: 'bordeaux', label: 'Bordeaux', hex: '#6E2530', types: ['rideau'] }
  ];

  MDS.data.products = [
    {
      id: 1, type: 'store', name: 'Store enrouleur', category: 'Stores intérieurs', reference: 'ST-001',
      description: 'Toile enroulée, sobre et facile à vivre, taillée à la mesure de votre fenêtre.',
      features: ['tamisant', 'motorise'], colors: ['blanc', 'ecru', 'beige', 'taupe', 'gris', 'anthracite'],
      specs: [
        { label: 'Type', value: 'Store intérieur à enroulement' },
        { label: 'Matière', value: 'Toile tamisante' },
        { label: 'Commande', value: 'Manuelle ou motorisée' },
        { label: 'Pose', value: 'Murale, plafond ou encastrée' }
      ],
      quoteModel: 'Store enrouleur', available: true, featured: true, price: 'Prix'
    },
    {
      id: 2, type: 'store', name: 'Store bateau', category: 'Stores intérieurs', reference: 'ST-002',
      description: 'Plis élégants qui se relèvent en drapé, pour une allure décorative.',
      features: ['tamisant', 'decoratif'], colors: ['ecru', 'beige', 'taupe', 'gris'],
      specs: [
        { label: 'Type', value: 'Store intérieur à plis' },
        { label: 'Matière', value: 'Tissu décoratif' },
        { label: 'Commande', value: 'Manuelle par cordon' },
        { label: 'Pose', value: 'Murale ou plafond' }
      ],
      quoteModel: 'Store bateau', available: true, featured: false, price: 'Prix'
    },
    {
      id: 3, type: 'store', name: 'Store vénitien', category: 'Stores intérieurs', reference: 'ST-003',
      description: 'Lames orientables pour doser finement la lumière et l\u2019intimité.',
      features: ['tamisant'], colors: ['blanc', 'ecru', 'beige', 'taupe', 'anthracite'],
      specs: [
        { label: 'Type', value: 'Store intérieur à lames' },
        { label: 'Matière', value: 'Lames orientables' },
        { label: 'Commande', value: 'Manuelle par cordon ou tige' },
        { label: 'Pose', value: 'Murale, plafond ou encastrée' }
      ],
      quoteModel: 'Store vénitien', available: true, featured: true, price: 'Prix'
    },
    {
      id: 4, type: 'store', name: 'Store californien', category: 'Stores intérieurs', reference: 'ST-004',
      description: 'Toile alternant bandes tamisantes et bandes opaques, du jour à la nuit.',
      features: ['tamisant', 'motorise'], colors: ['blanc', 'ecru', 'gris', 'anthracite'],
      specs: [
        { label: 'Type', value: 'Store intérieur jour et nuit' },
        { label: 'Matière', value: 'Toile à bandes alternées' },
        { label: 'Commande', value: 'Manuelle ou motorisée' },
        { label: 'Pose', value: 'Murale, plafond ou encastrée' }
      ],
      quoteModel: 'Store californien', available: true, featured: true, price: 'Prix'
    },
    {
      id: 5, type: 'store', name: 'Store vertical', category: 'Stores intérieurs', reference: 'ST-005',
      description: 'Lamelles verticales orientables, idéales pour les grandes baies.',
      features: ['tamisant'], colors: ['blanc', 'ecru', 'gris', 'anthracite'],
      specs: [
        { label: 'Type', value: 'Store intérieur à lamelles verticales' },
        { label: 'Matière', value: 'Lamelles en tissu' },
        { label: 'Commande', value: 'Manuelle par cordon ou tige' },
        { label: 'Pose', value: 'Plafond ou murale' }
      ],
      quoteModel: 'Store vertical', available: true, featured: false, price: 'Prix'
    },
    {
      id: 6, type: 'store', name: 'Store occultant', category: 'Stores intérieurs', reference: 'ST-006',
      description: 'Bloque la lumière pour préserver le sommeil et l\u2019intimité.',
      features: ['occultant', 'motorise'], colors: ['blanc', 'ecru', 'taupe', 'gris', 'anthracite'],
      specs: [
        { label: 'Type', value: 'Store intérieur occultant' },
        { label: 'Matière', value: 'Toile occultante' },
        { label: 'Commande', value: 'Manuelle ou motorisée' },
        { label: 'Pose', value: 'Murale, plafond ou encastrée' }
      ],
      quoteModel: 'Store occultant', available: true, featured: false, price: 'Prix'
    },
    {
      id: 7, type: 'store', name: 'Store screen', category: 'Stores extérieurs', reference: 'ST-007',
      description: 'Toile technique qui filtre le soleil tout en gardant la vue.',
      features: ['tamisant', 'motorise'], colors: ['ecru', 'gris', 'anthracite'],
      specs: [
        { label: 'Type', value: 'Store de protection solaire' },
        { label: 'Matière', value: 'Toile technique screen' },
        { label: 'Commande', value: 'Motorisée' },
        { label: 'Pose', value: 'Murale ou plafond' }
      ],
      quoteModel: 'Store screen', available: false, featured: false, price: 'Prix'
    },
    {
      id: 8, type: 'store', name: 'Store extérieur', category: 'Stores extérieurs', reference: 'ST-008',
      description: 'Protège vos ouvertures du soleil avant qu\u2019il n\u2019atteigne la vitre.',
      features: ['motorise'], colors: ['ecru', 'beige', 'gris', 'anthracite'],
      specs: [
        { label: 'Type', value: 'Store extérieur' },
        { label: 'Matière', value: 'Toile pour l\u2019extérieur' },
        { label: 'Commande', value: 'Manuelle ou motorisée' },
        { label: 'Pose', value: 'Murale ou plafond' }
      ],
      quoteModel: 'Store extérieur', available: true, featured: false, price: 'Prix'
    },
    {
      id: 9, type: 'rideau', name: 'Rideau occultant', category: 'Rideaux', reference: 'RD-001',
      description: 'Tissu dense qui obscurcit la pièce, pour les chambres et les salles de projection.',
      features: ['occultant'], colors: ['ecru', 'taupe', 'gris', 'anthracite', 'bleu', 'bordeaux'],
      specs: [
        { label: 'Type', value: 'Rideau confectionné' },
        { label: 'Matière', value: 'Tissu épais occultant' },
        { label: 'Finition', value: 'Œillets, plis ou pattes' },
        { label: 'Pose', value: 'Tringle ou rail' }
      ],
      quoteModel: 'Rideau occultant', available: true, featured: true, price: 'Prix'
    },
    {
      id: 10, type: 'rideau', name: 'Voilage', category: 'Voilages', reference: 'RD-002',
      description: 'Tissu léger qui filtre la lumière et adoucit la vue sur l\u2019extérieur.',
      features: ['tamisant'], colors: ['blanc', 'ecru', 'beige', 'gris'],
      specs: [
        { label: 'Type', value: 'Voilage confectionné' },
        { label: 'Matière', value: 'Tissu léger' },
        { label: 'Finition', value: 'Œillets, plis ou pattes' },
        { label: 'Pose', value: 'Tringle ou rail' }
      ],
      quoteModel: 'Voilage', available: true, featured: true, price: 'Prix'
    },
    {
      id: 11, type: 'rideau', name: 'Rideau avec doublure', category: 'Rideaux', reference: 'RD-003',
      description: 'Rideau doublé : plus de tenue, plus d\u2019occultation, un tombé soigné.',
      features: ['occultant', 'decoratif'], colors: ['ecru', 'beige', 'taupe', 'bleu', 'vert', 'bordeaux'],
      specs: [
        { label: 'Type', value: 'Rideau confectionné doublé' },
        { label: 'Matière', value: 'Tissu moyen ou épais' },
        { label: 'Doublure', value: 'Simple ou occultante' },
        { label: 'Pose', value: 'Tringle ou rail' }
      ],
      quoteModel: 'Rideau avec doublure', available: true, featured: false, price: 'Prix'
    },
    {
      id: 12, type: 'rideau', name: 'Rideau à œillets', category: 'Rideaux', reference: 'RD-004',
      description: 'Un tombé régulier et simple à manipuler, sur tringle.',
      features: ['decoratif'], colors: ['blanc', 'ecru', 'taupe', 'gris', 'anthracite', 'vert'],
      specs: [
        { label: 'Type', value: 'Rideau à œillets' },
        { label: 'Matière', value: 'Tissu léger ou moyen' },
        { label: 'Finition', value: 'Œillets' },
        { label: 'Pose', value: 'Tringle' }
      ],
      quoteModel: 'Rideau à œillets', available: true, featured: true, price: 'Prix'
    },
    {
      id: 13, type: 'rideau', name: 'Rideau à plis', category: 'Rideaux', reference: 'RD-005',
      description: 'Plis réguliers pour un rendu classique et structuré.',
      features: ['decoratif', 'tamisant'], colors: ['ecru', 'beige', 'taupe', 'vert', 'bleu'],
      specs: [
        { label: 'Type', value: 'Rideau à plis' },
        { label: 'Matière', value: 'Tissu moyen' },
        { label: 'Finition', value: 'Plis simples, doubles ou flamands' },
        { label: 'Pose', value: 'Tringle ou rail' }
      ],
      quoteModel: 'Rideau à plis', available: true, featured: false, price: 'Prix'
    },
    {
      id: 14, type: 'rideau', name: 'Rideau Wave', category: 'Rideaux', reference: 'RD-006',
      description: 'Vagues continues et régulières, sur rail dédié.',
      features: ['decoratif', 'motorise'], colors: ['blanc', 'ecru', 'gris', 'anthracite'],
      specs: [
        { label: 'Type', value: 'Rideau Wave' },
        { label: 'Matière', value: 'Tissu léger ou moyen' },
        { label: 'Finition', value: 'Wave' },
        { label: 'Pose', value: 'Rail Wave' }
      ],
      quoteModel: 'Rideau Wave', available: false, featured: false, price: 'Prix'
    }
  ];
})((window.MDS = window.MDS || {}));
