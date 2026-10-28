<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class OpenApiDocsController extends Controller
{
    public function schema(): JsonResponse
    {
        $schema = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'AffiliateOS Developer REST API',
                'version' => '1.0.0',
                'description' => 'Comprehensive multi-tenant eCommerce & Affiliate REST API. Supports scoped API keys, idempotency guarantees, and outbound HMAC-signed webhooks.',
                'contact' => [
                    'name' => 'AffiliateOS Developer Support',
                    'url' => 'https://affiliate-os.com/docs/api',
                ],
            ],
            'servers' => [
                [
                    'url' => url('/api/v1'),
                    'description' => 'Current Tenant API Server',
                ],
            ],
            'components' => [
                'securitySchemes' => [
                    'ApiKeyAuth' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-API-Key',
                        'description' => 'Bearer token or X-API-Key header (e.g. aff_live_...)',
                    ],
                    'BearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                    ],
                ],
            ],
            'security' => [
                ['ApiKeyAuth' => []],
                ['BearerAuth' => []],
            ],
            'paths' => [
                '/store/products' => [
                    'get' => [
                        'summary' => 'List Store Products',
                        'tags' => ['Merchant - Products'],
                        'parameters' => [
                            ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 20]],
                            ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string']],
                        ],
                        'responses' => [
                            '200' => ['description' => 'List of products with variants and wholesale prices.'],
                        ],
                    ],
                    'post' => [
                        'summary' => 'Create Product',
                        'tags' => ['Merchant - Products'],
                        'parameters' => [
                            ['name' => 'Idempotency-Key', 'in' => 'header', 'required' => true, 'schema' => ['type' => 'string']],
                        ],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['name', 'sku', 'price_minor'],
                                        'properties' => [
                                            'name' => ['type' => 'string'],
                                            'sku' => ['type' => 'string'],
                                            'price_minor' => ['type' => 'integer', 'example' => 35000],
                                            'stock' => ['type' => 'integer', 'example' => 100],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => ['description' => 'Product created successfully.'],
                        ],
                    ],
                ],
                '/store/orders' => [
                    'get' => [
                        'summary' => 'List Store Orders',
                        'tags' => ['Merchant - Orders'],
                        'parameters' => [
                            ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'affiliate_id', 'in' => 'query', 'schema' => ['type' => 'integer']],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Paginated orders cohort.'],
                        ],
                    ],
                ],
                '/store/orders/{id}/transition' => [
                    'post' => [
                        'summary' => 'Transition Order State',
                        'tags' => ['Merchant - Orders'],
                        'parameters' => [
                            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                            ['name' => 'Idempotency-Key', 'in' => 'header', 'required' => true, 'schema' => ['type' => 'string']],
                        ],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['status'],
                                        'properties' => [
                                            'status' => ['type' => 'string', 'example' => 'confirmed'],
                                            'reason' => ['type' => 'string'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Order state updated.'],
                        ],
                    ],
                ],
                '/affiliate/catalog' => [
                    'get' => [
                        'summary' => 'Browse Affiliate Catalog',
                        'tags' => ['Affiliate - Catalog'],
                        'responses' => [
                            '200' => ['description' => 'List of wholesale products with commission bounds.'],
                        ],
                    ],
                ],
                '/affiliate/orders' => [
                    'get' => [
                        'summary' => 'List Affiliate Placed Orders',
                        'tags' => ['Affiliate - Orders'],
                        'responses' => ['200' => ['description' => 'Affiliate orders.']],
                    ],
                    'post' => [
                        'summary' => 'Place Order on Behalf of Customer',
                        'tags' => ['Affiliate - Orders'],
                        'parameters' => [
                            ['name' => 'Idempotency-Key', 'in' => 'header', 'required' => true, 'schema' => ['type' => 'string']],
                        ],
                        'responses' => ['201' => ['description' => 'Order placed successfully.']],
                    ],
                ],
                '/affiliate/wallet' => [
                    'get' => [
                        'summary' => 'Get Affiliate Wallet Balances',
                        'tags' => ['Affiliate - Wallet'],
                        'responses' => ['200' => ['description' => 'Available, pending, and total earnings.']],
                    ],
                ],
            ],
        ];

        return response()->json($schema);
    }

    public function ui(): View
    {
        return view('docs.api', [
            'schemaUrl' => url('/api/v1/openapi.json'),
        ]);
    }
}
