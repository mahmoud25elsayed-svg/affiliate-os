<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تخصيص الثيم والمظهر - لوحة التاجر</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 py-8 space-y-6">

        <!-- Top Header & Breadcrumb -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs text-slate-400 font-semibold mb-1">
                    <a href="{{ route('store.kpis.index') }}" class="hover:text-slate-600 transition">لوحة التحكم</a>
                    <span>/</span>
                    <span class="text-slate-700">تخصيص المظهر</span>
                </div>
                <h1 class="text-2xl font-black text-slate-900 flex items-center space-x-3 space-x-reverse">
                    <span>🎨</span>
                    <span>تخصيص الثيم والمظهر (Theme & Storefront Customizer)</span>
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    تحكم بالهوية البصرية للمتجر، اختر الثيم، نسق أقسام الصفحة الرئيسية، وخصص ألوانك وخطوطك.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('store.domains.index') }}"
                   class="px-4 py-2 bg-white hover:bg-slate-100 text-slate-700 text-xs font-bold rounded-xl border border-slate-200 shadow-sm transition">
                    🌐 إدارة النطاقات المخصصة
                </a>
                <a href="{{ url('/') }}" target="_blank"
                   class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition flex items-center gap-1.5">
                    <span>معاينة المتجر الحقيقي</span>
                    <span>↗</span>
                </a>
            </div>
        </div>

        <!-- Flash Messages -->
        @if(session('success'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-bold flex items-center gap-2 shadow-sm">
            <span>✅</span>
            <span>{{ session('success') }}</span>
        </div>
        @endif

        @if($errors->any())
        <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl text-xs font-bold space-y-1">
            @foreach($errors->all() as $err)
                <p>• {{ $err }}</p>
            @endforeach
        </div>
        @endif

        <form method="POST" action="{{ route('store.theme.update') }}" class="space-y-8">
            @csrf

            <!-- 1. Theme Selection Cards -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200/80 shadow-sm space-y-6">
                <div>
                    <h2 class="text-base font-black text-slate-900">1. اختيار نمط وتصميم الثيم الأساسي</h2>
                    <p class="text-xs text-slate-500 mt-1">اختر القالب الأنسب لطبيعة منتجاتك وجمهورك المستهدف</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach($availableThemes as $t)
                    <label class="relative block p-6 rounded-3xl border-2 cursor-pointer transition-all {{ ($theme->theme_id ?? 'modern') === $t['id'] ? 'border-indigo-600 bg-indigo-50/20 shadow-md' : 'border-slate-200 hover:border-slate-300 bg-white' }}">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <input type="radio" name="theme_id" value="{{ $t['id'] }}" {{ ($theme->theme_id ?? 'modern') === $t['id'] ? 'checked' : '' }} class="w-4 h-4 text-indigo-600 focus:ring-indigo-500">
                                <h3 class="text-sm font-black text-slate-900">{{ $t['name'] }}</h3>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-black {{ $t['preview_badge'] === 'جديد' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700' }}">
                                {{ $t['preview_badge'] }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-600 mt-3 leading-relaxed">
                            {{ $t['description'] }}
                        </p>
                    </label>
                    @endforeach
                </div>
            </div>

            <!-- 2. Visual Branding (Colors & Fonts) -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200/80 shadow-sm space-y-6">
                <div>
                    <h2 class="text-base font-black text-slate-900">2. الألوان والخطوط (Branding & Typography)</h2>
                    <p class="text-xs text-slate-500 mt-1">اضبط ألوان متجرك لتتناسب مع هويتك التجارية</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">اللون الأساسي (Primary)</label>
                        <div class="flex items-center gap-2">
                            <input type="color" name="primary_color" value="{{ old('primary_color', $theme->primary_color ?? '#4f46e5') }}" class="w-10 h-10 p-0 border-0 rounded-xl cursor-pointer">
                            <input type="text" name="primary_color" value="{{ old('primary_color', $theme->primary_color ?? '#4f46e5') }}" dir="ltr" class="flex-1 px-3 py-2 rounded-xl border border-slate-300 text-xs font-mono">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">اللون الثانوي (Secondary)</label>
                        <div class="flex items-center gap-2">
                            <input type="color" name="secondary_color" value="{{ old('secondary_color', $theme->secondary_color ?? '#06b6d4') }}" class="w-10 h-10 p-0 border-0 rounded-xl cursor-pointer">
                            <input type="text" name="secondary_color" value="{{ old('secondary_color', $theme->secondary_color ?? '#06b6d4') }}" dir="ltr" class="flex-1 px-3 py-2 rounded-xl border border-slate-300 text-xs font-mono">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">لون التمييز والخصومات (Accent)</label>
                        <div class="flex items-center gap-2">
                            <input type="color" name="accent_color" value="{{ old('accent_color', $theme->accent_color ?? '#f59e0b') }}" class="w-10 h-10 p-0 border-0 rounded-xl cursor-pointer">
                            <input type="text" name="accent_color" value="{{ old('accent_color', $theme->accent_color ?? '#f59e0b') }}" dir="ltr" class="flex-1 px-3 py-2 rounded-xl border border-slate-300 text-xs font-mono">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">نوع الخط العربي (Font Family)</label>
                        <select name="font_family" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs bg-white text-slate-800">
                            @foreach(['Cairo', 'Almarai', 'Tajawal', 'Alexandria', 'IBM Plex Sans Arabic'] as $font)
                                <option value="{{ $font }}" {{ ($theme->font_family ?? 'Cairo') === $font ? 'selected' : '' }}>{{ $font }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <!-- 3. Homepage Sections Visibility -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200/80 shadow-sm space-y-6">
                <div>
                    <h2 class="text-base font-black text-slate-900">3. تفعيل وإظهار أقسام الصفحة الرئيسية</h2>
                    <p class="text-xs text-slate-500 mt-1">حدد الأقسام التي ترغب في إبرازها لزوار المتجر</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <label class="flex items-center gap-3 p-4 bg-slate-50 rounded-2xl border border-slate-200/60 cursor-pointer hover:bg-slate-100/60 transition">
                        <input type="checkbox" name="show_hero_banner" value="1" {{ $theme->show_hero_banner ? 'checked' : '' }} class="w-4 h-4 text-indigo-600 rounded">
                        <div>
                            <span class="text-xs font-bold text-slate-900 block">بانر الواجهة الرئيسي (Hero)</span>
                            <span class="text-[10px] text-slate-500">العنوان الكبير والزر الترويجي</span>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 p-4 bg-slate-50 rounded-2xl border border-slate-200/60 cursor-pointer hover:bg-slate-100/60 transition">
                        <input type="checkbox" name="show_features_badges" value="1" {{ ($theme->show_features_badges ?? true) ? 'checked' : '' }} class="w-4 h-4 text-indigo-600 rounded">
                        <div>
                            <span class="text-xs font-bold text-slate-900 block">شارات الثقة والضمان</span>
                            <span class="text-[10px] text-slate-500">الشحن السريع، المعاينة، الاسترجاع</span>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 p-4 bg-slate-50 rounded-2xl border border-slate-200/60 cursor-pointer hover:bg-slate-100/60 transition">
                        <input type="checkbox" name="show_featured_categories" value="1" {{ $theme->show_featured_categories ? 'checked' : '' }} class="w-4 h-4 text-indigo-600 rounded">
                        <div>
                            <span class="text-xs font-bold text-slate-900 block">الفئات المميزة</span>
                            <span class="text-[10px] text-slate-500">شبكة الأقسام والتصنيفات</span>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 p-4 bg-slate-50 rounded-2xl border border-slate-200/60 cursor-pointer hover:bg-slate-100/60 transition">
                        <input type="checkbox" name="show_featured_products" value="1" {{ $theme->show_featured_products ? 'checked' : '' }} class="w-4 h-4 text-indigo-600 rounded">
                        <div>
                            <span class="text-xs font-bold text-slate-900 block">المنتجات المميزة</span>
                            <span class="text-[10px] text-slate-500">أفضل المنتجات وأكثرها طلباً</span>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 p-4 bg-slate-50 rounded-2xl border border-slate-200/60 cursor-pointer hover:bg-slate-100/60 transition">
                        <input type="checkbox" name="show_offers_banner" value="1" {{ $theme->show_offers_banner ? 'checked' : '' }} class="w-4 h-4 text-indigo-600 rounded">
                        <div>
                            <span class="text-xs font-bold text-slate-900 block">بانر الخصومات والعروض</span>
                            <span class="text-[10px] text-slate-500">إبراز التخفيضات الكبرى</span>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 p-4 bg-slate-50 rounded-2xl border border-slate-200/60 cursor-pointer hover:bg-slate-100/60 transition">
                        <input type="checkbox" name="show_latest_products" value="1" {{ $theme->show_latest_products ? 'checked' : '' }} class="w-4 h-4 text-indigo-600 rounded">
                        <div>
                            <span class="text-xs font-bold text-slate-900 block">أحدث المنتجات المضافة</span>
                            <span class="text-[10px] text-slate-500">وصل حديثاً للمتجر</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- 4. Hero Content Settings -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200/80 shadow-sm space-y-6">
                <div>
                    <h2 class="text-base font-black text-slate-900">4. نصوص وروابط بانر الواجهة (Hero)</h2>
                    <p class="text-xs text-slate-500 mt-1">النص الترحيبي ورسالة التسويق الأولى التي يراها عملاؤك</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">العنوان الرئيسي (Headline)</label>
                        <input type="text" name="hero_headline" value="{{ old('hero_headline', $theme->hero_headline) }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-xs">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">العنوان الفرعي (Subheadline)</label>
                        <textarea name="hero_subheadline" rows="2" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-xs leading-relaxed">{{ old('hero_subheadline', $theme->hero_subheadline) }}</textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">نص الزر (CTA Button Text)</label>
                        <input type="text" name="hero_button_text" value="{{ old('hero_button_text', $theme->hero_button_text) }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-xs">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">رابط الزر (CTA URL)</label>
                        <input type="text" name="hero_button_url" value="{{ old('hero_button_url', $theme->hero_button_url) }}" dir="ltr" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-xs text-left">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">رابط صورة الهيرو (Hero Image URL)</label>
                        <input type="text" name="hero_image_url" value="{{ old('hero_image_url', $theme->hero_image_url) }}" dir="ltr" placeholder="https://... أو /images/hero.jpg" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-xs text-left font-mono">
                    </div>
                </div>
            </div>

            <!-- 5. White-Label Branding Section -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200/80 shadow-sm space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
                            <span>🏷️</span>
                            <span>إخفاء علامة المنصة (White-Label)</span>
                        </h2>
                        <p class="text-xs text-slate-500 mt-1">إخفاء عبارة "مشغل بواسطة AffiliateOS" من تذييل المتجر والفواتير</p>
                    </div>

                    @if($hasWhiteLabelFeature)
                        <span class="px-3 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full text-xs font-bold">
                            متاحة في باقتك ✅
                        </span>
                    @else
                        <span class="px-3 py-1 bg-amber-50 text-amber-800 border border-amber-200 rounded-full text-xs font-bold">
                            باقة المؤسسات فقط 🔒
                        </span>
                    @endif
                </div>

                @if($hasWhiteLabelFeature)
                <label class="flex items-center gap-3 p-4 bg-slate-50 rounded-2xl border border-slate-200 cursor-pointer">
                    <input type="checkbox" name="white_label_enabled" value="1" {{ ($theme->white_label_enabled ?? false) ? 'checked' : '' }} class="w-4 h-4 text-indigo-600 rounded">
                    <div>
                        <span class="text-xs font-bold text-slate-900 block">تفعيل الوضع الأبيض (إخفاء كافة إشارات AffiliateOS)</span>
                        <span class="text-[11px] text-slate-500">سيظهر متجرك مستقلاً 100% بدون أي روابط للمنصة في أسفل الصفحات</span>
                    </div>
                </label>
                @else
                <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 text-xs text-slate-500 leading-relaxed">
                    💡 ترقية الخطة إلى باقة المؤسسات (Enterprise) تمكّنك من تفعيل الـ White-label بالكامل وإزالة كل إشارات المنصة.
                </div>
                @endif
            </div>

            <!-- 6. Custom CSS & Advanced Style -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200/80 shadow-sm space-y-4">
                <div>
                    <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
                        <span>💻</span>
                        <span>أكواد CSS المخصصة (Custom CSS)</span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-1">أضف أي تنسيقات CSS إضافية تريد تطبيقها على واجهة المتجر</p>
                </div>

                <textarea name="custom_css" rows="4" dir="ltr" placeholder="/* Custom CSS here */&#10;.my-custom-class { ... }" class="w-full px-4 py-3 rounded-2xl border border-slate-300 font-mono text-xs text-slate-800 bg-slate-900 text-emerald-400 leading-relaxed">{{ old('custom_css', $theme->custom_css) }}</textarea>
            </div>

            <!-- Save Changes Floating Bar -->
            <div class="flex items-center justify-end gap-4 pt-4">
                <button type="submit" class="px-8 py-3.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-2xl shadow-lg shadow-indigo-600/20 transition-all hover:-translate-y-0.5 text-xs">
                    حفظ التغييرات وتطبيقها على المتجر ←
                </button>
            </div>

        </form>

    </div>
</body>
</html>
