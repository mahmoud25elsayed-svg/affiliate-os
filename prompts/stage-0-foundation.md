# المرحلة 0 — الأساس (P01–P06)

---

## P01 — Spike: التحقق من Lunar + تعدد المستأجرين

```text
أنت مهندس برمجيات أول. المستودع الحالي يحتوي وثائق فقط (README و docs/ و prompts/). اقرأ أولًا:
docs/02-architecture.md و docs/08-saas-direction.md و docs/09-clean-room-and-licensing.md.

اقرأ أيضًا docs/16-hostinger-and-review-decisions.md (الإنتاج على استضافة Hostinger المشتركة).

الهدف: إثبات أن Lunar (MIT) + Filament + stancl/tenancy بوضع **قاعدة البيانات الواحدة مع tenant_id** تعمل معًا على Laravel 12، وتعمل على استضافة مشتركة (بلا Redis ولا Docker ولا عمليات دائمة)، قبل الالتزام بها.

المطلوب (داخل مجلد spike/ منفصل، لن يُدمج في الكود النهائي):
1. تحقق بنفسك من أحدث الإصدارات المستقرة والمتوافقة: Laravel، Lunar، Filament، stancl/tenancy، ومن ملف LICENSE لكل منها. اكتب ما وجدته مع روابط.
2. أنشئ مشروع Laravel تجريبيًا، ثبّت الحزم الأربع بوضع single-database tenancy. أضف tenant_id لجداول Lunar بـ migration من عندنا، واستبدل نماذج Lunar بنماذج ترثها وتضيف Global Scope للمستأجر (تحقق من آلية ModelManifest في وثائق Lunar الحالية).
3. أمر artisan ينشئ مستأجرًا (سجل tenant + seed بياناته الأساسية + مستخدم مالك) وقِس الزمن.
3ب. شغّل التجربة بإعدادات الاستضافة المشتركة: QUEUE_CONNECTION=database، CACHE_STORE=database، طوابير عبر `queue:work --stop-when-empty` كما لو كانت من cron. تأكد أن Job يحمل سياق المستأجر الصحيح.
4. أنشئ 3 مستأجرين، وأضف منتجًا لكلٍّ عبر لوحة Lunar، وأثبت باختبار أن مستأجرًا لا يرى منتجات غيره.
5. وحدة "أفلييت" تجريبية صغيرة (جدول + نموذج + صفحة Filament) داخل المستأجر، دون تعديل أي ملف من Lunar في vendor.
6. جرب الامتداد: حقل سعر جملة للمتغير في جدول خاص بنا، وحالة طلب مخصصة عبر config، وPipeline مخصص في حساب السلة — كلها بدون تعديل vendor.

القيود: لا تنسخ أي شيء من 6Valley. لا تثبّت إضافات مدفوعة. لا تقارن بـ Bagisto (مستبعد بقرار المالك).

معايير الفشل (أي واحد منها ⇒ نوصي بالبناء من الصفر):
- تسرب بيانات بين المستأجرين لا يُحل بإعداد، أو نموذج من نماذج Lunar لا يمكن تغطيته بالـ Global Scope.
- الحاجة لتعديل ملفات داخل vendor/lunarphp أو أكثر من override هش.
- إصدار Lunar المتاح غير مستقر لـ Laravel 12 أو بلا صيانة نشطة.

معايير القبول — اكتب docs/10-adr-base-platform.md يحتوي:
- نتائج القياس: زمن إنشاء المستأجر، العزل، الترخيص، نقاط الامتداد، المشاكل.
- القرار: Lunar مؤكد، أو التحويل للبناء من الصفر مع السبب.
- قائمة الإصدارات المعتمدة بالضبط.
- أي تعديل مطلوب على البرومبتات P07–P13 بسبب النتائج.

الخاتمة: فرع spike/base-platform، commit، PR بعنوان "ADR-010: base platform". لا تحذف مجلد spike/ قبل دمج الـ ADR.
```

---

## P02 — تهيئة المشروع والبنية والجودة

```text
اقرأ: docs/10-adr-base-platform.md (الإصدارات المعتمدة)، docs/02-architecture.md §1–2، docs/07-implementation-plan.md §0–1 و SETUP-*.

الهدف: هيكل المشروع النهائي جاهز بجودة إنتاجية وCI أخضر.

المطلوب:
1. مشروع Laravel بالإصدار المعتمد في جذر المستودع (احذف spike/ بعد التأكد أن ADR مدموج).
2. Docker Compose **للتطوير المحلي فقط**: php-fpm (PHP 8.3)، nginx، mysql:8، mailpit. بلا Redis افتراضيًا. الإنتاج على استضافة Hostinger المشتركة (docs/16): QUEUE_CONNECTION=database، CACHE_STORE=database، SESSION_DRIVER=database. الكود لا يعتمد على Redis أو Docker، ويعمل على VPS لاحقًا بتغيير .env فقط. composer scripts للأوامر الشائعة.
3. الجودة: Pint، Larastan level 8، Pest + Arch plugin، ESLint + TypeScript strict للواجهة.
4. CI على GitHub Actions: lint، static analysis، tests على MySQL 8 حقيقي، build للواجهة. Cache للـ composer و npm.
5. هيكل app/Domain/{Identity,Catalog,Inventory,Orders,CallCenter,Fulfillment,Finance,Growth,Support,Notifications,Tenancy,Billing} و app/Support.
6. اختبارات Arch:
   - لا استخدام لنوع float أو دالة floatval داخل app/Domain/Finance و app/Domain/Orders.
   - وحدة لا تستورد Models من وحدة أخرى (المسموح: Actions و Events و Contracts).
   - لا كتابة مباشرة لعمود status في الطلبات خارج app/Domain/Orders/StateMachine (يُضاف فعليًا في P11، جهّز الاختبار معلّقًا).
7. Filament و Inertia + React + TypeScript + Tailwind مع دعم RTL و i18n (ar افتراضي، en).
8. Scheduler (يُشغّل من cron كل دقيقة)، وأمر `queue:cron` يشغّل `queue:work --stop-when-empty --max-time=50` مع قفل يمنع تشغيلين متزامنين. Sentry (معطل محليًا) وسجلات JSON. لا Horizon (يتطلب Redis وعمليات دائمة).
9. ملف CLAUDE.md مختصر (≤ 60 سطرًا) يحتوي القواعد الثابتة:
   - Clean-room: ممنوع أي كود أو أصول من 6Valley.
   - المال: أعداد صحيحة بالقروش عبر App\Support\Money فقط.
   - الحالات: الانتقال عبر OrderTransitionService فقط.
   - الحدود بين الوحدات.
   - كل نص واجهة في lang/.
   - تعريف "تم" من docs/07 §0.2.
   - الأوامر الشائعة.
10. قالب PR، CODEOWNERS، .editorconfig، CHANGELOG.md.

معايير القبول: `docker compose up` ثم الصفحة الرئيسية تعمل؛ `/admin` يفتح Filament بالعربية؛ CI أخضر على PR؛ اختبارات Arch تعمل.

الخاتمة: فرع feat/SETUP-bootstrap، PR. اطلب مني تفعيل حماية main بعد الدمج (أو فعّلها عبر gh إن كان لديك صلاحية، بعد سؤالي).
```

---

## P03 — الأدوات المشتركة (Core)

```text
اقرأ: CLAUDE.md، docs/07-implementation-plan.md (CORE-01..07).

الهدف: أدوات مشتركة صلبة تعتمد عليها كل الوحدات.

المطلوب:
1. App\Support\Money: Value Object غير قابل للتعديل، amount int بالقروش + currency. جمع/طرح/ضرب في int/مقارنة، allocate(ratios) يوزع الباقي، fromDecimalString للمدخلات، تنسيق عربي وإنجليزي. Eloquent Cast (MoneyCast) يخزن BIGINT. يرفض float صراحة.
2. App\Support\Phone\EgyptianPhone: تطبيع (01x، +201x، 00201x، أرقام عربية-هندية، مسافات، شرطات) إلى E.164، تحقق البادئات 010/011/012/015، hash() عبر HMAC-SHA256 بمفتاح من الإعدادات.
3. Idempotency: جدول idempotency_keys + Middleware للـ API يقرأ Idempotency-Key ويعيد نفس الاستجابة + Helper للخدمات.
4. Audit: Trait Auditable + AuditLogger (actor، action، subject، before، after، ip، tenant). جدول audit_logs append-only.
5. ReasonCode enum مركزي (إلغاء، مرتجع، رفض جودة، فشل تسليم) مع label عربي/إنجليزي ونوع.
6. إعدادات مكتوبة بأنواع (spatie/laravel-settings أو مكافئ): كل الإعدادات في كلاسات بقيم افتراضية، لا مفاتيح نصية متفرقة.
7. قاموس المحافظات المصرية (27) والمدن: Seeder بأسماء عربية وإنجليزية، كبيانات مركزية مشتركة يقرأها كل مستأجر.

القيود: تغطية اختبارات ≥ 95% لهذه الأدوات. لا اعتماد على أي وحدة Domain.

معايير القبول:
- Money: allocate(100,[1,1,1]) = [34,33,33]؛ تمرير float يرمي استثناء؛ fromDecimalString("12.5") = 1250.
- EgyptianPhone: جدول ≥ 25 حالة (صحيحة وخاطئة) ينجح.
- طلبان API بنفس Idempotency-Key يعيدان نفس الاستجابة بأثر واحد.

الخاتمة: فرع feat/CORE-shared، PR.
```

---

## P04 — تعدد المستأجرين (Tenancy)

```text
اقرأ: CLAUDE.md، docs/10-adr-base-platform.md، docs/08-saas-direction.md §6 (TEN-*).

اقرأ أيضًا: docs/16-hostinger-and-review-decisions.md و docs/10-adr-base-platform.md (طريقة العزل التي نجحت في P01).

الهدف: منصة متعددة المستأجرين **بقاعدة بيانات واحدة وعزل بـ tenant_id**، تعمل على استضافة Hostinger المشتركة.

المطلوب:
1. stancl/tenancy بالإصدار المعتمد بوضع single-database. جداول المنصة (tenants، domains، مدراء المنصة، plans، subscriptions، المحافظات) بلا tenant_id. **كل** جدول آخر فيه tenant_id بفهرس، ومفاتيح UNIQUE مركبة تبدأ بـ tenant_id (مثل UNIQUE(tenant_id, sku)).
2. Trait BelongsToTenant: Global Scope + ملء tenant_id تلقائيًا عند الإنشاء + رفض الحفظ بلا مستأجر. يُطبق على نماذجنا ونماذج Lunar المستبدلة.
3. تحديد المستأجر بالنطاق الفرعي ({store}.platform.test). الدومين المخصص لاحقًا (P28).
4. عزل: الكاش (prefix بالمستأجر)، الملفات (مجلد لكل مستأجر)، الطوابير (Job يحمل tenant_id ويُهيّئ السياق)، الجلسات، الـ Scheduler (أمر يمر على المستأجرين)، السجلات و Sentry (tenant_id في السياق).
5. ProvisionTenant Job: سجل tenant، seeders (إعدادات، أدوار، بيانات Lunar الأساسية: عملة EGP، لغة ar/en، مجموعة عملاء افتراضية، قناة)، مستخدم مالك. Idempotent وقابل لإعادة التشغيل بعد فشل جزئي.
6. أرشفة وحذف آمن لمستأجر، وتصدير بيانات مستأجر واحد (JSON/SQL مفلتر بـ tenant_id) واستيرادها.
7. اختبار Arch: كل Model خارج قائمة جداول المنصة يستخدم BelongsToTenant.

القيود: لا استعلام خام (DB::table / raw SQL) على جداول المستأجر بدون شرط tenant_id صريح — أضف قاعدة PHPStan أو اختبار يكشفها.

معايير القبول (اختبارات):
- مستأجران؛ بيانات أحدهما غير مرئية للآخر عبر HTTP والطوابير والكاش والملفات، لكل نموذج (اختبار يمر على كل النماذج آليًا).
- ProvisionTenant < 10 ثوانٍ، وإعادة تشغيله بعد فشل مصطنع تكتمل بلا تكرار.
- تصدير مستأجر ثم استيراده في قاعدة نظيفة يعطي نفس البيانات.

الخاتمة: فرع feat/TEN-core، PR.
```

---

## P05 — لوحة المنصة وتسجيل التاجر

```text
اقرأ: CLAUDE.md، docs/08-saas-direction.md (TEN-01, TEN-05).

الهدف: تاجر جديد يسجل بنفسه ويحصل على متجره خلال دقيقة، ومدير المنصة يدير كل التجار.

المطلوب:
1. صفحة هبوط للمنصة (عربي/إنجليزي) + نموذج تسجيل التاجر: الاسم، المتجر، النطاق الفرعي (تحقق التوفر والكلمات المحجوزة)، الهاتف + OTP، البريد، كلمة المرور.
2. بعد التسجيل: ProvisionTenant في الطابور + شاشة "جاري التجهيز" تتحدث تلقائيًا + بريد ترحيب + توجيه لـ لوحة التاجر.
3. لوحة المنصة المركزية (Filament panel: platform): التجار، حالاتهم، الاشتراكات، الاستخدام، إيقاف/تفعيل، "دخول كتاجر" (impersonation) مسجل في Audit ومحدود بوقت.
4. لوحة التاجر (Filament panel: store) داخل سياق المستأجر: لوحة رئيسية فارغة، إعدادات المتجر (الاسم، الشعار، الألوان، اللغة، العملة، التواصل)، الموظفون والأدوار.
5. أدوار التاجر (spatie/permission داخل المستأجر): owner، manager، catalog، orders، finance، support، call_agent. مصفوفة صلاحيات في كلاس واحد.
6. 2FA (TOTP) اختياري للتاجر وإلزامي لمدراء المنصة.
7. Rate limiting على التسجيل والدخول و OTP، و Captcha بعد محاولات فاشلة.

معايير القبول:
- اختبار Feature: تسجيل ← provisioning ← دخول لوحة التاجر ← تعديل الإعدادات.
- impersonation يُسجل ويُنهى تلقائيًا.
- اختبار Policy لكل دور.

الخاتمة: فرع feat/TEN-onboarding، PR.
```

---

## P06 — الخطط والاشتراكات (Billing)

```text
اقرأ: CLAUDE.md، docs/08-saas-direction.md (SAAS-*).

الهدف: خطط بحدود وميزات، واشتراك تجريبي، وفوترة خلف واجهة قابلة للتبديل.

المطلوب:
1. plans (مركزي): الاسم، السعر الشهري/السنوي بـ Money، الحدود (منتجات، طلبات شهرية، أفلييتات، موظفون، تخزين)، الميزات (Feature flags: affiliate، call_center، courier، cod_reconciliation، multi_vendor، custom_domain، api، ai).
2. subscriptions: trial → active → past_due → suspended → cancelled، مع تواريخ وسجل أحداث.
3. Features::enabled($feature) و Limits::check($limit) تُستدعى داخل الخدمات (لا في الواجهة فقط). تجاوز الحد يرمي استثناء مترجم.
4. BillingGateway interface + ManualGateway (تحويل وإيصال يعتمده مدير المنصة) + هيكل لبوابة إلكترونية لاحقًا. ابحث وتحقق من بوابات تدعم الاشتراكات المتكررة في مصر واكتب النتيجة في docs/11-billing-gateways.md بدون تنفيذها الآن.
5. فواتير PDF بالعربية للاشتراك.
6. Jobs: تذكير قبل نهاية التجربة/التجديد، تحويل لـ past_due، إيقاف بعد مهلة. الإيقاف = المتجر يعرض صفحة "مغلق مؤقتًا"، ولا تُحذف البيانات.
7. شاشة "الخطة والاستخدام" في لوحة التاجر.

معايير القبول:
- اختبار: خطة بحد 10 منتجات ⇒ المنتج 11 يُرفض من الخدمة نفسها.
- اختبار: انتهاء التجربة بلا دفع ⇒ past_due ⇒ suspended ⇒ المتجر مغلق والبيانات سليمة ⇒ الدفع ⇒ يعود.

الخاتمة: فرع feat/SAAS-billing، PR. **بوابة: تحقق يدويًا من سيناريو P06 كاملًا قبل المرحلة 1.**
```
