<?php

return [
    'common' => [
        'regards' => 'Cordiali saluti,',
        'greeting' => 'Bonjour, :name',
    ],
    'cart' => [
        'greeting' => 'Bonjour, :name.',
        'intro' => 'Le tue scelte sono ancora salvate nel carrello:',
        'stock' => 'Disponibilità e prezzi sono quelli aggiornati al momento del checkout.',
        'button' => 'Visualizza carrello',
        'reason' => 'Ricevi questa email perché hai attivato i promemoria del carrello.',
        'unsubscribe' => 'Non ricevere più promemoria',
        'subject' => 'Le tue selezioni ti aspettano ancora a La Maison ZAFIRA',
    ],
    'concierge' => [
        'received' => 'Merci. Abbiamo ricevuto la richiesta di rendez-vous privato.',
        'details' => 'Dettagli della richiesta:',
        'service' => 'Servizio:',
        'date' => 'Data preferita:',
        'time' => 'Orario:',
        'notes' => 'Note:',
        'contact_before' => 'Il nostro Client Advisor ti contatterà al',
        'contact_after' => 'o via email entro 24 ore per confermare l’appuntamento.',
        'reply' => 'Rispondi a questa email per qualsiasi ulteriore esigenza.',
        'subject' => 'Conferma del rendez-vous · ZAFIRA Concierge',
    ],
    'newsletter' => [
        'heading' => 'Bonjour, et bienvenue.',
        'thanks' => 'Grazie per esserti iscritto a',
        'editions' => 'Ogni edizione presenta le ultime collezioni, le esperienze La Maison e offerte speciali pensate per te.',
        'visit' => 'Ti aspettiamo a La Maison.',
        'subject' => 'La Lettre ZAFIRA · Benvenuto',
    ],
    'order' => [
        'number' => 'Numero d’ordine:',
        'total' => 'Totale:',
        'status' => [
            'paid' => [
                'subject' => 'Abbiamo ricevuto il pagamento del tuo ordine',
                'body' => 'Pagamento verificato. L’atelier ha iniziato a preparare il tuo ordine.',
            ],
            'shipped' => [
                'subject' => 'Il tuo ordine è in spedizione',
                'body' => 'Il tuo ordine è stato affidato al corriere. Il numero di tracciamento apparirà nella pagina dell’ordine non appena disponibile.',
            ],
            'completed' => [
                'subject' => 'Il tuo ordine è stato ricevuto',
                'body' => 'Grazie. In caso di problemi, contattate il concierge e consultate le condizioni di reso sul sito.',
            ],
            'cancelled' => [
                'subject' => 'Il tuo ordine è stato annullato',
                'body' => 'L’ordine è stato annullato e lo stock è stato ripristinato. Contatta il concierge se non corrisponde alle tue aspettative.',
            ],
        ],
    ],
    'password_changed' => [
        'subject' => 'La password del tuo account ZAFIRA è stata aggiornata',
        'changed' => 'La password del tuo account ZAFIRA è stata appena aggiornata.',
        'warning' => 'Se non hai effettuato tu questa modifica, contatta subito il Concierge Maison ZAFIRA.',
        'action' => 'Accedi al tuo account',
    ],
];
