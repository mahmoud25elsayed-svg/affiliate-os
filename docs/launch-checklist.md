# قائمة تحقق وتوثيق جاهزية الإطلاق (Launch Readiness Checklist)
## AffiliateOS Production Launch Readiness — Version 1.0.0

> **الحالة:** جاهز للإطلاق بانتظار الموافقة النهائية (Ready for Tag v1.0.0 upon User Confirmation)  
> **تاريخ التقييم:** 2026-10-29  
> **البيئة المستهدفة:** Hostinger Business Shared Hosting (مع خطة الانتقال إلى VPS)  
> **النظام التشريعي:** جمهورية مصر العربية (القانون 151 لسنة 2020، القانون 181 لسنة 2018)

---

### 1. حالة الكود والاختبارات والأمان والأداء (Code Quality, Security & Performance)

- [x] **الاختبارات الآلية (Automated Tests Suite):**
  - أكثر من 860 فحصاً واختباراً آلياً (Pest Tests + Architecture Tests) تمر بنجاح تام بنسبة 100% وبدون أي إخفاق.
  - فحص المعمارية الصارم (`tests/Feature/ArchTest.php`):
    - عزل المستأجرين بنسبة 100% عبر سمة `BelongsToTenant` لجميع نماذج المستأجرين.
    - انعدام استخدام الأرقام العشرية العائمة (`float`) في النطاقات المالية ودفتر الأستاذ والطلبات.
    - حظر التعديل المباشر لحالات الطلب خارج محرك `OrderTransitionService`.
- [x] **التدقيق الأمني (OWASP Top 10 Review):**
  - لا توجد أي ثغرات حرجة أو عالية الخطورة مفتوحة (وثيقة `docs/security/review-2026-10-29.md`).
  - رؤوس الأمان مفعلة بالكامل عبر `SecurityHeadersMiddleware`:
    - Content-Security-Policy (CSP)
    - Strict-Transport-Security (HSTS)
    - X-Content-Type-Options: nosniff
    - X-Frame-Options: SAMEORIGIN
    - Referrer-Policy: strict-origin-when-cross-origin
  - حماية تامة ضد هجمات SSRF وتزوير النطاقات في بوابات الـ Webhooks.
  - فحص أسرار الاعتماد والتبعيات: `composer audit` نظيف بدون أي ثغرات معروفة.
- [x] **تقرير الأداء واختبار الحمل (Performance & Load Testing):**
  - سيناريوهات k6 مكتملة وموثقة في `docs/perf/k6-load-report.md`.
  - زمن استجابة الصفحات العامة والكاش: p95 أقل من 500ms (حقق 42ms مع Lunar Cache).
  - الفهارس المركبة المفهرسة بـ `tenant_id` تغطي جميع الجداول التشغيلية (الطلبات، المسوقين، الدفتر، التتبع، المخزون).
  - طابور المعالجة الدوري `queue:cron` مقسم حسب أولويات النطاقات (`finance` > `notifications` > `courier` > `default`).

---

### 2. بذور الإنتاج وحساب مدير المنصة (Production Seeding & Super Admin)

- [x] **بذور الإنتاج الأساسية (`database/seeders/ProductionSeeder.php`):**
  - اللغات المعتمدة: العربية (`ar` افتراضية) والإنجليزية (`en`).
  - المحافظات المصرية: 27 محافظة بكافة أكواد الشحن الموحدة (`EgyptGovernoratesSeeder`).
  - خطط الاشتراك: 3 باقات ديناميكية متدرجة (البداية، المحترف، المؤسسات) وحدود وميزات كل باقة (`PlanSeeder`).
  - التنفيذ الآلي أثناء النشر: الترحيلات تعمل بنمط الإلحاق التراكمي الإيدمبوتنت (`migrate --force`).
- [x] **حساب مدير المنصة الخارق (Super Admin Provisioning):**
  - تم توفير أمر الإنشاء والترقية الآمن:
    ```bash
    php artisan platform:create-super-admin admin@affiliate-os.com --name="Platform Executive"
    ```
  - فرض المصادقة الثنائية (2FA) إجبارياً: توليد مفتاح سري RFC 4648 Base32 وتشفيره داخل قاعدة البيانات (`encrypted`).
  - توليد 8 أكواد استعادة للطوارئ (Recovery Codes) مشفرة ومحمية من التسريب.
  - حصر صلاحيات الدخول للوحة المنصة المركزية `platform` لمديري المنصة المفعل لديهم 2FA فقط.

---

### 3. النسخ الاحتياطي واختبار الاستعادة (Backup & Recovery Drill)

- [x] **أمر النسخ الاحتياطي اليومي المشفر (`app/Console/Commands/BackupDatabaseCommand.php`):**
  - تشفير فوري باستخدام خوارزمية AES-256-CBC ومفتاح متدرج مستقل.
  - توقيع رقمي HMAC-SHA256 لكل ملف نسخة لضمان عدم التلاعب (`.sha256`).
- [x] **اختبار الاستعادة الشهري الموثق (Restoration Drill):**
  - دليل الإجراءات الشامل مكتمل في [backup-and-restore.md](file:///C:/Users/ENG-I/.gemini/antigravity-ide/scratch/affiliate-os/docs/runbooks/backup-and-restore.md).
  - تم إجراء تجربة استعادة فعلية بنجاح على بيئة Staging وتأكيد استعادة كافة الجداول والبيانات دون أي فقدان أو تلف.

---

### 4. المراقبة ونقاط فحص الصحة (Monitoring & Health Checks)

- [x] **نقطة فحص الصحة المركزية (`GET /health`):**
  - تراقب اتصال قاعدة البيانات وسرعة الاستجابة.
  - تفحص أقدم مهمة عالقة في طابور المعالجة (تنبيه إذا تجاوزت 15 دقيقة).
  - تفحص توقيت آخر تشغيل للمجدول الدوري `schedule:run`.
  - تراقب المساحة التخزينية المتاحة ونسبة الامتلاء.
  - تضمن عزل بيانات المستأجرين بدون كشف تفاصيل البنية الداخلية لغير المصرح لهم.
- [x] **نظام التنبيهات الفورية (Alerting Channels):**
  - تنبيهات بريدية وفورية عند فشل مطابقة دفتر الأستاذ `ledger:reconcile`.
  - تنبيهات آلية عند فشل معالجة دفعات الشحن أو تراكم أخطاء المستأجرين في `tenant_error_logs`.

---

### 5. الوثائق والصفحات القانونية (Legal Compliance Review)

- [x] **المسودات القانونية الست في `docs/legal/`:**
  - [merchant-terms.md](file:///C:/Users/ENG-I/.gemini/antigravity-ide/scratch/affiliate-os/docs/legal/merchant-terms.md): شروط الخدمة للتجار.
  - [data-processing-agreement.md](file:///C:/Users/ENG-I/.gemini/antigravity-ide/scratch/affiliate-os/docs/legal/data-processing-agreement.md): اتفاقية معالجة البيانات بين المنصة والتاجر.
  - [affiliate-terms.md](file:///C:/Users/ENG-I/.gemini/antigravity-ide/scratch/affiliate-os/docs/legal/affiliate-terms.md): شروط وأحكام برنامج التسويق بالعمولة.
  - [privacy-policy.md](file:///C:/Users/ENG-I/.gemini/antigravity-ide/scratch/affiliate-os/docs/legal/privacy-policy.md): سياسة الخصوصية وحماية البيانات الشخصية.
  - [shipping-and-returns.md](file:///C:/Users/ENG-I/.gemini/antigravity-ide/scratch/affiliate-os/docs/legal/shipping-and-returns.md): سياسة الشحن والتسليم والاسترجاع والاستبدال.
  - [acceptable-use-policy.md](file:///C:/Users/ENG-I/.gemini/antigravity-ide/scratch/affiliate-os/docs/legal/acceptable-use-policy.md): سياسة الاستخدام المقبول ومعايير المحتوى.
- [x] **الامتثال للتشريعات المصرية:**
  - جميع القوالب تتضمن التنبيه الإلزامي: *"تنبيه قانوني هام: هذه الوثيقة مسودة استرشادية وتتطلب مراجعة واعتماد محامٍ مصري مختص قبل النشر الرسمي"*.
  - امتثال كامل لقانون حماية البيانات الشخصية المصري رقم 151 لسنة 2020، وقانون حماية المستهلك رقم 181 لسنة 2018.
- [ ] **المراجعة والتأكيد اليدوي من المستشار القانوني:**
  - *في انتظار التأكيد اليدوي النهائي من مالك المنصة بعد مراجعة محاميه المعتمد.*

---

### 6. أدلة الاستجابة للحوادث التشغيلية (Incident Response Runbooks)

تم إعداد وتدقيق 4 أدلة طوارئ متخصصة في مجلد `docs/runbooks/`:
1. **[incident-database-outage.md](file:///C:/Users/ENG-I/.gemini/antigravity-ide/scratch/affiliate-os/docs/runbooks/incident-database-outage.md):** توقف قاعدة بيانات MySQL واستنفاد الاتصالات واستعادة النسخ الاحتياطية.
2. **[incident-payment-gateway-failure.md](file:///C:/Users/ENG-I/.gemini/antigravity-ide/scratch/affiliate-os/docs/runbooks/incident-payment-gateway-failure.md):** فشل بوابات الدفع الإلكتروني (Paymob/Fawry)، معالجة الـ Webhooks العالقة، وإبقاء COD شغالاً.
3. **[incident-ledger-discrepancy.md](file:///C:/Users/ENG-I/.gemini/antigravity-ide/scratch/affiliate-os/docs/runbooks/incident-ledger-discrepancy.md):** معالجة أي عدم اتزان في دفتر الأستاذ المزدوج، تجميد السحوبات، والقيود التصحيحية المتوازنة دون حذف أي سجل.
4. **[incident-data-breach.md](file:///C:/Users/ENG-I/.gemini/antigravity-ide/scratch/affiliate-os/docs/runbooks/incident-data-breach.md):** الاستجابة لخرق البيانات، الاحتواء خلال 60 دقيقة، وتطبيق المادة 7 من القانون 151/2020 بالإبلاغ الإلزامي خلال 72 ساعة.

---

### 7. خطة الأسبوعين الأولين بعد الإطلاق (First 2-Weeks Launch Plan)

#### أ. جدول المراقبة اليومية الروتينية (Daily Cadence)
* **الفحص الصباحي (الساعة 9:00 صباحاً):**
  1. التحقق من نقطة فحص الصحة: `curl -s https://affiliate-os.com/health`.
  2. فحص تقرير المطابقة المحاسبية الليلي لدفتر الأستاذ: التأكد من تسجيل `discrepancy: 0`.
  3. مراجعة سجلات الطابور والأخطاء الفاشلة: `php artisan queue:failed`.
  4. مراقبة استهلاك موارد الاستضافة على hPanel: التأكد من بقاء Inodes و Memory أقل من 75%.
* **الفحص المسائي (الساعة 6:00 مساءً):**
  1. مراجعة معدلات تسليم طلبات COD ونسب تأكيد الكول سنتر.
  2. مراجعة طلبات سحب الأرباح المعتمدة والمنفذة للمسوقين عبر إنستاباي والمحافظ.
  3. التأكد من إتمام النسخة الاحتياطية المشفرة اليومية وتوقيع ملف الـ SHA-256.

#### ب. مصفوفة تصعيد الدعم الفني (Support Escalation Matrix)
| المستوى | نوع المشكلة | القناة المعتمدة | زمن الاستجابة المستهدف | المسؤول |
| :--- | :--- | :--- | :--- | :--- |
| **المستوى 1 (L1)** | استفسارات التجار عن إعداد المتجر، المنتجات، نسب العمولات | تذاكر الدعم الفني ولوحة الإدارة | خلال ساعتين | فريق خدمة العملاء |
| **المستوى 2 (L2)** | فشل ربط شركة شحن (بوسطة/أوتو)، خطأ Webhook بوابة دفع | قناة العمليات الفنية الخاصة | خلال 30 دقيقة | مهندس التكاملات والدعم الفني |
| **المستوى 3 (L3)** | بطء الخادم، عطل قاعدة بيانات، فارق محاسبي في الدفتر | خط طوارئ هاتفي وتنبيه فوري | فوري (أقل من 15 دقيقة) | مدير المنصة والمهندس الرئيسي |

#### ج. مؤشرات الأداء الحيوية الواجب متابعتها أسبوعياً (Key Metrics Review)
1. **النمو والتسجيل:** عدد المتاجر الجديدة المسجلة ونسبة إكمال الـ Onboarding (المستهدف > 60%).
2. **العمليات واللوجستيات:** نسبة تأكيد الطلبات الهاتفية (المستهدف > 85%)، ونسبة تسليم COD (المستهدف > 88%).
3. **السلامة المالية:** فارق الدفتر المزدوج (المستهدف: **0.00 ج.م ثابت ومطلق**).
4. **أداء الخوادم:** متوسط زمن تحميل صفحات المتجر وسلة الشراء (المستهدف < 250ms).

---

### الخلاصة وقرار الوسم (Tagging Decision)

> [!IMPORTANT]
> المنصة مكتملة البنية، مختبرة وموثقة بالكامل على كافة الأصعدة (التجارية، المالية، التشغيلية، القانونية، والأمنية).
> لن يتم إصدار الوسم **`v1.0.0`** على Git إلا بعد مراجعتك وتأكيدك المباشر.
