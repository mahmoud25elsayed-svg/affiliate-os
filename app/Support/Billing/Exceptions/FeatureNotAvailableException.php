<?php

declare(strict_types=1);

namespace App\Support\Billing\Exceptions;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FeatureNotAvailableException extends DomainException
{
    public function __construct(
        public readonly string $feature,
        string $message = ''
    ) {
        if ($message === '') {
            $message = __("الميزة ':feature' غير متاحة في باقتك الحالية. يرجى ترقية الخطة للاستفادة منها.", [
                'feature' => $feature,
            ]);
        }

        parent::__construct($message);
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return new JsonResponse([
                'error' => 'FeatureNotAvailable',
                'feature' => $this->feature,
                'message' => $this->getMessage(),
            ], 403);
        }

        abort(403, $this->getMessage());
    }
}
