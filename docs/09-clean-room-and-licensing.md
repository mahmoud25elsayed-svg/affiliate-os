# 09 — القرار النهائي: بناء نظيف (Clean-room) لمنصة SaaS

## القرار (ADR-009)

| البند | القرار |
|---|---|
| المنتج | منصة SaaS: كل تاجر يحصل على متجر كامل + برنامج أفلييت + وحدات تشغيل اختيارية |
| أساس الكود | **Laravel 12 + Lunar (MIT) + Filament + stancl/tenancy (قاعدة بيانات لكل تاجر)** |
| 6Valley | **لا يدخل منه أي ملف أو قالب أو أصل أو مخطط جداول** في هذا المستودع. يُستخدم فقط كقائمة ميزات مرجعية |
| البديل إن فشل Spike لـ Lunar | Bagisto (النواة MIT) بشرط عدم الاعتماد على إضافات مدفوعة بشروط غير واضحة |

**لماذا Lunar:** ترخيص MIT بلا إضافات مدفوعة، لوحة إدارته Filament (نفس أداة لوحاتنا)، و"headless" فنتحكم بالكامل في واجهة المتجر والـ SaaS. **ثمنه:** نبني واجهة المتجر بأنفسنا. تُتحقق صلاحيته في البرومبت `P01`.

## قواعد الـ Clean-room

1. لا يُفتح كود 6Valley أثناء كتابة كود هذا المستودع. المرجع الوحيد: قائمة الميزات في [08 §4](08-saas-direction.md) ووثائق 6Valley العامة.
2. لا نسخ لنصوص الواجهة أو التصميم أو الأيقونات أو أسماء الجداول.
3. كل ميزة تُكتب من مواصفاتنا (هذه الوثائق) ومن وثائق Lunar/Laravel.
4. سجل Git يبقى دليلًا على أن الكود كُتب داخل المستودع تدريجيًا.
5. اسم وهوية بصرية مختلفان تمامًا.

## رسالة للدعم (اختيارية — لاستخدام نسختك المشتراة في متجر خاص بك)

> Hello 6amTech team,
> I purchased 6valley Multivendor eCommerce (CodeCanyon, v16.5), license type: [Regular / Extended].
> 1. May I run it as a multi-tenant SaaS where independent merchants each get a store and pay me a subscription?
> 2. If yes, which license is required, and is it per tenant/domain or per platform?
> 3. May I heavily modify the source for that purpose?
> Please reply in writing. Thank you.

حتى لو جاء الرد بالإيجاب، **هذا المستودع يبقى نظيفًا**. أي استخدام لـ 6Valley يكون في مستودع aiSSS المنفصل.
