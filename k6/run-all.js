import { options as browseOptions } from './scenarios/01-browse-storefront.js';
import { options as checkoutOptions } from './scenarios/02-checkout-flow.js';
import { options as burstOptions } from './scenarios/03-affiliate-order-burst.js';
import { options as callCenterOptions } from './scenarios/04-call-center-agents.js';
import { options as courierOptions } from './scenarios/05-courier-webhooks.js';

import browseStorefront from './scenarios/01-browse-storefront.js';
import checkoutFlow from './scenarios/02-checkout-flow.js';
import affiliateOrderBurst from './scenarios/03-affiliate-order-burst.js';
import callCenterAgents from './scenarios/04-call-center-agents.js';
import courierWebhooks from './scenarios/05-courier-webhooks.js';

export const options = {
    scenarios: {
        browsing: browseOptions.scenarios.storefront_browsers,
        checkout: checkoutOptions.scenarios.checkout_flow,
        affiliate_burst: burstOptions.scenarios.affiliate_burst_rate,
        call_center: callCenterOptions.scenarios.call_center_agents,
        courier_sync: courierOptions.scenarios.courier_webhooks,
    },
    thresholds: {
        http_req_duration: ['p(95)<500', 'p(99)<1000'],
        http_req_failed: ['rate<0.02'],
    },
};

export default function () {
    // Delegated to individual scenarios via k6 scenario executor
}

export {
    browseStorefront,
    checkoutFlow,
    affiliateOrderBurst,
    callCenterAgents,
    courierWebhooks,
};
