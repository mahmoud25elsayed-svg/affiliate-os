import http from 'k6/http';
import { check, sleep } from 'k6';
import { BASE_URL, JSON_HEADERS } from '../config.js';

export const options = {
    scenarios: {
        courier_webhooks: {
            executor: 'ramping-arrival-rate',
            startRate: 5,
            timeUnit: '1s',
            stages: [
                { duration: '20s', target: 30 },  // 30 webhook payloads / sec
                { duration: '40s', target: 30 },
                { duration: '10s', target: 0 },
            ],
            preAllocatedVUs: 30,
            maxVUs: 100,
        },
    },
    thresholds: {
        http_req_duration: ['p(95)<300'],
        http_req_failed: ['rate<0.01'],
    },
};

const COURIER_STATUSES = ['DELIVERED', 'IN_TRANSIT', 'OUT_FOR_DELIVERY', 'RETURNED'];

export default function () {
    const trackingNum = `TRK_BOSTA_${Math.floor(100000 + Math.random() * 900000)}`;
    const status = COURIER_STATUSES[Math.floor(Math.random() * COURIER_STATUSES.length)];

    const payload = JSON.stringify({
        event: 'delivery.updated',
        data: {
            tracking_number: trackingNum,
            status: status,
            notes: 'تحديث حالة الشحنة تلقائياً من نظام شركة الشحن',
            timestamp: new Date().toISOString(),
        },
    });

    const res = http.post(
        `${BASE_URL}/webhooks/courier/bosta`,
        payload,
        { headers: JSON_HEADERS }
    );

    check(res, {
        'courier webhook processed': (r) => r.status === 200 || r.status === 400 || r.status === 404,
        'webhook response under 300ms': (r) => r.timings.duration < 300,
    });
}
