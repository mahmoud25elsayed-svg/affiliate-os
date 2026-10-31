@extends('layouts.affiliate')

@section('title', __('توثيق الهوية (KYC)'))

@section('content')
<div class="max-w-2xl mx-auto py-6">
    <div class="bg-white p-8 rounded-3xl border border-slate-200 shadow-md">
        <div class="flex items-center justify-between border-b border-slate-100 pb-5 mb-6">
            <div>
                <h1 class="text-xl font-extrabold text-slate-900">{{ __('توثيق الهوية الشخصية (KYC)') }}</h1>
                <p class="text-xs text-slate-500 mt-1">{{ __('وفقاً للمتطلبات التنظيمية لحماية المعاملات المالية وضمان هوية المسوقين.') }}</p>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-bold
                @if($affiliate->kyc_status === 'approved') bg-emerald-100 text-emerald-800
                @elseif($affiliate->kyc_status === 'pending') bg-amber-100 text-amber-800
                @elseif($affiliate->kyc_status === 'rejected') bg-rose-100 text-rose-800
                @else bg-slate-100 text-slate-600 @endif">
                {{ match($affiliate->kyc_status) {
                    'approved' => __('موثق ومعتمد ✓'),
                    'pending' => __('قيد المراجعة ⏳'),
                    'rejected' => __('مرفوض ✕'),
                    default => __('غير مكتمل')
                } }}
            </span>
        </div>

        @if($affiliate->kyc_status === 'rejected')
            <div class="p-4 mb-6 text-sm text-rose-800 bg-rose-50 rounded-2xl border border-rose-200">
                <span class="font-bold block">{{ __('سبب رفض الوثائق السابقة:') }}</span>
                <span class="text-xs mt-1 block">{{ $affiliate->kyc_rejected_reason }}</span>
            </div>
        @endif

        @if($affiliate->kyc_status === 'approved')
            <div class="p-6 text-center bg-emerald-50 rounded-2xl border border-emerald-200 text-emerald-900 mb-6">
                <span class="text-3xl block mb-2">🎉</span>
                <h3 class="font-bold text-base">{{ __('تم اعتماد وثائق هويتك بنجاح') }}</h3>
                <p class="text-xs text-emerald-700 mt-1">
                    {{ __('الرقم القومي المسجل:') }} <span class="font-mono font-bold">{{ substr($affiliate->national_id_number ?? '', 0, 4) . '******' . substr($affiliate->national_id_number ?? '', -4) }}</span>
                </p>
                <span class="text-[11px] text-emerald-600 block mt-2">{{ __('تاريخ الاعتماد:') }} {{ $affiliate->kyc_reviewed_at?->format('d/m/Y') }}</span>
            </div>
        @else
            <form method="POST" action="{{ route('affiliate.kyc.submit') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf

                <div>
                    <label for="national_id_number" class="block text-sm font-semibold text-slate-700 mb-1">{{ __('الرقم القومي (14 رقم) *') }}</label>
                    <input type="text" name="national_id_number" id="national_id_number" maxlength="14" required dir="ltr"
                           value="{{ old('national_id_number', $affiliate->national_id_number) }}"
                           class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm font-mono text-right"
                           placeholder="29801010123456">
                    @error('national_id_number') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="id_card_front" class="block text-sm font-semibold text-slate-700 mb-1">{{ __('صورة وجه البطاقة *') }}</label>
                        <input type="file" name="id_card_front" id="id_card_front" accept="image/*" required
                               class="w-full text-xs text-slate-500 file:mr-0 file:ml-3 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 border border-slate-200 rounded-xl p-2">
                        @error('id_card_front') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="id_card_back" class="block text-sm font-semibold text-slate-700 mb-1">{{ __('صورة ظهر البطاقة *') }}</label>
                        <input type="file" name="id_card_back" id="id_card_back" accept="image/*" required
                               class="w-full text-xs text-slate-500 file:mr-0 file:ml-3 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 border border-slate-200 rounded-xl p-2">
                        @error('id_card_back') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 text-xs text-slate-600 space-y-1">
                    <span class="font-bold block text-slate-800">{{ __('ملاحظات هامة:') }}</span>
                    <p>{{ __('• يجب أن تكون الصور واضحة وجميع البيانات والأرقام مقروءة بالكامل دون انعكاس ضوئي.') }}</p>
                    <p>{{ __('• تُحفظ المستندات في وسائط تخزين خاصة ومشفرة، وتخضع كل عملية فحص لسجل تدقيق أمني مشدد.') }}</p>
                </div>

                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3.5 px-4 rounded-xl shadow-md transition duration-200">
                    {{ __('رفع الوثائق للمراجعة') }}
                </button>
            </form>
        @endif
    </div>
</div>
@endsection

