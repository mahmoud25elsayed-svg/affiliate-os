<div class="mt-12 pt-10 border-t border-gray-100">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="text-xl font-bold text-gray-900">تقييمات ومراجعات العملاء</h2>
            <p class="text-sm text-gray-500 mt-1">آراء حقيقية من مشترين معتمدين للمنتج</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-2xl font-bold text-gray-900">{{ number_format($summary['average_rating'], 1) }}</span>
            <div class="flex text-amber-400">
                @for ($i = 1; $i <= 5; $i++)
                    <svg class="w-5 h-5 {{ $i <= round($summary['average_rating']) ? 'fill-current' : 'text-gray-200 fill-current' }}" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                @endfor
            </div>
            <span class="text-xs text-gray-400">({{ $summary['reviews_count'] }} تقييم)</span>
        </div>
    </div>

    <!-- Rating Distribution Bars -->
    <div class="bg-gray-50 rounded-2xl p-6 mb-8 max-w-lg">
        <div class="space-y-2">
            @for ($star = 5; $star >= 1; $star--)
                @php
                    $count = $summary['rating_breakdown'][$star] ?? 0;
                    $percent = $summary['reviews_count'] > 0 ? ($count / $summary['reviews_count']) * 100 : 0;
                @endphp
                <div class="flex items-center gap-3 text-xs">
                    <span class="w-12 font-medium text-gray-600">{{ $star }} نجوم</span>
                    <div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                        <div class="h-full bg-amber-400 rounded-full" style="width: {{ $percent }}%"></div>
                    </div>
                    <span class="w-8 text-left text-gray-400 font-mono">{{ $count }}</span>
                </div>
            @endfor
        </div>
    </div>

    <!-- Review Submission Form -->
    <div class="mb-10 bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
        @if ($successMessage)
            <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-sm mb-4">
                {{ $successMessage }}
            </div>
        @endif

        @if ($errorMessage)
            <div class="p-4 bg-red-50 border border-red-200 rounded-xl text-red-800 text-sm mb-4">
                {{ $errorMessage }}
            </div>
        @endif

        @if (! $isLoggedIn)
            <div class="text-center py-4">
                <p class="text-sm text-gray-600 mb-3">هل اشتريت هذا المنتج؟ سجل دخولك لمشاركة تجربتك وتقييم المنتج.</p>
                <a href="{{ route('storefront.login') }}"
                    class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-sm rounded-xl transition-colors">
                    تسجيل الدخول للتقييم
                </a>
            </div>
        @elseif (! $canReview)
            <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl text-amber-800 text-sm flex items-center gap-2">
                <svg class="w-5 h-5 flex-shrink-0 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>التقييم متاح حصرياً للعملاء الذين اشتروا هذا المنتج وتم تأكيد طلبهم.</span>
            </div>
        @else
            <form wire:submit.prevent="submitReview" class="space-y-4">
                <h3 class="font-bold text-sm text-gray-900 mb-2">أضف تقييمك ومراجعتك</h3>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">التقييم بالنجوم</label>
                    <div class="flex items-center gap-2">
                        @for ($s = 1; $s <= 5; $s++)
                            <button type="button" wire:click="$set('rating', {{ $s }})"
                                class="p-1 text-2xl focus:outline-none transition-transform hover:scale-110 {{ $rating >= $s ? 'text-amber-400' : 'text-gray-300' }}">
                                ★
                            </button>
                        @endfor
                        <span class="text-xs text-gray-500 font-medium mr-2">({{ $rating }} من 5)</span>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">عنوان المراجعة (اختياري)</label>
                    <input type="text" wire:model="title" placeholder="مثال: منتج رائع وخامة ممتازة"
                        class="w-full px-3.5 py-2 rounded-xl border border-gray-300 text-sm focus:ring-2 focus:ring-emerald-500" />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">تفاصيل المراجعة <span class="text-red-500">*</span></label>
                    <textarea wire:model="comment" rows="3" placeholder="شارك تجربتك مع تفاصيل المنتج وجودته وسرعة التوصيل..."
                        class="w-full px-3.5 py-2 rounded-xl border border-gray-300 text-sm focus:ring-2 focus:ring-emerald-500"></textarea>
                </div>

                <button type="submit"
                    class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-sm rounded-xl transition-colors shadow-sm">
                    إرسال المراجعة للمراجعة
                </button>
            </form>
        @endif
    </div>

    <!-- Approved Reviews List -->
    @if ($summary['reviews']->isEmpty())
        <div class="text-center py-10 bg-white rounded-2xl border border-gray-100">
            <p class="text-sm text-gray-400">لا توجد مراجعات معتمدة لهذا المنتج حتى الآن. كن أول من يقيّم هذا المنتج!</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($summary['reviews'] as $rev)
                <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-sm text-gray-900">{{ $rev->customer?->full_name ?? 'مشتري معتمد' }}</span>
                            @if ($rev->is_verified_purchase)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    مشتري مؤكد ✓
                                </span>
                            @endif
                        </div>
                        <span class="text-xs text-gray-400">{{ $rev->created_at?->diffForHumans() }}</span>
                    </div>

                    <div class="flex text-amber-400 mb-2">
                        @for ($i = 1; $i <= 5; $i++)
                            <span class="text-sm">{{ $i <= $rev->rating ? '★' : '☆' }}</span>
                        @endfor
                    </div>

                    @if ($rev->title)
                        <h4 class="font-bold text-sm text-gray-900 mb-1">{{ $rev->title }}</h4>
                    @endif

                    <p class="text-xs text-gray-600 leading-relaxed">{{ $rev->comment }}</p>
                </div>
            @endforeach
        </div>
    @endif
</div>
