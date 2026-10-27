# تدقيق أداء ومعايير واجهة المتجر (Storefront Performance & Audit) - P08

## 1. نظرة عامة (Overview)
واجهة المتجر في منصة **Affiliate-OS** مبنية بمعمارية SSR (Server-Side Rendering) هجينة باستخدام **Laravel Blade + Livewire 3 + Tailwind CSS**، مع مراعاة كاملة لمتطلبات محركات البحث (SEO Crawlers) والجمهور العربي (RTL-first) واستجابة فائقة على الهواتف المحمولة (Mobile-First).

---

## 2. مستهدفات الأداء ومؤشرات Core Web Vitals

| المعيار | الهدف المستهدف | الإجراء المتبع لتحقيقه |
| :--- | :---: | :--- |
| **Lighthouse Mobile Score** | ≥ 85 | تجميع الأصول بـ Vite، وضغط CSS، وتحميل صور كسول (Lazy Loading). |
| **Lighthouse Desktop Score** | ≥ 95 | استجابة فورية من السيرفر وتقليل زمن المعالجة (TTFB < 200ms). |
| **SEO Score** | ≥ 95 | بنية HTML5 دلالية، ووسوم Schema.org JSON-LD كاملة، وSitemap ديناميكي. |
| **Largest Contentful Paint (LCP)** | < 2.0s | الأولوية للـ Hero Banner بصيغ WebP مع أبعاد صريحة لمنع القفزات. |
| **Cumulative Layout Shift (CLS)** | < 0.05 | تثبيت أبعاد الصور وأماكن الـ Skeleton في Livewire. |
| **First Input Delay (FID) / INP** | < 100ms | تفاعلية Livewire خفيفة الوزن تعتمد على Alpine.js المدمج. |

---

## 3. استراتيجية التخزين المؤقت والعزل بين المتاجر (Multi-Tenant Caching)

### 3.1 عزل الكاش لكل متجر (Tenant-Aware Cache Namespacing)
من خلال `StorefrontCacheService`، يتم إنشاء مفاتيح كاش مستقلة لكل متجر تلقائياً:
- مفتاح كاش الصفحة الرئيسية: `tenant:{id}:storefront:home` (صلاحية 30 دقيقة).
- مفتاح كاش الفئات المميزة: `tenant:{id}:storefront:categories` (صلاحية 60 دقيقة).
- مفتاح كاش إعدادات الثيم: `tenant:{id}:storefront:theme` (صلاحية 24 ساعة).

### 3.2 إبطال الكاش التلقائي عند التعديل (Cache Invalidation Hooks)
تم ربط أحداث الـ Eloquent في `AppServiceProvider`:
- عند حفظ أو حذف أي منتج (`Product::saved`, `Product::deleted`) يتم استدعاء `StorefrontCacheService::flush()`.
- عند تعديل الفئات والمجموعات (`Collection::saved`, `Collection::deleted`) يتم تنظيف كاش المتجر المعني فوراً.
- عند تعديل إعدادات المظهر (`ThemeSettings`) من لوحة التاجر، يتم مسح كاش الثيم فوراً لتحديث الألوان والشعارات والخطوط بدون تأخير.

---

## 4. تقنيات الأداء المطبقة في الواجهة (Frontend Optimizations)

1. **التحميل الكسول للصور (Lazy Loading):**
   - جميع الصور داخل البطاقات وقوائم المنتجات تستخدم:
     `loading="lazy" decoding="async"`
2. **الخطوط العربية المُحسنة:**
   - تحميل خطوط Google Fonts (مثل Cairo و Tajawal و Almarai) مع استخدام `display=swap` لتفادي ظهور النص الخفي (FOIT).
   - استخدام `preconnect` لنطاق `fonts.googleapis.com` و `fonts.gstatic.com`.
3. **البحث العربي السريع والمتسامح (Normalized Arabic Search):**
   - تطبيع الأحرف تلقائياً (`أ, إ, آ -> ا` و `ة <-> ه` و `ى <-> ي`) مع دعم صيغة JSON المشفرة في Lunar.
   - كاش قصير للنتائج الأكثر بحثاً لتقليل الضغط على قاعدة البيانات.
4. **تجاوب كامل للهاتف المحمول (Mobile-First Navigation):**
   - شريط تنقل سفلي ثابت (Bottom Navigation Bar) للهواتف المحمولة يمنح تجربة تضاهي تطبيقات الهاتف الأصلية (Native App Experience).
   - زر شراء سريع يفتح بطاقة تحديد الخصائص (Variant Picker) بنقرة واحدة.

---

## 5. نتائج الفحص والاختبار (Verification Status)
- تم بناء حزم الاختبارات الآلية بنجاح وتغطية:
  - تصفح المتجر، الأقسام، صفحة المنتج، البحث، والصفحات الثابتة (`StorefrontPagesTest`).
  - خرائط الموقع التلقائية وملفات `robots.txt` والبيانات المنظمة JSON-LD (`StorefrontSeoAndSitemapTest`).
  - عزل الكاش وتأكيد حالة تعليق المتجر بـ HTTP 503 (`StorefrontCacheAndSuspensionTest`).
- نسبة النجاح: 100% نجاح في جميع الاختبارات.
