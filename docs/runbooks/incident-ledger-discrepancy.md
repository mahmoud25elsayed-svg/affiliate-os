# دليل التعامل مع الحوادث: عدم اتزان دفتر الأستاذ المحاسبي (Ledger Discrepancy)
## Runbook: Incident Response — Double-Entry Ledger Imbalance & Reconciliation

> **درجة الخطورة:** حرجة للغاية (Sev-1 Critical — Financial Integrity Violation)  
> **زمن الاستجابة المستهدف:** أقل من 20 دقيقة  
> **الفريق المسؤول:** المسؤول المالي ومهندس الأنظمة الأساسية (Lead Financial Engineer)

---

### 1. المبدأ الأساسي غير القابل للتفاوض (Zero Float & Absolute Balance)

في منصة AffiliateOS، يُعد دفتر الأستاذ المحاسبي المزدوج (`ledger_entries` و `ledger_lines`) الحقيقة المالية المطلقة.
* لا توجد كسور عشرية (جميع المبالغ بوحدات القروش الصحيحة Minor Units).
* مجموع القيود المدينة (Debits) يجب أن يساوي دائماً وبدقة 100% مجموع القيود الدائنة (Credits) لكل قيد ولكل حساب ولكل مستأجر.
* **يحظر تماماً تعديل أو حذف أي سجل محاسبي سابق في قاعدة البيانات (Immutability Principle).**

---

### 2. الأعراض ومصادر الرصد (Detection & Symptoms)

1. **فشل الفحص الليلي الآلي للمطابقة (`ledger:reconcile`):** خروج الأمر بكود خطأ `1` وظهور تنبيه:
   `CRITICAL: Ledger reconciliation failed for tenant [X]. Discrepancy detected: Y minor units.`
2. **فارق في رصيد محفظة المسوق أو التاجر:** عدم تطابق رصيد المحفظة المحسوب في `affiliate_wallets.balance_minor` مع مجموع القيود الدائنة ناقص المدينة في جدول `ledger_lines`.
3. **عدم توازن أسطر القيد الواحد:** وجود سجل في `ledger_entries` يختلف فيه مجموع `ledger_lines.debit_minor` عن مجموع `ledger_lines.credit_minor`.

---

### 3. إجراءات الاحتواء الفوري (Immediate Containment)

1. **إيقاف تنفيذ طلبات السحب (Freeze Payouts):**
   * إيقاف معالجة سحب الأرباح تلقائياً للمتجر المتأثر لمنع تسريب أي مبالغ حتى انتهاء التدقيق:
   ```bash
   php artisan config:set tenancy.payouts_frozen=true
   ```
2. **عزل وتحديد هوية المستأجر المتأثر:**
   * تشغيل فحص المطابقة التفصيلي لكافة المستأجرين:
   ```bash
   php artisan ledger:reconcile --verbose
   ```

---

### 4. التحقيق الجنائي المالي (Forensic Root-Cause Analysis)

1. **البحث عن القيود غير المتوازنة (Unbalanced Entries):**
   ```sql
   SELECT 
       e.id AS entry_id,
       e.tenant_id,
       e.reference,
       e.created_at,
       SUM(l.debit_minor) AS total_debits,
       SUM(l.credit_minor) AS total_credits,
       (SUM(l.debit_minor) - SUM(l.credit_minor)) AS discrepancy
   FROM ledger_entries e
   JOIN ledger_lines l ON l.ledger_entry_id = e.id
   WHERE e.tenant_id = '<TENANT_ID>'
   GROUP BY e.id, e.tenant_id, e.reference, e.created_at
   HAVING discrepancy != 0;
   ```

2. **البحث عن قيود الأرصدة المعلقة غير المتطابقة مع دورة الشحن:**
   * فحص ما إذا تم إطلاق عمولة مسوق لشحنة تم إلغاؤها بعد التأكيد أو إرجاعها دون تسجيل قيد استرداد العمولة (Reversal Entry).
   ```sql
   SELECT o.reference, o.status, e.id AS entry_id, e.event_type
   FROM lunar_orders o
   JOIN ledger_entries e ON e.reference = o.reference
   WHERE o.tenant_id = '<TENANT_ID>' AND o.status IN ('cancelled', 'returned');
   ```

---

### 5. المعالجة والتصحيح القياسي (Remediation & Correction)

1. **إنشاء قيد تسوية مالي متوازن تعويضي (Compensating Balanced Entry):**
   * وفق معايير المحاسبة المزدوجة، لا يُعدل القيد الخاطئ الأصلي أبداً، بل يُنشأ قيد تصحيحي جديد متوازن بالكامل يحمل نوع الحدث `COMPENSATING_ADJUSTMENT`.
   * يجب أن يوثق القيد اسم المسؤول الذي اعتمده وسبب التسوية الدقيق ورقم التذكرة أو الحادثة:
     ```php
     // Example via LedgerService:
     $ledgerService->recordBalancedEntry([
         'tenant_id' => $tenantId,
         'event_type' => 'COMPENSATING_ADJUSTMENT',
         'reference' => 'AUDIT-ADJ-'.now()->format('YmdHis'),
         'description' => 'تصحيح محاسبي معتمد للفارق المكتشف بحادثة #INC-89',
         'lines' => [
             ['account' => 'affiliate_commissions_payable', 'debit' => $diffMinor, 'credit' => 0],
             ['account' => 'merchant_clearing', 'debit' => 0, 'credit' => $diffMinor],
         ],
     ]);
     ```
2. **إعادة تشغيل فحص المطابقة الشامل للتأكد من زوال أي فوارق:**
   ```bash
   php artisan ledger:reconcile --tenant=<TENANT_ID>
   ```
   * التأكد من خروج الأمر بالنتيجة: `All tenant ledgers balanced. Total discrepancy: 0.`

3. **إلغاء تجميد طلبات السحب:**
   * إعادة تفعيل صرف الأرباح بعد التأكد التام من سلامة الموازين المحاسبية.

---

### 6. التوثيق والتدقيق اللاحق (Audit Trail)

1. تسجيل الواقعة في سجل التدقيق المحاسبي للمنصة `docs/compliance/financial-audits.md`.
2. مراجعة كود الانتقال المالي المسبب وإضافة اختبار وحدة مانع للتكرار (Regression Test).
