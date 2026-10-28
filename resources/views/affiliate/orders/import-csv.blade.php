@extends('layouts.affiliate')

@section('title', 'استيراد طلبات مجمعة عبر CSV')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-2">
        <h1 class="text-2xl font-black text-slate-900">استيراد الطلبات الجماعية عبر ملف CSV</h1>
        <p class="text-sm text-slate-500">ارفع ملف الطلبات وسيقوم النظام بالتحقق التلقائي من المنتجات والأسعار وتطبيق فحص الجودة (Quality Gate) فورياً.</p>
    </div>

    {{-- Import Result Alert --}}
    @if(isset($result))
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-4">
            <h3 class="text-base font-bold text-slate-900">نتيجة عملية الاستيراد</h3>

            <div class="grid grid-cols-3 gap-4 text-center">
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <span class="block text-xs text-slate-500 mb-1">إجمالي الصفوف</span>
                    <span class="text-2xl font-black text-slate-800">{{ $result['total'] }}</span>
                </div>
                <div class="bg-emerald-50 p-4 rounded-xl border border-emerald-200">
                    <span class="block text-xs text-emerald-700 font-bold mb-1">تم بنجاح</span>
                    <span class="text-2xl font-black text-emerald-600">{{ $result['imported'] }}</span>
                </div>
                <div class="bg-rose-50 p-4 rounded-xl border border-rose-200">
                    <span class="block text-xs text-rose-700 font-bold mb-1">تعذر استيرادها</span>
                    <span class="text-2xl font-black text-rose-600">{{ $result['failed'] }}</span>
                </div>
            </div>

            @if(!empty($result['errors']))
                <div class="mt-4 p-4 bg-rose-50 border border-rose-200 rounded-xl space-y-2">
                    <h4 class="text-xs font-bold text-rose-800">تفاصيل الأخطاء بالصفوف:</h4>
                    <ul class="text-xs text-rose-700 space-y-1 pr-4 list-disc">
                        @foreach($result['errors'] as $err)
                            <li><strong>السطر {{ $err['row'] }}:</strong> {{ $err['message'] }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="pt-2">
                <a href="{{ route('affiliate.orders.index') }}" class="inline-block bg-indigo-600 text-white font-bold text-sm px-6 py-2.5 rounded-xl shadow-sm hover:bg-indigo-700">
                    الانتقال لسجل الطلبات &larr;
                </a>
            </div>
        </div>
    @endif

    {{-- Upload Form --}}
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-6">
        <form method="POST" action="{{ route('affiliate.orders.import-csv.submit') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <div>
                <label class="block text-sm font-bold text-slate-800 mb-2">اختر ملف CSV من جهازك</label>
                <input type="file" name="csv_file" accept=".csv,text/csv" required
                       class="w-full text-sm rounded-xl border border-slate-200 p-3 bg-slate-50 focus:ring-indigo-500 focus:border-indigo-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700">
                <p class="text-xs text-slate-400 mt-1">الحد الأقصى لحجم الملف: 5 ميجابايت.</p>
                @error('csv_file') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div class="bg-indigo-50/70 p-4 rounded-xl border border-indigo-100 text-xs text-slate-700 space-y-2">
                <h4 class="font-bold text-indigo-900">هيكل الأعمدة المطلوب في ملف CSV:</h4>
                <p class="text-slate-600">يجب أن يحتوي السطر الأول على أسماء الأعمدة التالية باللغة العربية أو الإنجليزية:</p>
                <div class="overflow-x-auto">
                    <table class="w-full bg-white rounded-lg border border-indigo-200 text-center font-mono text-[11px] mt-2">
                        <thead class="bg-indigo-100/50 text-indigo-900 font-bold">
                            <tr>
                                <th class="p-2 border border-indigo-200">الاسم (name)</th>
                                <th class="p-2 border border-indigo-200">الهاتف (phone)</th>
                                <th class="p-2 border border-indigo-200">المحافظة (governorate)</th>
                                <th class="p-2 border border-indigo-200">المدينة (city)</th>
                                <th class="p-2 border border-indigo-200">العنوان (address)</th>
                                <th class="p-2 border border-indigo-200">الكود (sku)</th>
                                <th class="p-2 border border-indigo-200">الكمية (quantity)</th>
                                <th class="p-2 border border-indigo-200">السعر (selling_price)</th>
                            </tr>
                        </thead>
                        <tbody class="text-slate-600">
                            <tr>
                                <td class="p-2 border border-indigo-100">أحمد محمود</td>
                                <td class="p-2 border border-indigo-100">01012345678</td>
                                <td class="p-2 border border-indigo-100">القاهرة</td>
                                <td class="p-2 border border-indigo-100">مدينة نصر</td>
                                <td class="p-2 border border-indigo-100">شارع النصر عمارة 4</td>
                                <td class="p-2 border border-indigo-100">SKU-1001</td>
                                <td class="p-2 border border-indigo-100">1</td>
                                <td class="p-2 border border-indigo-100">250</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex items-center justify-between pt-2">
                <a href="{{ route('affiliate.orders.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-800">
                    &larr; إلغاء ورجوع
                </a>

                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm py-2.5 px-6 rounded-xl shadow-sm transition-all">
                    بدء استيراد الطلبات
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
