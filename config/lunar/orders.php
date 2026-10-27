<?php

use Lunar\Base\OrderReferenceGenerator;
use Lunar\Pipelines\Order\Creation\CleanUpOrderLines;
use Lunar\Pipelines\Order\Creation\CreateOrderAddresses;
use Lunar\Pipelines\Order\Creation\CreateOrderLines;
use Lunar\Pipelines\Order\Creation\CreateShippingLine;
use Lunar\Pipelines\Order\Creation\FillOrderFromCart;
use Lunar\Pipelines\Order\Creation\MapDiscountBreakdown;

return [
    /*
    |--------------------------------------------------------------------------
    | Order Reference Format
    |--------------------------------------------------------------------------
    |
    | Specify the format for the order reference generator to use.
    |
    */
    'reference_format' => [
        /**
         * Optional prefix for the order reference
         */
        'prefix' => null,

        /**
         * STR_PAD_LEFT: 00001965
         * STR_PAD_RIGHT: 19650000
         * STR_PAD_BOTH: 00196500
         */
        'padding_direction' => STR_PAD_LEFT,

        /**
         * 00001965
         * AAAA1965
         */
        'padding_character' => '0',

        /**
         * If the length specified below is smaller than the length
         * of the Order ID, then no padding will take place.
         */
        'length' => 8,
    ],

    /*
    |--------------------------------------------------------------------------
    | Order Reference Generator
    |--------------------------------------------------------------------------
    |
    | Here you can specify how you want your order references to be generated
    | when you create an order from a cart.
    |
    */
    'reference_generator' => OrderReferenceGenerator::class,

    /*
    |--------------------------------------------------------------------------
    | Draft Status
    |--------------------------------------------------------------------------
    |
    | When a draft order is created from a cart, we need an initial status for
    | the order that's created. Define that here, it can be anything that would
    | make sense for the store you're building.
    |
    */
    'draft_status' => 'awaiting-payment',

    'statuses' => [

        'submitted' => [
            'label' => 'Submitted',
            'color' => '#6b7280',
            'mailers' => [],
            'notifications' => [],
            'favourite' => true,
        ],

        'rejected_quality' => [
            'label' => 'Quality Rejected',
            'color' => '#ef4444',
            'mailers' => [],
            'notifications' => [],
            'favourite' => false,
        ],

        'pending_confirmation' => [
            'label' => 'Pending Confirmation',
            'color' => '#f59e0b',
            'mailers' => [],
            'notifications' => [],
            'favourite' => true,
        ],

        'confirmed' => [
            'label' => 'Confirmed',
            'color' => '#3b82f6',
            'mailers' => [],
            'notifications' => [],
            'favourite' => true,
        ],

        'unreachable' => [
            'label' => 'Unreachable',
            'color' => '#f97316',
            'mailers' => [],
            'notifications' => [],
            'favourite' => false,
        ],

        'cancelled_by_customer' => [
            'label' => 'Cancelled by Customer',
            'color' => '#dc2626',
            'mailers' => [],
            'notifications' => [],
            'favourite' => false,
        ],

        'cancelled_no_answer' => [
            'label' => 'Cancelled (No Answer)',
            'color' => '#b91c1c',
            'mailers' => [],
            'notifications' => [],
            'favourite' => false,
        ],

        'reserved' => [
            'label' => 'Stock Reserved',
            'color' => '#06b6d4',
            'mailers' => [],
            'notifications' => [],
            'favourite' => true,
        ],

        'out_of_stock' => [
            'label' => 'Out of Stock',
            'color' => '#e11d48',
            'mailers' => [],
            'notifications' => [],
            'favourite' => false,
        ],

        'packed' => [
            'label' => 'Packed',
            'color' => '#8b5cf6',
            'mailers' => [],
            'notifications' => [],
            'favourite' => true,
        ],

        'shipped' => [
            'label' => 'Shipped',
            'color' => '#6366f1',
            'mailers' => [],
            'notifications' => [],
            'favourite' => true,
        ],

        'out_for_delivery' => [
            'label' => 'Out for Delivery',
            'color' => '#4f46e5',
            'mailers' => [],
            'notifications' => [],
            'favourite' => true,
        ],

        'delivered' => [
            'label' => 'Delivered',
            'color' => '#10b981',
            'mailers' => [],
            'notifications' => [],
            'favourite' => true,
        ],

        'delivery_failed' => [
            'label' => 'Delivery Failed',
            'color' => '#f43f5e',
            'mailers' => [],
            'notifications' => [],
            'favourite' => false,
        ],

        'returning' => [
            'label' => 'Returning',
            'color' => '#ea580c',
            'mailers' => [],
            'notifications' => [],
            'favourite' => false,
        ],

        'return_requested' => [
            'label' => 'Return Requested',
            'color' => '#d97706',
            'mailers' => [],
            'notifications' => [],
            'favourite' => false,
        ],

        'returned' => [
            'label' => 'Returned',
            'color' => '#64748b',
            'mailers' => [],
            'notifications' => [],
            'favourite' => false,
        ],

        'settled' => [
            'label' => 'Settled',
            'color' => '#059669',
            'mailers' => [],
            'notifications' => [],
            'favourite' => true,
        ],

        'awaiting-payment' => [
            'label' => 'Awaiting Payment',
            'color' => '#848a8c',
            'mailers' => [],
            'notifications' => [],
            'favourite' => false,
        ],

        'cod-confirmed' => [
            'label' => 'COD Confirmed',
            'color' => '#10B981',
            'mailers' => [],
            'notifications' => [],
            'favourite' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Order Pipelines
    |--------------------------------------------------------------------------
    |
    | Define which pipelines should be run throughout an order's lifecycle.
    | The default ones provided should suit most needs, however you are
    | free to add your own as you see fit.
    |
    | Each pipeline class will be run from top to bottom.
    |
    */
    'pipelines' => [
        'creation' => [
            FillOrderFromCart::class,
            CreateOrderLines::class,
            CreateOrderAddresses::class,
            CreateShippingLine::class,
            CleanUpOrderLines::class,
            MapDiscountBreakdown::class,
        ],
    ],

];
