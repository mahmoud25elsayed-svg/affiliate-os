import http from 'k6/http';
import { check, sleep } from 'k6';
import { BASE_URL, DEFAULT_HEADERS, JSON_HEADERS, COMMON_THRESHOLDS } from '../config.js';

export const options = {
    scenarios: {
        checkout_flow: {
            executor: 'ramping-vus',
            startVUs: 2,
            stages: [
                { duration: '30s', target: 20 },  // 20 concurrent buyers
                { duration: '1m', target: 20 },
                { duration: '15s', target: 0 },
            ],
            gracefulRampDown: '5s',
        },
    },
    thresholds: COMMON_THRESHOLDS,
};

export default function () {
    const productId = Math.floor(Math.random() * 20) + 1;

    // 1. View Product Detail Page
    const prodRes = http.get(`${BASE_URL}/products/${productId}`, { headers: DEFAULT_HEADERS });
    check(prodRes, {
        'product page status valid': (r) => r.status === 200 || r.status === 404,
    });
    sleep(1.5);

    // 2. Add to Cart (Livewire / API endpoint)
    const cartRes = http.post(
        `${BASE_URL}/api/v1/store/orders`,
        JSON.stringify({
            customer: {
                name: 'متسوق تجريبي',
                phone: `010${Math.floor(10000000 + Math.random() * 90000000)}`,
                governorate_id: 1, // Cairo
                address: 'شارع المعز - القاهرة',
            },
            lines: [
                {
                    purchasable_type: 'product_variant',
                    purchasable_id: productId,
                    quantity: 1,
                    unit_price_minor: 25000,
                },
            ],
            payment_method: 'cod',
        }),
        { headers: JSON_HEADERS }
    );

    check(cartRes, {
        'order creation responded': (r) => r.status === 200 || r.status === 201 || r.status === 422,
        'order creation under 800ms': (r) => r.timings.duration < 800,
    });
    sleep(2);
}
