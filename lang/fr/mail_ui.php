<?php

return [
    'common' => [
        'regards' => 'Cordialement,',
        'greeting' => 'Bonjour, :name',
    ],
    'cart' => [
        'greeting' => 'Bonjour, :name.',
        'intro' => 'Votre sélection est toujours enregistrée dans votre panier :',
        'stock' => 'Les stocks et les prix sont ceux en vigueur lors du paiement.',
        'button' => 'Voir le panier',
        'reason' => 'Vous recevez cet e-mail car vous avez activé les rappels de panier.',
        'unsubscribe' => 'Ne plus recevoir de rappels',
        'subject' => 'Vos sélections vous attendent toujours à La Maison ZAFIRA',
    ],
    'concierge' => [
        'received' => 'Merci. Nous avons reçu votre demande de rendez-vous privé.',
        'details' => 'Détails de votre demande :',
        'service' => 'Service :',
        'date' => 'Date souhaitée :',
        'time' => 'Heure :',
        'notes' => 'Notes :',
        'contact_before' => 'Notre Client Advisor vous contactera au',
        'contact_after' => 'ou par e-mail sous 24 heures pour confirmer le rendez-vous.',
        'reply' => 'N’hésitez pas à répondre à cet e-mail pour tout besoin supplémentaire.',
        'subject' => 'Confirmation de rendez-vous · ZAFIRA Concierge',
    ],
    'newsletter' => [
        'heading' => 'Bonjour, et bienvenue.',
        'thanks' => 'Merci de rejoindre',
        'editions' => 'Chaque édition présente nos dernières collections, les expériences La Maison et des offres spécialement conçues pour vous.',
        'visit' => 'Nous nous réjouissons de vous accueillir à La Maison.',
        'subject' => 'La Lettre ZAFIRA · Bienvenue',
    ],
    'order' => [
        'number' => 'Numéro de commande :',
        'total' => 'Total :',
        'status' => [
            'paid' => [
                'subject' => 'Nous avons reçu le paiement de votre commande',
                'body' => 'Paiement vérifié. L’atelier commence à préparer votre commande.',
            ],
            'shipped' => [
                'subject' => 'Votre commande est en cours de livraison',
                'body' => 'Votre commande a été remise au transporteur. Le numéro de suivi apparaîtra sur la page de la commande dès qu’il sera disponible.',
            ],
            'completed' => [
                'subject' => 'Votre commande a été reçue',
                'body' => 'Merci. En cas de souci, contactez le concierge et consultez les conditions de retour sur le site.',
            ],
            'cancelled' => [
                'subject' => 'Votre commande a été annulée',
                'body' => 'La commande a été annulée et le stock a été rétabli. Contactez le concierge si cela ne correspond pas à vos attentes.',
            ],
        ],
    ],
    'password_changed' => [
        'subject' => 'Le mot de passe de votre compte ZAFIRA a été mis à jour',
        'changed' => 'Le mot de passe de votre compte ZAFIRA vient d’être mis à jour.',
        'warning' => 'Si vous n’êtes pas à l’origine de cette modification, contactez immédiatement le Concierge Maison ZAFIRA.',
        'action' => 'Accéder à mon compte',
    ],
];
