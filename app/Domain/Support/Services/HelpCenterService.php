<?php

declare(strict_types=1);

namespace App\Domain\Support\Services;

use App\Models\HelpArticle;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;

class HelpCenterService
{
    /**
     * @return list<string>
     */
    public function getCategories(string $audience = HelpArticle::AUDIENCE_PUBLIC, ?Tenant $tenant = null): array
    {
        $query = HelpArticle::query()
            ->published()
            ->forAudience($audience);

        if ($tenant) {
            $query->where(function ($q) use ($tenant) {
                $q->where('tenant_id', $tenant->id)->orWhereNull('tenant_id');
            });
        } else {
            $query->whereNull('tenant_id');
        }

        /** @var list<string> */
        return $query->distinct()->pluck('category')->values()->all();
    }

    /**
     * @return Collection<int, HelpArticle>
     */
    public function getArticles(
        string $audience = HelpArticle::AUDIENCE_PUBLIC,
        ?string $category = null,
        ?Tenant $tenant = null
    ): Collection {
        $query = HelpArticle::query()
            ->published()
            ->forAudience($audience);

        if ($tenant) {
            $query->where(function ($q) use ($tenant) {
                $q->where('tenant_id', $tenant->id)->orWhereNull('tenant_id');
            });
        } else {
            $query->whereNull('tenant_id');
        }

        if ($category) {
            $query->byCategory($category);
        }

        return $query->orderBy('sort_order')->get();
    }

    /**
     * @return Collection<int, HelpArticle>
     */
    public function search(
        string $searchQuery,
        string $audience = HelpArticle::AUDIENCE_PUBLIC,
        ?Tenant $tenant = null
    ): Collection {
        $query = HelpArticle::query()
            ->published()
            ->forAudience($audience)
            ->where(function ($q) use ($searchQuery) {
                $q->where('question', 'like', "%{$searchQuery}%")
                    ->orWhere('answer', 'like', "%{$searchQuery}%");
            });

        if ($tenant) {
            $query->where(function ($q) use ($tenant) {
                $q->where('tenant_id', $tenant->id)->orWhereNull('tenant_id');
            });
        } else {
            $query->whereNull('tenant_id');
        }

        return $query->orderBy('sort_order')->take(20)->get();
    }
}
