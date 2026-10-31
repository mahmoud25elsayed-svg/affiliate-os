<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طلب المنتج - {{ $product->translateAttribute('name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-transparent text-slate-800 p-2 sm:p-4">
    <div class="max-w-lg mx-auto bg-white rounded-3xl border border-slate-200/90 shadow-xl overflow-hidden">

        <!-- Header / Product Badge -->
        <div class="bg-gradient-to-l from-indigo-900 via-indigo-800 to-slate-900 text-white p-5">
            <span class="inline-block px-3 py-1 bg-amber-500/20 text-amber-300 border border-amber-500/30 rounded-full text-[11px] font-bold mb-2">
                ⚡ الدفع نقدًا عند الاستلام مع حق المعاينة
            </span>
            <h1 class="text-base sm:text-lg font-black text-white leading-snug">
                {{ $product->translateAttribute('name') }}
            </h1>
        </div>

        @if($errors->any())
        <div class="m-4 p-3 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl text-xs font-bold space-y-1">
            @foreach($errors->all() as $err)
                <p>• {{ $err }}</p>
            @endforeach
        </div>
        @endif

        <form method="POST" action="{{ route('storefront.embed.store', $product->id) }}" id="embedOrderForm" class="p-6 space-y-4">
            @csrf

            <!-- Hidden Tracking & Attributions -->
            <input type="hidden" name="ref" value="{{ $refCode }}">
            <input type="hidden" name="utm_source" value="{{ $utmSource }}">
            <input type="hidden" name="utm_medium" value="{{ $utmMedium }}">
            <input type="hidden" name="utm_campaign" value="{{ $utmCampaign }}">
            <input type="hidden" name="utm_content" value="{{ $utmContent }}">

            <!-- Variant Selection (if multiple) -->
            @if($product->variants->count() > 1)
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">اختر المقاس / اللون</label>
                <select name="variant_id" id="variantSelect" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs bg-white text-slate-800 font-bold">
                    @foreach($product->variants as $var)
                        <option value="{{ $var->id }}" {{ $firstVariant?->id === $var->id ? 'selected' : '' }}>
                            {{ $var->sku }} (متاح: {{ $var->stock }})
                        </option>
                    @endforeach
                </select>
            </div>
            @else
                <input type="hidden" name="variant_id" id="variantSelect" value="{{ $firstVariant?->id }}">
            @endif

            <!-- Quantity Selector -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">الكمية المطلوبة</label>
                <div class="flex items-center gap-3">
                    <button type="button" onclick="decrementQty()" class="w-10 h-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-black text-base transition flex items-center justify-center">-</button>
                    <input type="number" name="quantity" id="quantityInput" value="1" min="1" max="10" readonly class="w-16 text-center font-black text-sm border-0 bg-transparent text-slate-900">
                    <button type="button" onclick="incrementQty()" class="w-10 h-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-black text-base transition flex items-center justify-center">+</button>
                </div>
            </div>

            <!-- Customer Name -->
            <div>
                <label for="customer_name" class="block text-xs font-bold text-slate-700 mb-1.5">الاسم ثلاثي أو ثنائي بالكامل *</label>
                <input type="text" name="customer_name" id="customer_name" value="{{ old('customer_name') }}" required placeholder="مثال: أحمد محمد علي" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-xs text-slate-800 focus:ring-2 focus:ring-indigo-500">
            </div>

            <!-- Customer Phone -->
            <div>
                <label for="customer_phone" class="block text-xs font-bold text-slate-700 mb-1.5">رقم الموبايل (واتساب) *</label>
                <input type="tel" name="customer_phone" id="customer_phone" value="{{ old('customer_phone') }}" required placeholder="010XXXXXXXX أو 011/012/015" dir="ltr" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-xs text-slate-800 text-left font-mono focus:ring-2 focus:ring-indigo-500">
            </div>

            <!-- Governorate & City -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label for="governorate_id" class="block text-xs font-bold text-slate-700 mb-1.5">المحافظة *</label>
                    <select name="governorate_id" id="governorate_id" required onchange="fetchQuote()" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-xs bg-white text-slate-800">
                        <option value="">اختر المحافظة...</option>
                        @foreach($governorates as $gov)
                            <option value="{{ $gov->id }}" {{ old('governorate_id') == $gov->id ? 'selected' : '' }}>
                                {{ $gov->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="city" class="block text-xs font-bold text-slate-700 mb-1.5">المدينة / المركز *</label>
                    <input type="text" name="city" id="city" value="{{ old('city') }}" required placeholder="مثال: المنصورة / المعادي" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-xs text-slate-800">
                </div>
            </div>

            <!-- Detailed Address -->
            <div>
                <label for="address" class="block text-xs font-bold text-slate-700 mb-1.5">العنوان بالتفصيل *</label>
                <textarea name="address" id="address" rows="2" required placeholder="اسم الشارع، رقم العمارة، رقم الشقة أو علامة مميزة" class="w-full px-4 py-2 rounded-xl border border-slate-300 text-xs text-slate-800">{{ old('address') }}</textarea>
            </div>

            <!-- Order Total Summary Box -->
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80 space-y-2 text-xs">
                <div class="flex items-center justify-between text-slate-600">
                    <span>مصاريف الشحن والتوصيل:</span>
                    <span id="shippingSummaryText" class="font-bold text-slate-800">يُحسب بعد اختيار المحافظة</span>
                </div>
                <div class="flex items-center justify-between pt-2 border-t border-slate-200 font-black text-sm text-slate-900">
                    <span>الإجمالي المستحق عند الاستلام:</span>
                    <span id="totalSummaryText" class="text-indigo-600 font-black text-base">---</span>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" id="submitBtn" class="w-full py-4 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-2xl shadow-lg shadow-emerald-600/30 transition-all hover:-translate-y-0.5 text-sm flex items-center justify-center gap-2">
                <span>تأكيد الطلب الآن</span>
                <span>(الدفع عند الاستلام) ←</span>
            </button>

            <p class="text-[11px] text-center text-slate-400">
                🔒 بياناتك محمية بالكامل وسيتم التواصل معك هاتفياً قبل خروج الشحنة
            </p>
        </form>

    </div>

    <script>
        function incrementQty() {
            const input = document.getElementById('quantityInput');
            let val = parseInt(input.value) || 1;
            if (val < 10) {
                input.value = val + 1;
                fetchQuote();
            }
        }

        function decrementQty() {
            const input = document.getElementById('quantityInput');
            let val = parseInt(input.value) || 1;
            if (val > 1) {
                input.value = val - 1;
                fetchQuote();
            }
        }

        function fetchQuote() {
            const varEl = document.getElementById('variantSelect');
            const govEl = document.getElementById('governorate_id');
            const qtyEl = document.getElementById('quantityInput');

            if (!govEl.value || !varEl.value) return;

            fetch('{{ route("storefront.embed.quote") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    variant_id: varEl.value,
                    governorate_id: govEl.value,
                    quantity: qtyEl.value
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('shippingSummaryText').innerText = data.formatted_shipping + ' {{ \App\Support\Money::symbol() }}';
                    document.getElementById('totalSummaryText').innerText = data.formatted_total + ' {{ \App\Support\Money::symbol() }}';
                }
            })
            .catch(() => {});
        }
    </script>
</body>
</html>

