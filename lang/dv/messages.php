<?php

return [
    'payment' => [
        'created' => 'ފައިސާ 成功创建',
        'completed' => 'ފައިސާ 支付完成',
        'failed' => 'ފައިސާ 支付失败',
        'cancelled' => '订单已取消',
        'pending' => '支付待处理',
        'expired' => '付款已过期',
        'not_found' => '未找到付款',
        'invalid_amount' => '付款金额无效',
        'invalid_signature' => '付款签名无效',
    ],
    'status' => [
        'pending' => 'pending',
        'completed' => 'completed',
        'failed' => 'failed',
        'cancelled' => 'cancelled',
        'unknown' => 'unknown',
    ],
    'messages' => [
        'initiate_payment' => '点击"立即支付"跳转到FahiPay',
        'payment_success' => '您的付款成功！',
        'payment_failed' => '您的付款无法完成。请重试。',
        'payment_cancelled' => '您取消了付款流程。',
        'redirecting' => '正在跳转到付款页面...',
        'processing' => '正在处理付款...',
    ],
    'buttons' => [
        'pay_now' => '立即支付',
        'cancel' => '取消',
        'retry' => '重试付款',
        'view_details' => '查看详情',
    ],
    'errors' => [
        'not_configured' => 'FahiPay未配置。请检查您的设置。',
        'api_error' => '处理付款时发生错误。',
        'network_error' => '网络错误。请检查您的连接。',
    ],
    'mail' => [
        'subject_received' => '已收到付款 - :transaction_id',
        'subject_confirmed' => '付款已确认 - :transaction_id',
        'greeting' => '您好！',
        'thank_you' => '感谢您的付款！',
    ],
    'validation' => [
        'amount_required' => '付款金额为必填项',
        'amount_min' => '最小付款金额为 :min',
        'amount_max' => '最大付款金额为 :max',
    ],
];
