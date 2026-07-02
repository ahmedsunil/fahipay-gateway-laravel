<?php

return [
    'payment' => [
        'created' => 'Payment created successfully',
        'completed' => 'Payment completed successfully',
        'failed' => 'Payment failed',
        'cancelled' => 'Payment was cancelled',
        'pending' => 'Payment is pending',
        'expired' => 'Payment has expired',
        'not_found' => 'Payment not found',
        'invalid_amount' => 'Invalid payment amount',
        'invalid_signature' => 'Invalid payment signature',
    ],
    'status' => [
        'pending' => 'Pending',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
        'unknown' => 'Unknown',
    ],
    'messages' => [
        'initiate_payment' => 'Click "Pay Now" to be redirected to FahiPay',
        'payment_success' => 'Your payment was successful!',
        'payment_failed' => 'Your payment could not be completed. Please try again.',
        'payment_cancelled' => 'You cancelled the payment process.',
        'redirecting' => 'Redirecting to payment page...',
        'processing' => 'Processing payment...',
    ],
    'buttons' => [
        'pay_now' => 'Pay Now',
        'cancel' => 'Cancel',
        'retry' => 'Retry Payment',
        'view_details' => 'View Details',
    ],
    'errors' => [
        'not_configured' => 'FahiPay is not configured. Please check your settings.',
        'api_error' => 'An error occurred while processing your payment.',
        'network_error' => 'Network error. Please check your connection.',
    ],
    'mail' => [
        'subject_received' => 'Payment Received - :transaction_id',
        'subject_confirmed' => 'Payment Confirmed - :transaction_id',
        'greeting' => 'Hello!',
        'thank_you' => 'Thank you for your payment!',
    ],
    'validation' => [
        'amount_required' => 'Payment amount is required',
        'amount_min' => 'Minimum payment amount is :min',
        'amount_max' => 'Maximum payment amount is :max',
    ],
];
