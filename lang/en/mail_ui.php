<?php

return [
    'common' => [
        'regards' => 'Kind regards,',
        'greeting' => 'Bonjour, :name',
    ],
    'cart' => [
        'greeting' => 'Bonjour, :name.',
        'intro' => 'Your selections are still saved in your cart:',
        'stock' => 'Stock and prices reflect current conditions at checkout.',
        'button' => 'View Cart',
        'reason' => 'You received this email because you enabled cart reminders.',
        'unsubscribe' => 'Stop receiving reminders',
        'subject' => 'Your selections are still waiting at La Maison ZAFIRA',
    ],
    'concierge' => [
        'received' => 'Merci. We have received your private rendez-vous request.',
        'details' => 'Your request details:',
        'service' => 'Service:',
        'date' => 'Preferred date:',
        'time' => 'Time:',
        'notes' => 'Notes:',
        'contact_before' => 'Our Client Advisor will contact you at',
        'contact_after' => 'or by email in less than 24 hours to confirm the schedule.',
        'reply' => 'Please reply to this email if you have any additional needs.',
        'subject' => 'Rendez-vous Confirmation · ZAFIRA Concierge',
    ],
    'newsletter' => [
        'heading' => 'Bonjour, et bienvenue.',
        'thanks' => 'Thank you for joining',
        'editions' => 'Each edition features our latest collections, La Maison experiences and special offers created for you.',
        'visit' => 'We look forward to welcoming you to La Maison.',
        'subject' => 'La Lettre ZAFIRA · Welcome',
    ],
    'order' => [
        'number' => 'Order number:',
        'total' => 'Total:',
        'status' => [
            'paid' => [
                'subject' => 'We have received payment for your order',
                'body' => 'Payment verified. The atelier has begun preparing your order.',
            ],
            'shipped' => [
                'subject' => 'Your order is on its way',
                'body' => 'Your order has been handed over for shipping. The tracking number will appear on the order page once available.',
            ],
            'completed' => [
                'subject' => 'Your order has been received',
                'body' => 'Thank you. If there is an issue, contact the concierge for help and see the return terms on the website.',
            ],
            'cancelled' => [
                'subject' => 'Your order has been cancelled',
                'body' => 'The order has been cancelled and the stock has been restored. Contact the concierge if this is not what you expected.',
            ],
        ],
    ],
    'password_changed' => [
        'subject' => 'Your ZAFIRA Account Password Has Been Updated',
        'changed' => 'The password for your ZAFIRA account was just updated.',
        'warning' => 'If you did not make this change, contact the Maison ZAFIRA Concierge immediately.',
        'action' => 'Sign in to Your Account',
    ],
];
