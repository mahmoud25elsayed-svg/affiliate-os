@extends('layouts.affiliate')

@section('title', 'إنشاء طلب مباشر جديد')

@section('content')
<div class="max-w-4xl mx-auto" x-data="orderForm()">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black text-slate-900">إنشاء طلب مباشر للعميل</h1>
            <p class="text-sm text-slate-500 mt-1">أدخل بيانات العميل وحدد سعر البيع الذي تريده وسيقوم النظام بحساب ربحك والشحن فورياً</p>
        </div>
        <a href="{{ route('affiliate.orders.import-csv.show') }}" class="text-sm font-bold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 px-4 py-2 rounded-xl border border-indigo-200">
            استيراد ملف CSV &larr;
        </a>
    </div>

    <form method="POST" action="{{ route('affiliate.orders.store') }}" class="space-y-6">
        @csrf

        {{-- Step 1: Customer Details --}}
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-4">
            <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center text-sm font-bold">1</span>
                بيانات العميل المستلم
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">اسم العميل بالكامل *</label>
                    <input type="text" name="customer_name" required value="{{ old('customer_name') }}" placeholder="أحمد محمد علي"
                           class="w-full text-sm rounded-xl border-slate-200 py-2.5 focus:ring-indigo-500 focus:border-indigo-500">
                    @error('customer_name') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">{{ __('رقم موبايل العميل') }} *</label>
                    <input type="text" name="customer_phone" required value="{{ old('customer_phone') }}" placeholder="{{ \App\Support\Location\Market::country()->example }}"
                           class="w-full text-sm rounded-xl border-slate-200 py-2.5 focus:ring-indigo-500 focus:border-indigo-500 font-mono">
                    <p class="text-[11px] text-slate-400 mt-1">{{ __('مثال') }}: {{ \App\Support\Location\Market::country()->example }} {{ __('أو بالصيغة الدولية') }} +{{ \App\Support\Location\Market::country()->dial }}</p>
                    @error('customer_phone') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">رقم هاتف بديل (اختياري)</label>
                    <input type="text" name="customer_alt_phone" value="{{ old('customer_alt_phone') }}" placeholder="{{ \App\Support\Location\Market::country()->example }}"
                           class="w-full text-sm rounded-xl border-slate-200 py-2.5 focus:ring-indigo-500 focus:border-indigo-500 font-mono">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">المحافظة *</label>
                    <select name="governorate_id" required x-model="governorateId" @change="calculateQuote()"
                            class="w-full text-sm rounded-xl border-slate-200 py-2.5 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">اختر المحافظة...</option>
                        @foreach($governorates as $gov)
                            <option value="{{ $gov->id }}" {{ (old('governorate_id') == $gov->id || (request('gov') == $gov->id)) ? 'selected' : '' }}>
                                {{ $gov->label() }}
                            </option>
                        @endforeach
                    </select>
                    @error('governorate_id') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">المدينة / المركز / الحي *</label>
                    <input type="text" name="city" required value="{{ old('city') }}" placeholder="مدينة نصر / المعادي"
                           class="w-full text-sm rounded-xl border-slate-200 py-2.5 focus:ring-indigo-500 focus:border-indigo-500">
                    @error('city') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">العنوان بالتفصيل واسم الشارع *</label>
                    <input type="text" name="address" required value="{{ old('address') }}" placeholder="شارع عباس العقاد بجوار مسجد..."
                           class="w-full text-sm rounded-xl border-slate-200 py-2.5 focus:ring-indigo-500 focus:border-indigo-500">
                    <p class="text-[11px] text-slate-400 mt-1">يجب كتابة عنوان واضح ومفصل لا يقل عن 10 أحرف لاجتياز فحص الجودة</p>
                    @error('address') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-3 gap-2 col-span-full">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">رقم العقار</label>
                        <input type="text" name="building_number" value="{{ old('building_number') }}" placeholder="عمارة 15"
                               class="w-full text-sm rounded-xl border-slate-200 py-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">الدور</label>
                        <input type="text" name="floor" value="{{ old('floor') }}" placeholder="الدور 3"
                               class="w-full text-sm rounded-xl border-slate-200 py-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">رقم الشقة</label>
                        <input type="text" name="flat" value="{{ old('flat') }}" placeholder="شقة 6"
                               class="w-full text-sm rounded-xl border-slate-200 py-2 focus:ring-indigo-500">
                    </div>
                </div>

                <div class="col-span-full">
                    <label class="block text-xs font-bold text-slate-700 mb-1">ملاحظات التوصيل لشركة الشحن (اختياري)</label>
                    <input type="text" name="notes" value="{{ old('notes') }}" placeholder="الاتصال قبل الوصول / التسليم خلال ساعات العمل"
                           class="w-full text-sm rounded-xl border-slate-200 py-2.5 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
        </div>

        {{-- Step 2: Product & Pricing --}}
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-4">
            <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center text-sm font-bold">2</span>
                المنتج وتحديد سعر البيع
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="col-span-full md:col-span-1">
                    <label class="block text-xs font-bold text-slate-700 mb-1">اختر المنتج *</label>
                    <select name="variant_id" required x-model="variantId" @change="onVariantChange()"
                            class="w-full text-sm rounded-xl border-slate-200 py-2.5 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">اختر المنتج...</option>
                        @foreach($variants as $v)
                            @php
                                $wh = $v->wholesalePrice;
                                $whPrice = $wh ? number_format($wh->wholesale_price_minor / 100, 2) : 0;
                            @endphp
                            <option value="{{ $v->id }}"
                                    data-wholesale="{{ $wh ? ($wh->wholesale_price_minor / 100) : 0 }}"
                                    data-min="{{ $wh ? ($wh->min_price_minor / 100) : 0 }}"
                                    data-max="{{ $wh ? ($wh->max_price_minor / 100) : 0 }}"
                                    {{ (old('variant_id', $selectedVariant?->id) == $v->id) ? 'selected' : '' }}>
                                {{ $v->product?->translateAttribute('name') ?? 'منتج' }} (جملة: {{ $whPrice }} {{ \App\Support\Money::symbol() }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">الكمية المطلوبة *</label>
                    <input type="number" name="quantity" min="1" max="50" required x-model="quantity" @input="calculateQuote()"
                           class="w-full text-sm rounded-xl border-slate-200 py-2.5 focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">سعر البيع المقترح للقطعة ({{ \App\Support\Money::symbol() }}) *</label>
                    <input type="number" step="1" name="selling_price" required x-model="sellingPrice" @input="calculateQuote()"
                           class="w-full text-sm rounded-xl border-slate-200 py-2.5 focus:ring-indigo-500 focus:border-indigo-500 font-mono font-bold text-indigo-700">
                    <p class="text-[11px] text-slate-400 mt-1" x-text="rangeHint">النطاق المسموح</p>
                </div>
            </div>

            {{-- Live Dynamic Quote Box --}}
            <div class="mt-4 bg-slate-50 border border-slate-200 rounded-2xl p-5" x-show="quoteLoaded">
                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">حسبة الطلب المباشر الفورية (بالجنيه المصري)</h3>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
                    <div class="bg-white p-3 rounded-xl border border-slate-200">
                        <span class="block text-[11px] text-slate-500 mb-1">سعر الجملة (عليك)</span>
                        <span class="text-base font-black text-slate-800 font-mono" x-text="quote.wholesale_price + ' {{ \App\Support\Money::symbol() }}'">0 {{ \App\Support\Money::symbol() }}</span>
                    </div>

                    <div class="bg-white p-3 rounded-xl border border-slate-200">
                        <span class="block text-[11px] text-slate-500 mb-1">الشحن للمحافظة</span>
                        <span class="text-base font-black text-slate-800 font-mono" x-text="quote.shipping_cost + ' {{ \App\Support\Money::symbol() }}'">0 {{ \App\Support\Money::symbol() }}</span>
                    </div>

                    <div class="bg-emerald-50 p-3 rounded-xl border border-emerald-200">
                        <span class="block text-[11px] text-emerald-700 font-bold mb-1">صافي ربحك بالطلب</span>
                        <span class="text-lg font-black text-emerald-600 font-mono" x-text="'+' + quote.affiliate_profit + ' {{ \App\Support\Money::symbol() }}'">0 {{ \App\Support\Money::symbol() }}</span>
                    </div>

                    <div class="bg-indigo-50 p-3 rounded-xl border border-indigo-200">
                        <span class="block text-[11px] text-indigo-700 font-bold mb-1">إجمالي تحصيل العميل</span>
                        <span class="text-lg font-black text-indigo-700 font-mono" x-text="quote.customer_total + ' {{ \App\Support\Money::symbol() }}'">0 {{ \App\Support\Money::symbol() }}</span>
                    </div>
                </div>

                {{-- Warnings --}}
                <template x-if="warnings.length > 0">
                    <div class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-xl space-y-1">
                        <template x-for="w in warnings">
                            <p class="text-xs text-amber-800 font-medium flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-amber-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                <span x-text="w"></span>
                            </p>
                        </template>
                    </div>
                </template>

                <div x-show="quoteError" class="mt-3 p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-700 font-bold" x-text="quoteError"></div>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <a href="{{ route('affiliate.orders.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-800">
                &larr; إلغاء ورجوع للطلبات
            </a>

            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-black text-base py-3 px-8 rounded-xl shadow-lg hover:shadow-indigo-200 transition-all">
                تأكيد وإرسال الطلب الآن
            </button>
        </div>
    </form>
</div>

<script>
function orderForm() {
    return {
        variantId: '{{ old('variant_id', $selectedVariant?->id ?? '') }}',
        governorateId: '{{ old('governorate_id', request('gov', '')) }}',
        quantity: {{ old('quantity', 1) }},
        sellingPrice: {{ old('selling_price', $selectedVariant?->wholesalePrice?->min_price_minor ? ($selectedVariant->wholesalePrice->min_price_minor / 100) : 0) }},
        quote: { wholesale_price: '0', shipping_cost: '0', cod_fee: '0', affiliate_profit: '0', customer_total: '0' },
        warnings: [],
        quoteLoaded: false,
        quoteError: null,
        rangeHint: 'اختر منتجاً لعرض نطاق السعر',

        init() {
            if (this.variantId) {
                this.onVariantChange();
            }
        },

        onVariantChange() {
            const select = document.querySelector('select[name="variant_id"]');
            const option = select.options[select.selectedIndex];
            if (option && option.value) {
                const wh = parseFloat(option.getAttribute('data-wholesale') || 0);
                const min = parseFloat(option.getAttribute('data-min') || wh);
                const max = parseFloat(option.getAttribute('data-max') || wh);
                this.rangeHint = `الحد الأدنى للبيع: ${min} {{ \App\Support\Money::symbol() }} — الحد الأقصى: ${max} {{ \App\Support\Money::symbol() }}`;
                if (!this.sellingPrice || this.sellingPrice < min) {
                    this.sellingPrice = min;
                }
                this.calculateQuote();
            }
        },

        async calculateQuote() {
            if (!this.variantId || !this.governorateId || !this.sellingPrice) {
                return;
            }

            this.quoteError = null;

            try {
                const response = await fetch('{{ route('affiliate.orders.quote') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        variant_id: this.variantId,
                        governorate_id: this.governorateId,
                        quantity: this.quantity,
                        selling_price: this.sellingPrice
                    })
                });

                const data = await response.json();
                if (data.success) {
                    this.quote = data.formatted;
                    this.warnings = data.quote.warnings || [];
                    this.quoteLoaded = true;
                    // The server range also applies the per-order commission cap for this quantity,
                    // which the product's own min/max (data-min/data-max) does not know about.
                    const q = data.quote;
                    this.rangeHint = `الحد الأدنى للبيع: ${(q.min_allowed_unit_price_minor / 100).toFixed(2)} {{ \App\Support\Money::symbol() }} — الحد الأقصى: ${(q.max_allowed_unit_price_minor / 100).toFixed(2)} {{ \App\Support\Money::symbol() }}`;
                } else {
                    this.quoteLoaded = false;
                    this.quoteError = data.message || 'تعذر حساب السعر، راجع البيانات.';
                }
            } catch (e) {
                console.error(e);
            }
        }
    }
}
</script>
@endsection

