import http from 'k6/http';
import { check, sleep } from 'k6';
import { BASE_URL, DEFAULT_HEADERS, COMMON_THRESHOLDS } from '../config.js';

export const options = {
    scenarios: {
        storefront_browsers: {
            executor: 'ramping-vus',
            startVUs: 5,
            stages: [
                { duration: '30s', target: 50 },  // Ramp-up to 50 concurrent shoppers
                { duration: '1m', target: 50 },   // Steady load
                { duration: '15s', target: 0 },   // Ramp-down
            ],
            gracefulRampDown: '5s',
        },
    },
    thresholds: COMMON_THRESHOLDS,
};

const ARABIC_SEARCH_TERMS = ['قميص', 'حذاء', 'ساعة', 'حقيبة', 'سماعات', 'عطر'];

export default function () {
    // 1. Visit Storefront Homepage (SSR + Caching)
    const homeRes = http.get(`${BASE_URL}/`, { headers: DEFAULT_HEADERS });
    check(homeRes, {
        'homepage status is 200': (r) => r.status === 200,
        'homepage loaded under 500ms': (r) => r.timings.duration < 500,
    });
    sleep(1 + Math.random() * 2);

    // 2. View Product Categories
    const categoryId = Math.floor(Math.random() * 5) + 1;
    const catRes = http.get(`${BASE_URL}/categories/${categoryId}`, { headers: DEFAULT_HEADERS });
    check(catRes, {
        'category status is 200 or 404': (r) => r.status === 200 || r.status === 404,
        'category duration under 500ms': (r) => r.timings.duration < 500,
    });
    sleep(1);

    // 3. Search Products using Arabic Terms
    const term = ARABIC_SEARCH_TERMS[Math.floor(Math.random() * ARABIC_SEARCH_TERMS.length)];
    const searchRes = http.get(`${BASE_URL}/search?q=${encodeURIComponent(term)}`, { headers: DEFAULT_HEADERS });
    check(searchRes, {
        'search status is 200': (r) => r.status === 200,
        'search duration under 500ms': (r) => r.timings.duration < 500,
    });
    sleep(1.5);
}
