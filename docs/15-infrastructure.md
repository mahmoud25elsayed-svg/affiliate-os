# توثيق البنية التحتية والنشر والنسخ الاحتياطي (Infrastructure, Deployment & Backups)

> **المرحلة 5 — الإنتاج والإطلاق (P36)**  
> **تاريخ التدقيق الفعلي:** 2026-10-29  
> **حساب الاستضافة:** Hostinger Business Shared Hosting (`u747110105` - Order ID: `1006644429`)  
> **تنبيه:** لا يتم شراء أو إنشاء أي موارد مدفوعة دون موافقة صريحة من المالك.

---

## 1. تدقيق إمكانيات استضافة Hostinger Business (Live API Audit)

تم استخراج هذه الإمكانيات والحدود مباشرة عبر واجهة برمجة تطبيقات Hostinger الرسمية لضمان الدقة:

| الخاصية / المورد | القيمة الفعلية | التفاصيل والتأثير على المعمارية |
|---|---|---|
| **نوع الخطة** | `hostinger_business` | استضافة سحابية مشتركة قائمة على CloudLinux و LiteSpeed Web Server. |
| **نسخة PHP** | **PHP 8.3.36** | إصدار حديث ومستقر؛ متوفر دعم ترقية مستقبلي حتى PHP 8.5. |
| **Zend OPcache** | **مفعّل (On)** | مفعّل للويب والـ CLI؛ استهلاك الذاكرة 256MB والحد الأقصى للملفات المسرّعة 16,229 ملفاً. |
| **ذاكرة PHP (Memory Limit)** | **512MB (قابلة للرفع حتى 2048MB)** | كافية تماماً لتنفيذ استعلامات التجارة الإلكترونية وتوليد التقارير. |
| **الحد الأقصى لوقت التنفيذ** | **360 ثانية** | ملائم للعمليات الخلفية وأوامر التهجير والنسخ الاحتياطي. |
| **امتدادات PHP الحرجة** | **جميعها مفعّلة** | `bcmath` (للحسابات المالية بدون كسور), `nd_pdo_mysql`, `intl`, `gd`, `imagick`, `curl`, `pcntl`, `posix`, `sockets`, `igbinary`, `zip`, `fileinfo`. |
| **نظام قواعد البيانات** | **MySQL 8.0** | الحد الأقصى لحجم كل قاعدة بيانات هو **3072 MB (3 GB)**. حتى 100 قاعدة بيانات. الاتصال المحلي عبر `127.0.0.1:3306` أو `localhost`. |
| **معمارية المستأجرين (Tenancy)** | **قاعدة بيانات موحدة (Single-DB Tenancy)** | بسبب قيود الاستضافة المشتركة في إنشاء قواعد بيانات برمجياً، تعمل جميع المتاجر في قاعدة واحدة بعزل حازم عبر عمود `tenant_id` وفهارس مركبة. |
| **حدود المعالجة المتزامنة** | **30 Entry Processes (EP) / 100 Nproc** | سقف الإنتاجية حوالي 150 طلب/ثانية للصفحات غير المكشّشة قبل حدوث اصطفاف. |
| **حد عدد الملفات (Inodes)** | **600,000 Inode** | يتطلب تنظيفاً دورياً لملفات الجلسات والكاش المؤقت وسجلات النظام. |
| **النطاقات والـ Wildcard SSL** | **مدعومة بالكامل** | دعم نطاق فرعي مجمع `*.affiliate-os.com` مع شهادة Let's Encrypt مجانية مجمعة عند ربط DNS الخادم. |
| **الوصول عبر SSH و Composer** | **متاح ومفعّل** | منفذ SSH رقم `65002`، مع توفر Composer 2 و Git CLI في سطر الأوامر. |

---

## 2. معمارية البيئات (Staging & Production)

تم عزل بيئتي التجربة (Staging) والإنتاج (Production) بالكامل لضمان عدم تأثير التجارب أو اختبارات الحمل على الإنتاج:

```
/home/u747110105/
  ├── domains/
  │   ├── staging.affiliate-os.com/
  │   │   ├── .env                      # إعدادات Staging وقاعدة u747110105_affiliate_stg
  │   │   ├── storage/                  # ملفات ومرفقات Staging المستقلة
  │   │   ├── releases/                 # إصدارات Staging المؤرخة
  │   │   │   └── 20261029100000/
  │   │   └── current -> releases/20261029100000
  │   │
  │   └── affiliate-os.com/
  │       ├── .env                      # إعدادات الإنتاج وقاعدة u747110105_affiliate_prod
  │       ├── storage/                  # ملفات الإنتاج وسجلات النظام المعتمدة
  │       ├── releases/                 # إصدارات الإنتاج المؤرخة (حفظ آخر 5 إصدارات)
  │       │   ├── 20261029090000/
  │       │   └── 20261029103000/
  │       ├── current -> releases/20261029103000   # الرابط الرمزي الذري النشط
  │       └── public_html -> domains/affiliate-os.com/current/public
```

### معايير الأمان الإلزامية للملفات:
1. ملف `.env` يبقى **خارج مجلد الويب العام `public_html` نهائياً** وبصلاحية `0600`.
2. مجلد `storage/` دائم ولا يتم حذفه أو استبداله أثناء النشر، بل يرتبط رمزياً بكل إصدار.
3. جذر الويب (Web Root) في Hostinger يشير إلى `current/public` حصراً.

---

## 3. آلية النشر دون توقف (Zero-Downtime Atomic Deployment)

تستخدم منصة AffiliateOS أسلوب النشر الذري المماثل لـ Capistrano و Envoy، حيث يتم تجهيز الإصدار بالكامل في مجلد مؤرخ ثم تبديل الرابط الرمزي `current` في كسر من الثانية:

```
[1. بناء محلي / CI]
  ├── npm ci && npm run build
  └── composer install --no-dev --optimize-autoloader

[2. رفع الإصدار إلى الخادم]
  └── rsync إلى releases/YYYYMMDDHHIISS/

[3. ربط الملفات الدائمة وتشغيل الهجرات]
  ├── ln -sfn ../../.env .env
  ├── ln -sfn ../../storage storage
  ├── php artisan migrate --force
  └── php artisan optimize

[4. التبديل الذري (Atomic Swap)]
  ├── ln -sfn releases/YYYYMMDDHHIISS current_tmp
  └── mv -Tf current_tmp current

[5. إعادة تشغيل الطابور والتحقق]
  ├── php artisan queue:restart
  └── فحص GET /health (التراجع الفوري إذا فشل)
```

### سكريبتات النشر المعتمدة:
- سكريبت النشر الذري: [`scripts/deploy/deploy.sh`](file:///scripts/deploy/deploy.sh)
- سكريبت التراجع الفوري: [`scripts/deploy/rollback.sh`](file:///scripts/deploy/rollback.sh)

### تشغيل النشر:
```bash
# للنشر على بيئة Staging
./scripts/deploy/deploy.sh staging

# للنشر على بيئة الإنتاج
./scripts/deploy/deploy.sh production

# للتراجع الفوري في حال حدوث أي طارئ
./scripts/deploy/rollback.sh production
```

### مخطط مسار GitHub Actions (Blueprint):
```yaml
# يُشغَّل Staging عند الدمج في main، و Production عند إنشاء tag v*
name: Deploy
on:
  push:
    branches: [main]
    tags: ['v*']

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP & Node
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      - name: Build & Deploy
        env:
          SSH_KEY: ${{ secrets.HOSTINGER_SSH_KEY }}
        run: |
          ./scripts/deploy/deploy.sh staging
```

---

## 4. جدول المهام الدورية على Hostinger (Cron Jobs)

نظراً لعدم وجود خدمة دائمة (Daemon) مثل Supervisor في الاستضافة المشتركة، يتم تشغيل الجدولة ومعالجة الطوابير عبر نظام cron الخاص بـ Hostinger:

| التوقيت | الأمر التنفيذي | الغرض والوظيفة |
|---|---|---|
| `* * * * *` (كل دقيقة) | `cd /home/u747110105/domains/affiliate-os.com/current && php artisan schedule:run >> /dev/null 2>&1` | تشغيل جدولة المهام الدورية (النبض، التسويات، التحليلات). |
| `* * * * *` (كل دقيقة) | `cd /home/u747110105/domains/affiliate-os.com/current && php artisan queue:cron --stop-when-empty >> /dev/null 2>&1` | تشغيل عامل الطوابير ذي الأولوية (`finance`, `notifications`, `courier`, `default`) مع حد أقصى 55 ثانية لمنع التداخل. |

---

## 5. استراتيجية النسخ الاحتياطي واستعادة البيانات

راجع الدليل التشغيلي المفصل: [`docs/runbooks/backup-and-restore.md`](file:///docs/runbooks/backup-and-restore.md)

### المكونات الأساسية:
1. **أمر النسخ الاحتياطي المدمج:**  
   `php artisan app:backup-database --encrypt --disk=s3 --retention-days=14`
2. **التشفير الإلزامي:**  
   تشفير كامل لقاعدة البيانات عبر خوارزمية **AES-256-CBC** مع توثيق **HMAC-SHA256** لمنع التلاعب وتوليد ملف بصمة **SHA-256** بجانب كل نسخة.
3. **أرشفة المرفقات:**  
   ضغط وأرشفة مجلد `storage/app/public` الحاوي على وثائق التجار والمستندات الرسمية.
4. **التدريب الشهري على Staging:**  
   تنفيذ محاكاة استعادة كاملة أول يوم أحد من كل شهر والتحقق من أن `ledger:reconcile` يخرج بصفر فروقات.

---

## 6. نقطة مراقبة سلامة النظام والجاهزية (`/health`)

تم تفعيل نقطة فحص مركزية [`/health`](file:///app/Http/Controllers/HealthCheckController.php) مفتوحة للأنظمة الخارجية مثل UptimeRobot و BetterUptime دون الحاجة لمصادقة، لتقييم المكونات التالية:

1. **قاعدة البيانات (`database`):** التحقق من سلامة الاتصال، وزمن الاستجابة (Latency بالملي ثانية).
2. **طوابير المهام (`queue`):** التحقق من عدد المهام المعلقة، وعمر أقدم مهمة غير منفذة:
   - سليم: `< 60 ثانية`.
   - متدهور: `60 - 120 ثانية`.
   - غير سليم (حرج): `> 120 ثانية` (يُرجع HTTP 503 فوراً ويطلق تنبيهاً حرجاً).
3. **نبض الجدولة (`scheduler`):** التحقق من تشغيل `schedule:run` خلال آخر دقيقتين.
4. **مساحة التخزين (`storage`):** التحقق من النسبة المتبقية لمساحة القرص:
   - تنبيه عند بقاء `< 15%`.
   - حالة حرجة عند بقاء `< 5%`.
5. **سلامة الدفاتر وتفعيل المتاجر (`platform`):** التنبيه في حال وجود أي فروقات مالية ناتجة عن `ledger:reconcile`، أو تعثر تهيئة أي متجر جديد لأكثر من 15 دقيقة.

---

## 7. خطة الانتقال إلى خادم مخصص (Hostinger VPS Migration Plan)

تم تصميم التطبيق وفق مبدأ القابلية الكاملة للنقل (Portability)، بحيث لا يعتمد الكود المصدري على أي مزود استضافة محدد.

### مؤشرات وقواعد اتخاذ قرار الانتقال (Migration Triggers):
يتم الانتقال إلى خادم Hostinger VPS (KVM 4) عند تحقق أي من الشروط التالية:
1. تجاوز عدد التجار النشطين **50 متجراً نشطاً**.
2. زيادة زمن اصطفاف المهام في الطابور عن **120 ثانية** بشكل متكرر.
3. تجاوز سقف العمليات المتزامنة في الاستضافة المشتركة (30 Entry Processes) مما يتسبب في أخطاء HTTP 503 أو 508.
4. الحاجة لدعم عدد كبير من النطاقات المخصصة التلقائية وتثبيت شهادات SSL فورية لها عبر Let's Encrypt و Caddy/Nginx.

### مواصفات خادم VPS المقترح:
- **الخطة:** Hostinger KVM 4 VPS
- **المعالج والذاكرة:** 4 vCPUs / 16 GB RAM / 200 GB NVMe Storage
- **نظام التشغيل:** Ubuntu 24.04 LTS

### المعمارية التقنية بعد الانتقال:
| الخدمة | على الاستضافة المشتركة | على الخادم الافتراضي (VPS) |
|---|---|---|
| **خادم الويب** | LiteSpeed Shared | Nginx + Laravel Octane (FrankenPHP) |
| **محرك الكاش** | `file` / `database` | `redis` (Redis 7.x) |
| **محرك الطوابير** | `database` (بواسطة cron كل دقيقة) | `redis` مدعوماً بـ **Laravel Horizon** و **Supervisor** كعمليات دائمة |
| **إدارة العمليات** | CloudLinux LVE | `systemd` + `supervisor` |
| **الدومينات المخصصة** | يدوياً عبر hPanel | أوتوماتيكياً عبر Caddy مع Let's Encrypt On-Demand TLS |

### خطوات الانتقال الفعلية (Zero-Data-Loss Migration Steps):
1. **تجهيز خادم الـ VPS:** تثبيت Nginx, PHP 8.3, Redis, Supervisor, MySQL 8, و Caddy.
2. **تجهيز مسار المشروع:** استنساخ المستودع وضبط الصلاحيات.
3. **تفعيل وضع الصيانة على الاستضافة المشتركة:** `php artisan down`.
4. **أخذ نسخة نهائية لقاعدة البيانات ومجلد التخزين:**
   ```bash
   php artisan app:backup-database --skip-storage
   rsync -avz /home/u747110105/domains/affiliate-os.com/storage/ root@vps_ip:/var/www/affiliate-os/storage/
   ```
5. **استيراد البيانات على الـ VPS:** استيراد النسخة لقاعدة بيانات MySQL على الـ VPS.
6. **تحديث ملف `.env` على الـ VPS لتفعيل Redis و Horizon:**
   ```dotenv
   QUEUE_CONNECTION=redis
   CACHE_STORE=redis
   SESSION_DRIVER=redis
   OCTANE_SERVER=frankenphp
   ```
7. **تشغيل الخدمات:**
   ```bash
   php artisan optimize
   supervisorctl reread && supervisorctl update && supervisorctl start horizon
   ```
8. **تحويل مسار DNS (Cutover):** تغيير عنوان A-Record في Cloudflare / Hostinger ليشير إلى عنوان IP الخادم الافتراضي الجديد.
9. **إلغاء وضع الصيانة:** `php artisan up`.

