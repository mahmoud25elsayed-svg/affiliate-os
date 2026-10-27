<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Domain\Storefront\Services\ArabicSearchService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class SearchBar extends Component
{
    public string $query = '';

    public bool $isOpen = false;

    public function search(): void
    {
        if (! empty(trim($this->query))) {
            $this->redirect(url('/search?q='.urlencode($this->query)));
        }
    }

    public function render(ArabicSearchService $searchService): View
    {
        $results = collect();

        if (mb_strlen(trim($this->query)) >= 2) {
            $results = $searchService->search($this->query)
                ->with(['variants.prices', 'media'])
                ->take(5)
                ->get();
            $this->isOpen = true;
        } else {
            $this->isOpen = false;
        }

        return view('livewire.storefront.search-bar', [
            'results' => $results,
        ]);
    }
}
