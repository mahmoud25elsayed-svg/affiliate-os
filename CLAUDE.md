# قواعد وإرشادات تطوير AffiliateOS

## 1. القواعد المعمارية الثابتة (Non-Negotiable Rules)
1. **Clean-room Architecture:** يُحظر تمامًا نسخ أي كود، ملف، أصل، أو تصميم من 6Valley.
2. **المال بالأعداد الصحيحة:** لا استخدام لـ `float` في المال. التعامل بالقروش عبر `App\Support\Money`.
3. **انتقال الحالات:** لا تعديل مباشر لـ `orders.status`. جميع الانتقالات تمر حصرًا عبر `OrderTransitionService`.
4. **حدود الوحدات (Domain Boundaries):** لا تستورد أي وحدة Domain نماذج Eloquent لوحدة أخرى مباشرة (التواصل عبر Actions، Events، Contracts، DTOs).
5. **التعريب والواجهة:** كل نص يظهر للمستخدم يجب أن يكون في `lang/ar.json` أو ملفات الترجمة.

## 2. تعريف "تم" (Definition of Done)
- [ ] الكود يحقق معايير القبول المكتوبة في البرومبت.
- [ ] الاختبارات الآلية (`composer test`) تمر بنجاح (تغطية منطق الأعمال).
- [ ] الفحص الساكن (`composer types` Larastan Level 8) يمر بدون أي خطأ.
- [ ] التنسيق (`composer lint` Pint و `npm run lint`) يمر بدون مخالفات.
- [ ] فحص الأنواع وبناء الواجهة (`npm run types` و `npm run build`) يمر بنجاح.
- [ ] مسار الـ CI على GitHub أخضر، وفرع بميزة نظيفة وتسمية قياسية.

## 3. الأوامر الشائعة (Common Commands)
- تشغيل البيئة: `docker compose up -d` أو `composer dev`
- الاختبارات: `composer test` أو `./vendor/bin/pest`
- فحص الأنواع: `composer types` (PHPStan Level 8) و `npm run types`
- فحص التنسيق: `composer lint` و `composer lint:fix`
- بناء الواجهة: `npm run build`
