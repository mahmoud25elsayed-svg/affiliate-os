# دليل التعامل مع الحوادث: توقف قاعدة البيانات (Database Outage)
## Runbook: Incident Response — MySQL Database Outage & Recovery

> **درجة الخطورة:** حرجة (Sev-1 Critical)  
> **زمن الاستجابة المستهدف (MTTR):** أقل من 15 دقيقة  
> **الفريق المسؤول:** مهندس العمليات الأساسي (Platform Lead / DevOps)

---

### 1. الأعراض ومصادر الرصد (Detection & Symptoms)

يتم اكتشاف توقف أو تعطل قاعدة البيانات عبر إحدى الإشارات التالية:
1. **فشل فحص الجاهزية:** استجابة نقطة الفحص `GET /health` بكود `503 Service Unavailable` أو `500 Server Error` وفشل بند `database.status: ok`.
2. **سجلات الأخطاء المركزية:** ظهور متكرر لرسائل `PDOException`:
   * `SQLSTATE[HY000] [2002] Connection refused`
   * `SQLSTATE[08004] [1040] Too many connections`
   * `SQLSTATE[HY000] [2006] MySQL server has gone away`
3. **توقف طابور المعالجة:** توقف أو فشل الأمر `queue:cron` وظهور استثناءات متكررة في `storage/logs/laravel.log`.

---

### 2. إجراءات الاحتواء الفوري (Immediate Containment)

1. **تفعيل وضع الصيانة المؤقت للمنصة** لمنع تضرر معاملات المستخدمين أثناء الإصلاح:
   ```bash
   php artisan down --render="errors.503" --secret="emergency-bypass-2026"
   ```
2. **فحص حالة خادم MySQL على Hostinger Business:**
   * الدخول إلى لوحة التحكم `hPanel` ← **Databases** ← فحص حالة قاعدة الإنتاج `uXXXXXX_affiliate_prod`.
   * الاتصال عبر SSH بالخادم وتشغيل فحص اتصال سريع:
     ```bash
     mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" -h "$DB_HOST" -e "SELECT 1;"
     ```

---

### 3. التشخيص وتحديد السبب الجذري (Diagnosis & Root Cause)

#### الحالة أ: استهلاك كافة الاتصالات المسموحة (`Too many connections`)
* **السبب:** تراكم اتصالات عالقة (Sleeping Connections) ناتجة عن طوابير طويلة أو استعلامات بطيئة غير مفهرسة.
* **الإجراء:**
  1. الاستعلام عن الاتصالات النشطة:
     ```sql
     SHOW PROCESSLIST;
     ```
  2. إنهاء الاتصالات النائمة العالقة لأكثر من 60 ثانية:
     ```sql
     KILL <process_id>;
     ```
  3. التأكد من إيقاف أي عمليات اختبار حمل أو كشط بيانات غير مرخص.

#### الحالة ب: امتلاء مساحة التخزين أو سقف الـ Inodes
* **السبب:** امتلاء مساحة حساب الاستضافة المشتركة أو وصول عدد الملفات لسقف الـ Inodes مما يمنع MySQL من إنشاء جداول مؤقتة.
* **الإجراء:**
  1. فحص استهلاك المساحة والملفات عبر SSH:
     ```bash
     df -h
     find . -type f | wc -l
     ```
  2. تنظيف ملفات الكاش القديمة والسجلات غير الضرورية:
     ```bash
     rm -rf storage/logs/*.log.* storage/framework/cache/data/*
     ```

#### الحالة ج: تلف في جداول البيانات (Table Corruption)
* **السبب:** إغلاق مفاجئ للخادم أو انقطاع غير متوقع أثناء كتابة معاملة ضخمة.
* **الإجراء:**
  1. فحص سلامة الجداول:
     ```sql
     CHECK TABLE lunar_orders, ledger_entries, platform_visits;
     ```
  2. في حال وجود عطب في محرك InnoDB، يجب إعادة تشغيل الخادم في وضع استعادة `innodb_force_recovery = 1` عبر دعم Hostinger.

---

### 4. استعادة الخدمة (Service Recovery)

1. **التحقق من عمل الخادم واستجابة الاستعلامات:**
   ```bash
   php artisan db:monitor
   ```
2. **الاستعادة من أحدث نسخة احتياطية مشفرة (في حال تعذر إصلاح البيانات الحالية):**
   * اتبع خطوات الدليل [backup-and-restore.md](file:///C:/Users/ENG-I/.gemini/antigravity-ide/scratch/affiliate-os/docs/runbooks/backup-and-restore.md).
   * فك تشفير أحدث نسخة احتياطية يومية وتشغيل الأمر:
     ```bash
     openssl enc -d -aes-256-cbc -in backup.enc -out restore.sql -pass file:secret.key
     mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" < restore.sql
     ```
   * تنفيذ الترحيلات المعلقة إن وجدت:
     ```bash
     php artisan migrate --force
     ```
3. **تشغيل فحص مطابقة دفتر الأستاذ المحاسبي فور الاستعادة:**
   ```bash
   php artisan ledger:reconcile
   ```
4. **إعادة تشغيل المنصة وإنهاء وضع الصيانة:**
   ```bash
   php artisan up
   ```
5. **التحقق من عودة مؤشرات الصحة إلى الوضع الطبيعي:**
   ```bash
   curl -s -i https://affiliate-os.com/health | grep "HTTP/2 200"
   ```

---

### 5. ما بعد الحادثة (Post-Incident Review)

1. توثيق زمن التوقف بدقة وتاريخ ووقت البداية والاستعادة في سجل الحوادث `docs/security/incident-log.md`.
2. إرسال إشعار توضيحي للتجار المتأثرين في حال تجاوز التوقف 10 دقائق.
3. مراجعة الاستعلامات البطيئة في `storage/logs/` وإضافة أي فهارس مركبة مفقودة.
