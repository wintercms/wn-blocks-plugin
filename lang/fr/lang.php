<?php

return [
    'plugin' => [
        'name' => 'Blocks',
        'description' => 'Plugin de gestion de contenu basé sur des blocs pour Winter CMS.',
    ],
    'actions' => [
        'open_url' => [
            'name' => 'Ouvrir l\'URL',
            'description' => 'Ouvrez l\'URL fournie',
            'href' => 'URL',
            'target' => 'Ouvrir l\'URL dans',
            'target_self' => 'Même onget',
            'target_blank' => 'Nouvel onglet',
        ],
    ],
    'blocks' => [
        'button' => [
            'name' => 'Bouton',
            'description' => 'Un bouton cliquable',
        ],
        'button_group' => [
            'name' => 'Groupe de boutons',
            'description' => 'Groupe de boutons cliquables',
            'buttons' => 'Boutons',
            'position_center' => 'Centré',
            'position_left' => 'A gauche',
            'position_right' => 'A droite',
            'position' => 'Position',
            'width_auto' => 'Auto',
            'width_full' => 'complète',
            'width' => 'Largeur',
        ],
        'cards' => [
            'name' => 'Cartes',
            'description' => 'Contenu au format carte',
        ],
        'code' => [
            'name' => 'Code',
            'description' => 'Contenu HTML personnalisé',
        ],
        'columns_two' => [
            'name' => 'Deux colonnes',
            'description' => 'Deux colonnes de contenu',
            'left' => 'Colonne de gauche',
            'right' => 'Colonne de droite',
        ],
        'divider' => [
            'name' => 'Séparateur',
            'description' => 'Ligne de séparation horizontale',
        ],
        'image' => [
            'name' => 'Image',
            'description' => 'Image unique de la médiathèque',
            'alt_text' => 'Description (pour les lecteurs d\'écran) ',
            'size' => [
                'w-full' => 'Complète',
                'w-2/3' => '2/3',
                'w-1/2' => 'Moitié',
                'w-1/3' => '1/3',
                'w-1/4' => '1/4',
            ],
        ],
        'plaintext' => [
            'name' => 'Texte simple',
            'description' => 'Contenu sans mise en forme',
        ],
        'richtext' => [
            'name' => 'Contenu enrichi',
            'description' => 'Contenu avec mise en forme de base',
        ],
        'title' => [
            'name' => 'Titre',
            'description' => 'Texte grand format avec options de taille',
            'size' => [
                'h4' => 'Petit',
                'h3' => 'Moye,',
                'h2' => 'Grand',
            ],
        ],
        'video' => [
            'name' => 'Vidéo',
            'description' => 'Intégrer une vidéo de la médiathèque',
        ],
        'vimeo' => [
            'name' => 'Vimeo',
            'description' => 'Intégrer une vidéo Vimeo',
            'vimeo_id' => 'ID de la vidéo Vimeo',
        ],
        'youtube' => [
            'name' => 'YouTube',
            'description' => 'Intégrer une vidéo YouTube',
            'youtube_id' => 'ID de la vidéo YouTube',
        ],
    ],
    'fields' => [
        'actions_prompt' => 'Ajouter une action',
        'actions' => 'Actions',
        'blocks_prompt' => 'Ajouter un bloc',
        'blocks' => 'Blocs',
        'color' => 'Couleur',
        'content' => 'Contenu',
        'icon' => 'Icône',
        'label' => 'Label/texte',
        'size' => 'Taille',
        'alignment_x' => [
            'label' => 'Alignement horizontal',
            'left' => 'A gauche',
            'center' => 'Au entre',
            'right' => 'A droite',
        ],
    ],
];
