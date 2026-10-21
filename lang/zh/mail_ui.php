<?php

return [
    'common' => [
        'regards' => '此致，',
        'greeting' => 'Bonjour, :name',
    ],
    'cart' => [
        'greeting' => 'Bonjour, :name.',
        'intro' => '您挑选的商品仍保存在购物车中：',
        'stock' => '库存和价格以结账时的最新情况为准。',
        'button' => '查看购物车',
        'reason' => '您收到此邮件是因为启用了购物车提醒。',
        'unsubscribe' => '停止接收提醒',
        'subject' => '您的心仪之选仍在 La Maison ZAFIRA 等候',
    ],
    'concierge' => [
        'received' => 'Merci。我们已收到您的私人预约申请。',
        'details' => '您的申请详情：',
        'service' => '服务：',
        'date' => '期望日期：',
        'time' => '时间：',
        'notes' => '备注：',
        'contact_before' => '我们的客户顾问将通过',
        'contact_after' => '或邮件在不到24小时内联系您以确认预约时间。',
        'reply' => '如有其他需求，欢迎回复此邮件。',
        'subject' => '预约确认 · ZAFIRA Concierge',
    ],
    'newsletter' => [
        'heading' => 'Bonjour, et bienvenue.',
        'thanks' => '感谢您加入',
        'editions' => '每期呈现最新系列、La Maison 体验，以及为您精心设计的特别优惠。',
        'visit' => '期待您莅临 La Maison。',
        'subject' => 'La Lettre ZAFIRA · 欢迎',
    ],
    'order' => [
        'number' => '订单号：',
        'total' => '总额：',
        'status' => [
            'paid' => [
                'subject' => '我们已收到您的订单付款',
                'body' => '付款已核实。工坊已开始准备您的订单。',
            ],
            'shipped' => [
                'subject' => '您的订单正在配送中',
                'body' => '订单已交付配送。运单号可用后将显示在订单页面。',
            ],
            'completed' => [
                'subject' => '您的订单已签收',
                'body' => '感谢您。如有问题，请联系礼宾寻求帮助，并查看网站上的退货条款。',
            ],
            'cancelled' => [
                'subject' => '您的订单已取消',
                'body' => '订单已取消，库存已恢复。如与您的预期不符，请联系礼宾服务。',
            ],
        ],
    ],
    'password_changed' => [
        'subject' => '您的 ZAFIRA 账户密码已更新',
        'changed' => '您的 ZAFIRA 账户密码刚刚更新。',
        'warning' => '如果这不是您进行的操作，请立即联系 Maison ZAFIRA Concierge。',
        'action' => '登录账户',
    ],
];
