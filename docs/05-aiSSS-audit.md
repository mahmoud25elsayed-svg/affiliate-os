# 05 — تدقيق مشروع aiSSS: ما نعيده وما نصلحه وما نتركه

المصدر: المستودع الخاص `aiSSS` (فرع `main`، آخر commit: "Skip registration/install steps and rename project to isss")، وتحديدًا `Modules/Affiliate` (~2,300 سطر غير الـ views، 11 migration، 19 ملف view) و`AFFILIATE_TRANSFORMATION_PLAN.md`.

> المراجعة قراءة كود فقط (لم يُشغَّل المشروع). كل نقطة مرتبطة بملف وسطر لتتحقق منها.

## 1. ما هو aiSSS

متجر **6Valley** (سكريبت متجر متعدد البائعين تجاري، ~9,700 ملف، تطبيقات جوال وتوصيل ومزادات…) تُضاف عليه وحدة `Modules/Affiliate` تحاكي نموذج تاجر: كتالوج، طلب مباشر، محفظة، سحب، مستويات، لوحة أدمن.

## 2. قرار: لا نبني فوق 6Valley

| السبب | الدليل |
|---|---|
| حجم غير مناسب | ~9,739 ملف؛ **فشل `git clone` على Windows عند الـ checkout** بسبب مسارات طويلة في `updates/` و`ios/build` (Filename too long) |
| ترخيص | 6Valley سكريبت تجاري (استنتاج من بنيته وملف `InstallationMiddleware`، دون قراءة نص الترخيص). **نسخ كوده لمستودع جديد قد يخالف شروط الترخيص (لم أقرأ نصه). راجع الترخيص قبل أي نسخ.** |
| تقييد النموذج | الوحدة تعتمد على `App\Models\Order` و`Product` الخاصين بـ 6Valley (`current_stock`, `unit_price`, `order_status`)، فتتقيّد بنموذج غير مصمم للـ COD |
| ضجيج | 115 موديل في `app/Models` معظمها غير ذي صلة (مزادات، توصيل، بائعون…) |

## 3. ما نعيد استخدامه (كفكرة أو كود كتبته أنت)

| العنصر | المصدر | كيف نعيده |
|---|---|---|
| فكرة محفظة بأرصدة معلقة/متاحة/محجوزة/مسحوبة | `AffiliateWallet`، `WalletLedgerService` | نُحوّلها إلى دفتر قيد مزدوج (انظر 02) |
| `lockForUpdate()` داخل `DB::transaction` | `WalletLedgerService` | نُبقيه، ونضيف idempotency |
| دورة السحب (طلب ← حجز ← موافقة ← دفع) | `createWithdrawalRequest` + `WithdrawalController` | نفس المنطق مع maker-checker |
| المستويات (برونزي/فضي/ذهبي/بلاتيني) | `AffiliateLevelSeeder`، `config.php` | نربطها بنسبة التسليم لا بعدد الطلبات وحده |
| طرق السحب المحلية (فودافون كاش، إنستاباي، بنك) | `config.php` | نبقيها |
| نصوص عربية وتصميم الـ RTL | views | مرجع تصميم فقط |
| `affiliate:install` | `InstallAffiliateCommand` | أمر تثبيت للمشروع الجديد |
| ملاحظات التخطيط ومخططات mermaid | `AFFILIATE_TRANSFORMATION_PLAN.md` | نوسّعها هنا |

## 4. ما نصلحه (مشاكل وجدتها في الكود)

| # | المشكلة | الموضع | الأثر | الحل في الجديد |
|---|---|---|---|---|
| A1 | المبالغ `float` في خدمات المحفظة | `WalletLedgerService.php:16,40,73,101` | أخطاء تقريب تراكمية | أعداد صحيحة بالقروش |
| A2 | ليس قيدًا مزدوجًا؛ الجدول يخزن `balance_after` لـ available فقط حتى في قيود pending/locked | `WalletLedgerService.php:31,92,137` | سجل لا يعكس الأرصدة الأخرى | حسابات منفصلة وسطور موقّعة |
| A3 | لا idempotency على القيود | `WalletLedgerService` كله | تكرار حدث = ربح مضاعف | `idempotency_key UNIQUE` |
| A4 | مسارات متوازية لتغيير الحالة: الـ Controller (`deliver/cancel/returnOrder`) وكذلك `AffiliateOrderObserver` | `OrderRequestController.php:97-187`، `AffiliateOrderObserver.php:26` | احتمال ازدواج أو تضارب، يعتمد على ترتيب حفظ الحالة | خدمة انتقال واحدة |
| A5 | `deliver()` بلا فحص للحالة السابقة (يسمح بالتسليم من `new`) | `OrderRequestController.php:97-127` | تخطّي التأكيد؛ يُضاف الربح للمتاح دون أن يمر بالمعلق | آلة حالات تمنع الانتقال |
| A6 | `cancel()` بلا فحص حالة؛ والمرتجع بعد التسليم لا يسترد الربح من available | `:162-187`, `:141` | ربح يُدفع على طلب أُرجع | نافذة مرتجع + دين سالب + قيود عكسية |
| A7 | لا حجز مخزون: يتحقق من `current_stock` فقط ولا ينقصه | `OrderPlacementService.php:30` | بيع أكثر من المتاح | جدول حجوزات وCHECK |
| A8 | `shipping_cost` يُقرأ من المدخلات (والـ Controller يمرر `$request->all()`) | `OrderPlacementService.php:59`، `OrderRequestController.php:96` | المسوّق قد يتحكم في الشحن عبر طلب مباشر (لم أجرب استغلاله) | التسعير كله على السيرفر + `validated()` فقط |
| A9 | مفتاح إعداد غير متسق: الكود يقرأ `affiliate.min_withdrawal` والإعداد المعرف `min_withdrawal_amount` | `WalletLedgerService.php:106`، `config.php:10` | إعداد الأدمن لا يؤثر كما يُتوقع (يعمل بالافتراضي 100) | مصدر إعدادات واحد مُختبَر |
| A10 | إعدادات معرّفة بلا استخدام ظاهر: `commission_hold_days`, `auto_approve_commission`, `max_markup_percentage` | `config.php:16,19,25` | وعود غير منفذة (فترة الاحتجاز لا تعمل) | نافذة المرتجع منفذة ومختبرة |
| A11 | نموذجان للعمولة متنافسان: `CommissionService` (نسبة، يستخدم `product_base_price` و`addPending`) و`OrderPlacementService` (ربح رقمي) | `CommissionService.php:15-111` | كود ميت/غير متسق | نموذج تسعير واحد (انظر 02 §5) |

## 5. ما نتركه

مزادات، توصيل بالمندوب، متاجر البائعين، الثيمات، كل ما يخص تطبيقات الجوال الأصلية لـ 6Valley.

## 6. خطة النقل

1. **لا ننسخ ملفات من aiSSS.** نعيد الكتابة بالمفاهيم المذكورة في §3 ضمن بنية 02.
2. لو أردت استخدام Blade views كمرجع تصميم: ننسخ فقط الأصول التي كتبتها أنت (نصوص، ألوان، هيكل الصفحات)، لا قوالب 6Valley.
3. `AFFILIATE_TRANSFORMATION_PLAN.md` يبقى في aiSSS كمرجع تاريخي.
4. تُنقل اختبارات القبول التالية إلى المشروع الجديد كمتطلبات (انظر 02 §9).

## 7. أدلة قبول مستخرجة من الأخطاء

- طلب يُسلَّم مرتين ⇒ رصيد واحد.
- تسليم مباشر من `submitted` ⇒ يرفض.
- إرجاع طلب مُسلَّم بعد انتهاء نافذة المرتجع ⇒ الرصيد المتاح ينقص (أو يصير دينًا) بقيد عكسي.
- طلبا سحب متزامنان على نفس الرصيد ⇒ ينجح واحد فقط.
- إرسال `shipping_cost=0` من العميل ⇒ يتجاهله السيرفر.
- حجزان متزامنان لآخر قطعة ⇒ ينجح واحد.
