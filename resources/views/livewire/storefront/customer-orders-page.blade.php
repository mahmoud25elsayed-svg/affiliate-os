<div class="py-10 bg-gray-50 min-h-[75vh]">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">حسابي</h1>
                <p class="text-sm text-gray-500 mt-1">أهلاً بك، {{ Auth::guard('customer')->user()?->full_name }}</p>
            </div>
            <form action="{{ route('storefront.logout') }}" method="POST">
                @csrf
                <button type="submit" class="text-sm text-red-600 hover:text-red-700 font-medium px-4 py-2 rounded-xl bg-red-50 hover:bg-red-100 transition-colors">
                    تسجيل الخروج
                </button>
            </form>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <!-- Sidebar Navigation -->
            <div class="md:col-span-1">
                <nav class="bg-white rounded-2xl shadow-sm border border-gray-100 p-2 space-y-1">
                    <a href="{{ route('storefront.account.orders') }}"
                        class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-semibold bg-emerald-50 text-emerald-800">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        <span>طلباتي</span>
                    </a>
                    <a href="{{ route('storefront.account.addresses') }}"
                        class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors">
                        <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span>عناويني</span>
                    </a>
                    <a href="{{ route('storefront.account.wishlist') }}"
                        class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors">
                        <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                        <span>المفضلة</span>
                    </a>
                    <a href="{{ route('storefront.account.profile') }}"
                        class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors">
                        <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span>الملف الشخصي</span>
                    </a>
                </nav>
            </div>

            <!-- Content Area -->
            <div class="md:col-span-3 space-y-4">
                @if (session('success'))
                    <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm rounded-xl">
                        {{ session('success') }}
                    </div>
                @endif

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                        <h2 class="text-lg font-bold text-gray-900">سجل الطلبات</h2>
                        <span class="text-xs font-semibold px-2.5 py-1 bg-gray-100 text-gray-600 rounded-full">
                            {{ $orders->count() }} طلب
                        </span>
                    </div>

                    @if ($orders->isEmpty())
                        <div class="p-12 text-center">
                            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600">
                                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                </svg>
                            </div>
                            <h3 class="text-base font-semibold text-gray-900 mb-1">لا توجد طلبات سابقة</h3>
                            <p class="text-sm text-gray-500 mb-6">لم تقم بإجراء أي طلبات حتى الآن.</p>
                            <a href="{{ route('storefront.products.index') }}"
                                class="inline-flex items-center px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-sm transition-colors">
                                تصفح المنتجات
                            </a>
                        </div>
                    @else
                        <div class="divide-y divide-gray-100">
                            @foreach ($orders as $order)
                                <div class="p-6 hover:bg-gray-50/50 transition-colors">
                                    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                                        <div>
                                            <span class="text-xs text-gray-400 block mb-0.5">رقم الطلب</span>
                                            <span class="font-mono font-bold text-gray-900">{{ $order->reference }}</span>
                                        </div>
                                        <div>
                                            <span class="text-xs text-gray-400 block mb-0.5">تاريخ الطلب</span>
                                            <span class="text-sm text-gray-600">{{ $order->created_at?->translatedFormat('d F Y') }}</span>
                                        </div>
                                        <div>
                                            <span class="text-xs text-gray-400 block mb-0.5">الإجمالي</span>
                                            <span class="font-bold text-emerald-700 text-base">
                                                {{ number_format(($order->total->value ?? 0) / 100, 2) }} ج.م
                                            </span>
                                        </div>
                                        <div>
                                            @php
                                                $status = $order->status;
                                                $badges = [
                                                    'pending_confirmation' => ['text' => 'قيد المراجعة', 'class' => 'bg-amber-50 text-amber-700 border-amber-200'],
                                                    'confirmed' => ['text' => 'تم التأكيد', 'class' => 'bg-blue-50 text-blue-700 border-blue-200'],
                                                    'in_preparation' => ['text' => 'جاري التجهيز', 'class' => 'bg-indigo-50 text-indigo-700 border-indigo-200'],
                                                    'shipped' => ['text' => 'تم الشحن', 'class' => 'bg-purple-50 text-purple-700 border-purple-200'],
                                                    'delivered' => ['text' => 'تم التسليم', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
                                                    'cancelled' => ['text' => 'ملغي', 'class' => 'bg-red-50 text-red-700 border-red-200'],
                                                ];
                                                $badge = $badges[$status] ?? ['text' => $status, 'class' => 'bg-gray-50 text-gray-700 border-gray-200'];
                                            @endphp
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border {{ $badge['class'] }}">
                                                {{ $badge['text'] }}
                                            </span>
                                        </div>
                                        <div>
                                            <a href="{{ route('storefront.account.orders.show', $order->reference) }}"
                                                class="text-xs font-semibold text-emerald-600 hover:text-emerald-700 px-3 py-1.5 rounded-lg border border-emerald-200 hover:bg-emerald-50 transition-colors">
                                                عرض التفاصيل
                                            </a>
                                        </div>
                                    </div>

                                    <!-- Quick items snippet -->
                                    <div class="text-xs text-gray-500 bg-gray-50 rounded-xl p-3 flex flex-wrap gap-4">
                                        @foreach ($order->lines as $line)
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium text-gray-700">{{ $line->description ?? 'منتج' }}</span>
                                                <span class="text-gray-400">× {{ $line->quantity }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
