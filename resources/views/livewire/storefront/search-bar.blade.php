<div class="relative w-full">
    <form wire:submit="search" class="relative">
        <input
            type="text"
            wire:model.live.debounce.300ms="query"
            placeholder="ابحث عن منتج، ماركة، أو كود..."
            class="w-full rounded-full border border-gray-200 bg-gray-50/80 py-2.5 pr-11 pl-4 text-sm text-gray-900 focus:bg-white focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 transition-all placeholder:text-gray-400"
            autocomplete="off"
        >
        <button type="submit" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-brand-600 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </button>
    </form>

    <!-- Search Suggestions Dropdown -->
    @if($isOpen && $results->isNotEmpty())
        <div class="absolute z-50 mt-2 w-full bg-white rounded-2xl shadow-xl border border-gray-100 py-2 divide-y divide-gray-50 overflow-hidden">
            @foreach($results as $product)
                @php
                    $firstVariant = $product->variants->first();
                    $retailPrice = $firstVariant?->prices->first();
                @endphp
                <a href="{{ url('/products/'.$product->id) }}" class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 transition-colors">
                    <div class="w-10 h-10 rounded-lg bg-gray-100 flex-shrink-0 overflow-hidden flex items-center justify-center">
                        @if($product->thumbnail)
                            <img src="{{ $product->thumbnail->getUrl() }}" class="w-full h-full object-cover">
                        @else
                            <span class="text-xs text-gray-400">📦</span>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <h4 class="text-xs font-bold text-gray-900 truncate">{{ $product->translateAttribute('name') }}</h4>
                        <span class="text-[10px] text-gray-400">{{ $product->brand?->name ?? 'متجرنا' }}</span>
                    </div>
                    <span class="text-xs font-bold text-brand-600">
                        {{ $retailPrice ? $retailPrice->price->formatted() : '' }}
                    </span>
                </a>
            @endforeach
            <div class="px-4 py-2 bg-gray-50 text-center">
                <button type="button" wire:click="search" class="text-xs font-bold text-brand-600 hover:underline">
                    عرض جميع نتائج البحث عن "{{ $query }}" &larr;
                </button>
            </div>
        </div>
    @endif
</div>
