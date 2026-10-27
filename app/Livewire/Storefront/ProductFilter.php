<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;
use Lunar\Models\Brand;
use Lunar\Models\Collection;
use Lunar\Models\Price;
use Lunar\Models\Product;

class ProductFilter extends Component
{
    use WithPagination;

    public ?int $categoryId = null;

    public ?int $brandId = null;

    public ?int $minPrice = null;

    public ?int $maxPrice = null;

    public string $sort = 'latest';

    public function updatingCategoryId(): void
    {
        $this->resetPage();
    }

    public function updatingBrandId(): void
    {
        $this->resetPage();
    }

    public function updatingMinPrice(): void
    {
        $this->resetPage();
    }

    public function updatingMaxPrice(): void
    {
        $this->resetPage();
    }

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['brandId', 'minPrice', 'maxPrice', 'sort']);
        $this->resetPage();
    }

    public function render(): View
    {
        $query = Product::query()
            ->with(['variants.prices', 'variants.wholesalePrice', 'brand', 'collections', 'media'])
            ->where('status', 'published');

        if ($this->categoryId) {
            $query->whereHas('collections', function (Builder $q) {
                $q->where('lunar_collections.id', $this->categoryId);
            });
        }

        if ($this->brandId) {
            $query->where('brand_id', $this->brandId);
        }

        if ($this->minPrice !== null && $this->minPrice > 0) {
            $minMinor = $this->minPrice * 100;
            $query->whereHas('variants.prices', function (Builder $pq) use ($minMinor) {
                $pq->where('price', '>=', $minMinor);
            });
        }

        if ($this->maxPrice !== null && $this->maxPrice > 0) {
            $maxMinor = $this->maxPrice * 100;
            $query->whereHas('variants.prices', function (Builder $pq) use ($maxMinor) {
                $pq->where('price', '<=', $maxMinor);
            });
        }

        match ($this->sort) {
            'price_asc' => $query->orderBy(
                Price::select('price')
                    ->whereColumn('priceable_id', 'lunar_products.id')
                    ->take(1),
                'asc'
            ),
            'price_desc' => $query->orderBy(
                Price::select('price')
                    ->whereColumn('priceable_id', 'lunar_products.id')
                    ->take(1),
                'desc'
            ),
            default => $query->latest('id'),
        };

        $products = $query->paginate(12);

        $categories = Collection::withCount('products')->get();
        $brands = Brand::all();

        return view('livewire.storefront.product-filter', [
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
        ]);
    }
}
