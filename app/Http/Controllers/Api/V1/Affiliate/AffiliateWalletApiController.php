<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\ApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AffiliateWalletApiController extends Controller
{
    protected function resolveAffiliate(Request $request): Affiliate
    {
        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('api_key');

        if ($apiKey && $apiKey->owner instanceof Affiliate) {
            return $apiKey->owner;
        }

        if ($request->has('affiliate_id')) {
            /** @var Affiliate $affiliate */
            $affiliate = Affiliate::findOrFail((int) $request->input('affiliate_id'));

            return $affiliate;
        }

        /** @var Affiliate|null $firstAffiliate */
        $firstAffiliate = Affiliate::first();
        if ($firstAffiliate) {
            return $firstAffiliate;
        }

        abort(403, 'Affiliate identity required.');
    }

    public function show(Request $request): JsonResponse
    {
        $affiliate = $this->resolveAffiliate($request);

        return response()->json([
            'data' => [
                'affiliate_id' => $affiliate->id,
                'name' => $affiliate->name,
                'code' => $affiliate->code,
                'status' => $affiliate->status,
                'available_minor' => $affiliate->getAvailableBalance(),
                'pending_minor' => $affiliate->getPendingBalance(),
                'locked_minor' => $affiliate->getLockedBalance(),
                'withdrawn_minor' => $affiliate->getWithdrawnBalance(),
                'has_debt' => $affiliate->hasDebt(),
                'debt_minor' => $affiliate->getDebtMinor(),
                'currency' => 'EGP',
            ],
        ]);
    }
}
