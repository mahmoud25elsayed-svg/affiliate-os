/**
 * Shared k6 Load Testing Configuration for AffiliateOS
 */

export const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
export const TENANT_DOMAIN = __ENV.TENANT_DOMAIN || 'cairo-store.affiliate-os.test';

export const DEFAULT_HEADERS = {
    'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
    'Accept-Language': 'ar,en-US;q=0.7,en;q=0.3',
    'User-Agent': 'k6-load-testing-agent/1.0',
    'Host': TENANT_DOMAIN,
};

export const JSON_HEADERS = {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'User-Agent': 'k6-load-testing-agent/1.0',
    'Host': TENANT_DOMAIN,
};

export const COMMON_THRESHOLDS = {
    // 95% of requests must complete below 500ms
    http_req_duration: ['p(95)<500', 'p(99)<1000'],
    // Error rate must stay below 1%
    http_req_failed: ['rate<0.01'],
};
