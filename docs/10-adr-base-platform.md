# 10 — قرار المعمارية (ADR-010): تأكيد منصة الأساس (Lunar + Filament + stancl/tenancy على Laravel 12)

| الحقل | القيمة |
|---|---|
| **الحالة** | **معتمد (Accepted)** |
| **التاريخ** | 2026-10-27 |
| **المشروع** | `affiliate-os` |
| **الفرع** | `spike/base-platform` |
| **المرجع** | [08-saas-direction.md](file:///C:/Users/ENG-I/.gemini/antigravity-ide/scratch/affiliate-os/docs/08-saas-direction.md) §3، [09-clean-room-and-licensing.md](file:///C:/Users/ENG-I/.gemini/antigravity-ide/scratch/affiliate-os/docs/09-clean-room-and-licensing.md) |

---

## 1. السياق (Context)

بعد قرار التحول لمنصة SaaS لتجارة إلكترونية وأفلييت متعددة المستأجرين (ADR-008 و ADR-009)، تم استبعاد استخدام كود 6Valley بالكامل لصالح نهج الغرفة النظيفة (Clean-room)، واستبعاد Bagisto بسبب تعقيد تحويله إلى SaaS بقاعدة بيانات مستقلة لكل مستأجر ووجود إضافات تجارية مغلقة.

تم تكليف هذه المرحلة التجريبية (**P01 Spike**) بالتحقق العملي والتجريبي من جدوى تشغيل:
**Laravel 12 + Lunar (Headless Ecommerce) + Filament v4 + stancl/tenancy v3** في بيئة متعددة قواعد البيانات (Database-per-tenant)، والإجابة عن الأسئلة الحاسمة التالية قبل الالتزام:
1. هل تتوافق إصدارات هذه الحزم رسميًا على Laravel 12 بترخيص مفتوح المصدر (MIT) يسمح بالبيع كخدمة SaaS تجارية؟
2. هل يمكن عزل جداول Lunar بالكامل داخل قواعد بيانات المستأجرين دون تلويث القاعدة المركزية؟
3. كم يستغرق إنشاء وتهيئة مستأجر جديد بالكامل (Migrations + Seeders + Admin User)؟
4. هل يضمن النظام عزلاً تاماً للبيانات والمنتجات والطلبات بين المستأجرين؟
5. هل يمكن تمديد وظائف المتجر ونواة التجارة (حسابات السلة، التسعير بالجملة، حالات الطلب، ووحدة الأفلييت) دون تعديل ملف واحد داخل `vendor/`؟

---

## 2. مراجعة التراخيص والإصدارات المعتمدة

تم فحص وحل الحزم المعتمدة في بيئة تجريبية حقيقية، وجميعها مرخصة بـ **MIT License** المتوافق تجارياً وقانونياً:

| الحزمة | الإصدار المقفل في الـ Spike | الترخيص | رابط المصدر الرسمي والترخيص |
|---|---|---|---|
| **laravel/framework** | `v12.70.1` | MIT | [Laravel 12 License](https://github.com/laravel/framework/blob/12.x/LICENSE.md) |
| **lunarphp/lunar** & **lunarphp/core** | `v1.5.0` | MIT | [Lunar License](https://github.com/lunarphp/lunar/blob/main/LICENSE.md) |
| **filament/filament** | `v4.16.0` | MIT | [Filament License](https://github.com/filamentphp/filament/blob/4.x/LICENSE.md) |
| **stancl/tenancy** | `v3.10.1` | MIT | [Stancl Tenancy License](https://github.com/archtechx/tenancy/blob/3.x/LICENSE) |

> **النتيجة:** لا توجد أي مكتبات أو إضافات طرف ثالث مدفوعة أو مغلقة المصدر، والترخيص يتيح بناء وبيع نظام SaaS بنسبة 100%.

---

## 3. نتائج القياس التجريبية (Empirical Benchmark Results)

تم بناء بيئة اختبارية كاملة داخل مجلد `spike/` تحتوي على قاعدة مركزية و 3 مستأجرين مستقلين، وإجراء الاختبارات الآلية (`7 passed, 35 assertions`).

### 3.1 زمن إنشاء المستأجر (Tenant Provisioning Time)
تم تنفيذ أمر مخصص `php artisan tenant:create {id} {domain}` ينشئ قاعدة البيانات، وينفذ migrations المستأجر وجداول Lunar (96+ جدولاً)، وينفذ الـ Seeder (إنشاء العملة الافتراضية EGP، القناة، اللغة العربية، مجموعة العملاء، نوع المنتجات، وحساب المالك):

| المستأجر | زمن الإنشاء الفعلي | النتيجة |
|---|---|---|
| `tenant1` | **6.04 ثانية** | نجاح كامل |
| `tenant2` | **5.46 ثانية** | نجاح كامل |
| `tenant3` | **5.46 ثانية** | نجاح كامل |
| **المتوسط** | **5.65 ثانية** | **أقل بكثير من الحد الأقصى المستهدف (< 60 ثانية)** |

### 3.2 عزل المخطط والبيانات (Schema & Data Isolation)
- **القاعدة المركزية (Central DB):** تحتوي فقط على 5 جداول منصة (`users`, `tenants`, `domains`, `cache`, `jobs`)، وخالية تماماً من أي جدول من جداول Lunar بفضل تعطيل هجرات النواة تلقائياً (`'disable_migrations' => true` في `config/lunar/database.php`).
- **قواعد المستأجرين (Tenant DBs):** كل مستأجر يمتلك قاعدة بياناته المعزولة بالكامل متضمنة كافة جداول Lunar والمتجر، بالإضافة للجداول المخصصة (`affiliates`, `wholesale_prices`).
- **اختبار العزل (`TenantIsolationTest`):**
  - تم إنشاء منتجات ومتغيرات مستقلة لكل مستأجر (`tenant1` منتج بـ 300 EGP، `tenant2` منتج بـ 500 EGP، `tenant3` منتج بـ 750 EGP).
  - أثبتت الاختبارات الآلية أن استعلامات النماذج وقاعدة البيانات محصورة بالكامل في سياق المستأجر النشط، ويستحيل لأي مستأجر قراءة أو تعديل بيانات مستأجر آخر.

### 3.3 نقاط الامتداد والتخصيص بدون لمس vendor (Extension Points)
تم اختبار 4 سيناريوهات امتداد حاسمة لمتطلبات المنصة في `LunarExtensionTest`:
1. **سعر الجملة للمتغير (`WholesalePrice`):**
   - تم إنشاء جدول ونموذج منفصل `WholesalePrice`، وربطه بديناميكية عبر `ProductVariant::resolveRelationUsing('wholesalePrice', ...)` دون أي تجاوز (override) لملفات Lunar.
2. **حالة طلب مخصصة (`COD Confirmed`):**
   - تمت إضافة الحالة بنجاح عبر `config/lunar/orders.php`، وتم إنشاء طلبات وقراءتها والتحقق من صلاحيتها في محرك الحالات.
3. **خط معالجة السلة المخصص (`ApplyCodFeePipeline`):**
   - تمت إضافة خطوة معالجة مخصصة لاحتساب رسوم الدفع عند الاستلام (COD Fee) وتسجيلها في بيانات وصف السلة (Cart Meta) عبر مصفوفة `config/lunar/cart.php` pipelines.
4. **وحدة الأفلييت المنفصلة (`Affiliate Module`):**
   - تم بناء جدول `affiliates` ونموذج `Affiliate` ومورد Filament (`AffiliateResource`) يعمل داخل سياق المستأجر وبلوحته الإدارية بعزل كامل عن بقية المستأجرين.

---

## 4. الملاحظات التقنية والحلول المكتشفة (Technical Gotchas & Solutions)

أثناء بناء الـ Spike تم التوصل إلى حلول معمارية دقيقة يجب تثبيتها في مراحل البناء اللاحقة:

1. **تسجيل لوحة Lunar في دورة حياة Filament v4:**
   - في Filament v4، التسجيل عبر `Filament::registerPanel()` يعتمد على خطاف حل الحاوية (`resolving(PanelRegistry::class)`). إذا تم استدعاء `LunarPanel::register()` في دالة `boot()` يكون السجل قد تم حله مسبقاً فيفشل التسجيل.
   - **الحل:** يجب استدعاء تسجيل اللوحات في دالة `register()` داخل `AppServiceProvider` أو Provider مخصص للوحات.
2. **منع هجرات Lunar في القاعدة المركزية:**
   - افتراضياً تقوم حزمة Lunar بتسجيل مجلد الهجرات الخاص بها في مسار الهجرات المركزي لـ Laravel.
   - **الحل:** وضع `'disable_migrations' => true` في `config/lunar/database.php`، وتمرير مسار هجرات `vendor/lunarphp/*/database/migrations` صراحة في مصفوفة `migration_parameters` في `config/tenancy.php`.
3. **Casting الكائنات في Lunar Order:**
   - حقول مثل `tax_breakdown` في Lunar Order تستخدم كائنات قيمة مخصصة (`TaxBreakdown` Value Object) تتطلب التحويل إلى JSON وليس مصفوفة فارغة منعاً لخطأ القيود في قواعد البيانات الصارمة.

---

## 5. القرار (The Decision)

> **تأكيد Lunar كقاعدة أساسية للمنصة (Lunar Confirmed)**.

تم استيفاء جميع معايير القبول بنجاح تام:
- لا يوجد أي تسرب للبيانات بين المستأجرين.
- لم يتم تعديل أي سطر برمجي داخل مجلد `vendor/` إطلاقاً.
- التوافق مع Laravel 12 و Filament v4 و stancl/tenancy مستقر وسلس للغاية.
- سرعة تهيئة المستأجر (~5.6 ثوانٍ) تلبي متطلبات تجربة المستخدم للخدمة الذاتية للتاجر.

---

## 6. الأثر على المراحل التالية (P02 إلى P13)

بناءً على نتائج الـ Spike، يتم اعتماد التوصيات التالية للمراحل القادمة في خطة التنفيذ:

1. **المرحلة P02 (Project Bootstrap):**
   - اعتماد نفس شجرة الحزم المقفلة (`laravel 12.70.x`, `lunar 1.5.x`, `filament 4.16.x`, `tenancy 3.10.x`).
   - إعداد `AppServiceProvider` وهيكل `PanelProvider` المكتشف لدعم لوحة الإدارة المركزية ولوحة التاجر المتعددة.
2. **المرحلة P04 (Tenancy):**
   - اعتماد نموذج التهيئة المعتمد في الـ Spike (`ProvisionTenantJob`) مع تمرير مسارات هجرات Lunar للمستأجرين فقط.
   - تطبيق عزل الكاش وجلسات التخزين ببادئات معرف المستأجر (`tenant_{id}`).
3. **المراحل P07–P10 (Store & Catalog):**
   - الاستفادة المباشرة من نماذج Lunar الأساسية (`Product`, `ProductVariant`, `Collection`, `Price`, `Media`) دون إعادة اختراعها.
   - بناء واجهة المتجر كـ Headless Storefront باستخدام Inertia/React مع استهلاك نماذج وخدمات Lunar في سياق المستأجر.
4. **المراحل P11–P13 (Orders, State Machine & Finance):**
   - ربط آلة حالات الطلبات ودفتر الأستاذ (Double-entry Ledger) بخطافات وأحداث الطلب الخاصة بـ Lunar (`Lunar\Models\Order`).
   - توسيع حسابات السلة وضريبة الشحن عبر pipelines المدمجة في `config/lunar/cart.php`.
