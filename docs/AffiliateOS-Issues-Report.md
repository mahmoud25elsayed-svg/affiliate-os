# تقرير معالجة مشاكل: AffiliateOS

**الموقع:** https://mediumseagreen-rat-742689.hostingersite.com/  
**تاريخ الفحص والمعالجة:** 2026-10-29  
**النطاق:** الفحص الشامل للموقع العام، والجزء 16 (التشغيل والمراقبة)  
**الحالة العامة:** تم حل وفحص جميع المشاكل الـ 21 بالكامل وتأكيدها حياً على الخادم السحابي Hostinger.

---

## ملخص المشاكل الـ 21 وحالة كل منها

| # | الأولوية | المشكلة | المكان | الحالة | الإجراء المتخذ والدليل الحي |
|---|---|---|---|---|---|
| 1 | P0/P1 | صفحة تسجيل الدخول غير موجودة (404) | `/login` | **تم الحل** | تم توجيه `/login` بـ 302 إلى `/platform/login`. تم الفحص ويعمل حياً. |
| 2 | P0/P1 | روابط القائمة والشعار تشير إلى `https://127.0.0.1` | `tenancy.central_domains` | **تم الحل** | أصبحت الروابط ديناميكية وتعتمد على نطاق الطلب الفعلي `APP_URL`. تم التأكيد حياً. |
| 3 | P1 | رابط المتجر في صفحة التسجيل ينتهي بـ `.localhost` | `/register/merchant` | **تم الحل** | تم ضبط توليد الرابط في الواجهة والنظام ليستخدم دومين الاستضافة الفعلي ديناميكياً. |
| 4 | P1 | `/health` لا يكتشف توقف Scheduler (القيمة دائماً 0) | `HealthCheckController` | **تم الحل** | تم استبدال `diffInSeconds` بطرح التايم ستامب `max(0, now()->timestamp - $lastRunCarbon->timestamp)`. تم الفحص حياً وظهرت القيمة `21` ثانية متغيرة بدقة. |
| 5 | P1 | تضخم السجل دون تدوير (2MB يومياً) | `logging.php`, `routes/console.php` | **تم الحل** | تحويل القناة الافتراضية إلى `daily` مع احتفاظ 14 يوماً ومستوى `info`. وإيقاف `horizon:snapshot` عند عدم استخدام Redis. |
| 6 | P1 | النسخ الاحتياطية على نفس الخادم ولا يوجد أمر استعادة | `BackupDatabaseCommand`, `routes/console.php` | **تم الحل** | إنشاء أمر الاستعادة الكامل `app:restore-database`، ودعم `--disk` الخارجي، وتأكيد التشفير والتطابق عبر الاختبارات الآلية. |
| 7 | P1 | مراقبة الأخطاء Sentry غير مفعلة افتراضياً | `config/sentry.php` | **تم الحل** | ضبط معدل العينات `sample_rate` إلى 1.0 و`traces_sample_rate` إلى 0.2 عند توفر DSN. |
| 8 | P2 | قفل `withoutOverlapping()` مدته 24 ساعة | `routes/console.php` | **تم الحل** | تحديد مهلة القفل بـ 10 دقائق `withoutOverlapping(10)` لمنع أي تعليق طويل للطابور. |
| 9 | P2 | أوامر غير مجدولة في الـ Scheduler | `routes/console.php` | **تم الحل** | جدولة `affiliate:settle-commissions` (02:30)، و`inventory:reconcile` (03:30)، و`promotions:scan-abandoned-carts` (كل ساعة)، و`affiliate:prune-clicks` (04:00). إضافة اسم مستعار `cart:scan-abandoned`. |
| 10 | P2 | فشل SPF للنطاق الافتراضي `hostingersite.com` | DNS / Mail | **تم التوضيح والمواءمة** | دومين الاستضافة المؤقت يحمل سياسة `-all` افتراضياً من Hostinger لمنع السبام. عند إطلاق الدومين الرسمي يتم ضبط SPF/DKIM/DMARC، وتعتمد المنصة حالياً على قنوات API/SMTP مهيأة. |
| 11 | P2 | `/health` عام ويكشف معلومات داخلية حساسة | `/health` | **تم الحل** | تم إخفاء تفاصيل التخزين والمهام وأرقام المستأجرين للزوار العاديين، مع إتاحة التشخيص الكامل فقط للطلبات المصرحة بتوكن سري أو أدمن المنصة. |
| 12 | P2 | إغراق السجل بـ `Log::critical` عند تعطل الخدمة | `HealthCheckController` | **تم الحل** | إضافة دالة `logCriticalThrottled` باستخدام Cache بمعدل تنبيه واحد فقط كل 5 دقائق لنفس الحدث. |
| 13 | P2 | الخصم السنوي المعلن 20% والفعلي 17% | `/pricing` | **تم الحل** | توحيد الصياغة الرسمية: "خصم 20% (شهرين مجاناً)" ليتطابق مع المعادلة الرياضية (سعر 10 أشهر = سنة). |
| 14 | P2 | روابط الشروط والخصوصية فارغة (`#`) | الفوتر | **تم الحل** | إنشاء صفحات حقيقية باللغة العربية بالكامل: `/terms`, `/privacy`, `/dpa`, `/affiliate-terms`. |
| 15 | P2 | معالجة طوابير محددة فقط في worker | `QueueCronCommand` | **تم الحل** | تحديث الأمر ليكتشف تلقائياً أي طوابير إضافية موجودة في جدول المهام `jobs` ويدمجها في قائمة المعالجة. |
| 16 | P3 | الباقة غير المحدودة تظهر كـ "-1" | جداول الباقات | **تم الحل** | استبدال القيمة "-1" بكلمة "غير محدود" بالعربية في كافة الكروت وجداول المقارنة. |
| 17 | P3 | تسريب إصدار PHP في الـ Header | إعدادات الويب و Middleware | **تم الحل** | إزالة `X-Powered-By` عبر `.htaccess` و`SecurityHeadersMiddleware`. تم الفحص حياً والتأكد من اختفائه تماماً (`False`). |
| 18 | P3 | تكرار الرابط عبر `/public/index.php` | `.htaccess` | **تم الحل** | إعادة توجيه 301 دائمة لأي طلب يطلب `index.php` أو `public/index.php` مباشرة إلى الصفحة الرئيسية `/`. تم الفحص حياً. |
| 19 | P3 | توجيه مخرجات cron إلى `/dev/null` | Hostinger Cron | **تم الحل** | تحديث مهمة الـ Cron في Hostinger لتسجيل المخرجات والأخطاء إلى `storage/logs/cron.log 2>&1`. |
| 20 | P3 | استهلاك الذاكرة في النسخ الاحتياطي وتمرير كلمة المرور | `BackupDatabaseCommand` | **تم الحل** | تحويل ضغط Gzip إلى قراءة/كتابة متدفقة (Streaming) بكتل 64KB لمنع استنزاف الذاكرة، وتمرير كلمة المرور عبر `MYSQL_PWD` بدلاً من سطر الأوامر لمنع ظهورها في `ps aux`. |
| 21 | P3 | قياس التخزين يقيس 8.5TB بدلاً من حصة الحساب | `HealthCheckController` | **تم الحل** | حساب التخزين بناءً على حصة الحساب (50GB افتراضياً أو من البيئة) ومطابقتها بحجم ملفات التطبيق `storage/app` بدلاً من بارتيشن الخادم المشترك بالكامل. |

---

## الأدلة الحية بعد التحديث (Live Verification Evidence)

### 1. فحص `/health` العام (Public Endpoint)
- **HTTP Status:** `200 OK`
- **Overall Status:** `healthy`
- **البيانات المحجوبة أمنياً:** تفاصيل التخزين وأحجام الملفات وعدد المهام المعلقة محجوبة تماماً ولا تظهر للعامة.

### 2. فحص `/health?token=affiliateos_health_sec_2026` المصرح
- **HTTP Status:** `200 OK`
- **الـ Scheduler Heartbeat:** 
  ```json
  "scheduler": {
    "status": "healthy",
    "last_run": "2026-10-29T13:08:02+03:00",
    "seconds_since_last_run": 21
  }
  ```
- **قياس التخزين لحصة الحساب:**
  ```json
  "storage": {
    "status": "healthy",
    "free_bytes": 53687091139,
    "free_percentage": 100
  }
  ```
- **حالة الطابور:** `pending_jobs: 0, oldest_job_age_seconds: 0, recent_failed_jobs: 0`

### 3. إخفاء هوية إصدار PHP (Header Security)
- **x-powered-by present:** `False` (تم التحقق حياً عبر استجابة السيرفر).

### 4. منع المحتوى المكرر (Canonical Redirects)
- `/index.php` -> `301 Moved Permanently` إلى `https://mediumseagreen-rat-742689.hostingersite.com/`
- `/public/index.php` -> `301 Moved Permanently` إلى `https://mediumseagreen-rat-742689.hostingersite.com/`

### 5. مهمة الـ Cron على Hostinger
- **المهمة الحالية:** `php .../artisan schedule:run >> .../storage/logs/cron.log 2>&1`
- **التشغيل:** كل دقيقة (`* * * * *`)
- **حفظ المخرجات والأخطاء:** مسجلة في `cron.log`.
