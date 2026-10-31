<div>
    <button type="button" wire:click="toggle"
        class="p-2.5 rounded-xl border {{ $isWishlisted ? 'border-red-200 bg-red-50 text-red-500' : 'border-gray-200 hover:border-gray-300 text-gray-500 hover:text-gray-700 bg-white' }} shadow-sm transition-all flex items-center justify-center gap-1.5 text-xs font-semibold"
        title="{{ $isWishlisted ? __('إزالة من المفضلة') : __('إضافة للمفضلة') }}">
        <svg class="w-5 h-5 {{ $isWishlisted ? 'fill-current' : 'fill-none' }}" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
        </svg>
        <span>{{ $isWishlisted ? __('في المفضلة') : __('حفظ في المفضلة') }}</span>
    </button>
</div>
