import http from 'k6/http';
import { check, sleep } from 'k6';
import { BASE_URL, DEFAULT_HEADERS, JSON_HEADERS } from '../config.js';

export const options = {
    scenarios: {
        call_center_agents: {
            executor: 'constant-vus',
            vus: 20,              // Exactly 20 concurrent call center agents
            duration: '1m',
        },
    },
    thresholds: {
        http_req_duration: ['p(95)<400'],
        http_req_failed: ['rate<0.01'],
    },
};

export default function () {
    // 1. Agent queries confirmation queue
    const queueRes = http.get(
        `${BASE_URL}/store/call-center/queue?status=pending_confirmation`,
        { headers: DEFAULT_HEADERS }
    );

    check(queueRes, {
        'queue page responded': (r) => r.status === 200 || r.status === 302,
        'queue query under 400ms': (r) => r.timings.duration < 400,
    });
    sleep(2);

    // 2. Agent updates order outcome (confirm or call attempt)
    const orderId = Math.floor(Math.random() * 50) + 1;
    const outcomeRes = http.post(
        `${BASE_URL}/store/call-center/orders/${orderId}/attempt`,
        JSON.stringify({
            status: 'no_answer',
            notes: 'تم الاتصال بالعميل ولم يرد، إعادة المحاولة لاحقاً',
        }),
        { headers: JSON_HEADERS }
    );

    check(outcomeRes, {
        'attempt recording completed': (r) => r.status === 200 || r.status === 302 || r.status === 404,
    });
    sleep(3);
}
