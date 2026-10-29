import http from 'k6/http';
import { check } from 'k6';
import { BASE_URL, JSON_HEADERS } from '../config.js';

export const options = {
    scenarios: {
        affiliate_burst_rate: {
            executor: 'constant-arrival-rate',
            rate: 50,              // Exactly 50 requests / second
            timeUnit: '1s',
            duration: '1m',        // 1-minute sustained burst
            preAllocatedVUs: 50,
            maxVUs: 200,
        },
    },
    thresholds: {
        http_req_duration: ['p(95)<500', 'p(99)<1200'],
        http_req_failed: ['rate<0.02'],
    },
};

export default function () {
    const randomId = Math.floor(Math.random() * 1000000);
    const trackingCode = `aff_trk_${(randomId % 20) + 1}`;

    const payload = JSON.stringify({
        tracking_link_code: trackingCode,
        idempotency_key: `idemp_${Date.now()}_${randomId}`,
        customer: {
            name: `عميل أفلييت ${randomId}`,
            phone: `011${Math.floor(10000000 + Math.random() * 90000000)}`,
            governorate_id: 1,
            city: 'مدينة نصر',
            address: 'شارع عباس العقاد',
        },
        items: [
            {
                variant_id: 1,
                quantity: 1,
                price_minor: 35000,
            },
        ],
        notes: 'طلب تسويق بالعمولة تجريبي فائق التزامن',
    });

    const res = http.post(
        `${BASE_URL}/embed/order/quote`,
        payload,
        { headers: JSON_HEADERS }
    );

    check(res, {
        'quote status is 200 or 404': (r) => r.status === 200 || r.status === 404 || r.status === 422,
        'burst response under 500ms': (r) => r.timings.duration < 500,
    });
}
