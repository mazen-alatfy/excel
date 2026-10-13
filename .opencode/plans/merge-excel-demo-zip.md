# دمج `laravel-excel-demo.zip` — Application 1 (uploader) + Application 2 (receiver)

## القرار النهائي (مؤكد من المستخدم)

| الدور | المجلد | البورت | الـ Endpoints |
|---|---|---|---|
| **Application 1 = uploader** | `D:\excel-app-1` | `8000` | `POST /api/contacts/upload`, `GET /api/contacts` (proxy) |
| **Application 2 = receiver** | `D:\excel` | `8001` | `POST /api/contacts/bulk`, `GET /api/contacts` |

- مصدر الكود: `C:\Users\Mazen\AppData\Local\Temp\opencode\excel-demo\` (مفكوكة من `C:\Users\Mazen\Downloads\laravel-excel-demo.zip`).
- الأسلوب: **استبدال كامل** للكود القديم بالكود الجديد من الزيب (مسح `POST /api/import` + `ContactService::sendToApp2` + `ApiResponse` usage).
- "Application 1 / 2" مجرد تسمية شغل — لا تغيير في `APP_NAME` أو الكود.
- الاتنين Laravel 13 + PHP 8.4.24، `api:` routing مفعّل في `bootstrap/app.php` في الاتنين.

---

## الخطوات

### Step 0 — تجهيز `D:\excel-app-1` (حسب الـ AGENTS بتاعه)

```sh
# prerequisites متحقق منها أصلاً: PHP 8.4.24 / Composer 2.9.5
composer require laravel/boost --dev
php artisan boost:install -n          # هيكتب AGENTS.md/CLAUDE.md/الـ skills/الـ MCP
# ثم إعادة قراءة AGENTS.md الجديد والمتابعة ب הקוاليتي الجديدة

composer require maatwebsite/excel    # بدون --dev (استخدام runtime) — بيجيب PhpSpreadsheet معاه
```

> ملاحظة: `maatwebsite/excel` غير موجود في `excel-app-1` حاليًا (`vendor\phpoffice\phpspreadsheet` = False)، وملزم لأنه الكود بيردّ عليه.

### Step 1 — Application 2 = Receiver في `D:\excel`

1. **استبدال** `app/Http/Controllers/ContactController.php` بكود الزيب: `bulkStore()` (validate `contacts.*` + `DB::transaction` + `updateOrCreate` على `email` + عدّاد created/updated + 201) و `index()` (count + data).
2. **استبدال** `routes/api.php`:
   ```php
   Route::post('/contacts/bulk', [ContactController::class, 'bulkStore']);
   Route::get('/contacts', [ContactController::class, 'index']);
   ```
   (يتمسح `Route::post('/import', ...)`).
3. **استبدال** `app/Models/Contact.php` بنسخة الزيب: `$fillable = ['name', 'email', 'phone', 'age']` مع الـ timestamps الافتراضية (القديم كان `$timestamps = false`).
4. **تعديل migration** `database/migrations/2026_09_19_165750_create_contacts_table.php` ليطابق مخطط الزيب:
   - `email` → `->unique()`
   - `phone` → `string('phone', 30)->nullable()` (كان NOT NULL)
   - `age` → `unsignedSmallInteger('age')->nullable()` (كان string NOT NULL)
5. **معالجة الـ DB**: جدول `contacts` الحالي في MySQL `excel` فيه 6 صفوف بـ 3 إيميلات مكررة ومفيش unique index → بعد تعديل الـ migration نشغّل:
   ```sh
   php artisan migrate:fresh
   ```
   (آمن: `users`/`sessions`/`personal_access_tokens` = 0 صفوف، البيانات الوحيدة هي 6 صفوف تجريبية مكررة).
6. **حذف الملفات اللي بقت مش مستخدمة**: `app/Services/ContactService.php` و `app/Imports/ContactsImport.php`.
7. **ن keeping** `maatwebsite/excel` في `composer.json` (ممنوع إزالة deps بدون موافقة — هيفضل مركب وماتستخدمش) و`app/Helpers/ApiResponse.php` (helper عام).

### Step 2 — Application 1 = Uploader في `D:\excel-app-1`

1. **استبدال** `app/Http/Controllers/ContactController.php` بكود الزيب: `upload()` (validate `file` mimes xlsx/xls → `Excel::toArray` → فلتر rows فيها name+email → `Http::post` لـ `receiverUrl('/api/contacts/bulk')` مع try/Catch `ConnectionException` → 502) و `index()` (proxy للـ receiver) و `receiverUrl()` helper.
2. **إضافة** `app/Imports/ContactsImport.php` **مع تصحيح إلزامي** (الكود الأصلي في الزيب فيه bug):
   ```php
   // الزيب: implements WithHeadingRow فقط → TypeError
   // لازم:
   class ContactsImport implements Import, WithHeadingRow {}
   ```
   السبب: في `maatwebsite/excel 4.0.3` الـ `Excel::toArray(Import $import, ...)` تاخد واجهة `Import` بس، و`WithHeadingRow` **مش بيرثها** (تم التحقق: `is_a(WithHeadingRow, Import)` = false). من غير الإصلاح: `TypeError` عند الرفع.
3. **استبدال** `routes/api.php`:
   ```php
   Route::post('/contacts/upload', [ContactController::class, 'upload']);
   Route::get('/contacts', [ContactController::class, 'index']);
   ```
   (يتمسح `Route::post('/upload', ...)`).
4. **إضافة** في `config/services.php`:
   ```php
   'receiver' => ['url' => env('RECEIVER_URL', 'http://127.0.0.1:8001')],
   ```
5. **إضافة** `RECEIVER_URL=http://127.0.0.1:8001` في `.env` و `.env.example`.
6. **إضافة** `<env name="RECEIVER_URL" value="http://127.0.0.1:8001"/>` في `phpunit.xml` (عشان الاختبارات تقرأ الـ config).
7. **حذف** `app/Services/ContactService.php` (الـ `sendToApp2` القديم).

### Step 3 — الاختبارات

**`D:\excel` (Pest — `php artisan make:test --pest ContactBulkTest`):**
- `POST /api/contacts/bulk` ببيانات صحيحة → 201 + created = N + الصفوف في الـ DB.
- إعادة إرسال نفس الإيميل → 201 + updated + الصفوف لسه واحدة (updateOrCreate).
- إيميل غير صالح / `contacts` فاضي → 422.
- `GET /api/contacts` → 200 + count + data.
- يستخدم `RefreshDatabase` في أول الملف (الـ `Pest.php` الـ RefreshDatabase معلّق) — الـ DB في الاختبارات sqlite `:memory:` أصلاً.

**`D:\excel-app-1` (PHPUnit عادي — مفيش Pest هنا → `php artisan make:test ContactUploadTest` class-based):**
- يولّد ملف xlsx مؤقت بـ PhpSpreadsheet داخل الاختبار (header: name/email/phone/age + صفّين).
- `Http::fake()` على `RECEIVER_URL` → `POST /api/contacts/upload` → 200 + `Http::assertSent` بـ payload `{contacts: [...]}` على `http://127.0.0.1:8001/api/contacts/bulk`.
- ملف ناقص / mime غلط → 422.
- `GET /api/contacts` → بيرجّع رد الـ receiver المزوّر.
- `ConnectionException` → 502 (opitional تغطية).

### Step 4 — التحقق النهائي

1. `vendor/bin/pint --dirty --format agent` في `D:\excel` (مطلوب في AGENTS.md بتاعه) + `vendor/bin/pint` في `excel-app-1`.
2. `php artisan test --compact` في الاتنين.
3. **E2E حقيقي** (سيرفرين شغّالين):
   ```sh
   # Terminal 1 (receiver)
   cd D:\excel        && php artisan serve --port=8001
   # Terminal 2 (uploader)
   cd D:\excel-app-1  && php artisan serve --port=8000
   ```
   - `curl -F "file=@C:\Users\Mazen\Downloads\contacts.xlsx" http://127.0.0.1:8000/api/contacts/upload` → `rows_sent: 12` + `receiver_response.created`.
   - `curl http://127.0.0.1:8001/api/contacts` → count/data.
   - `curl http://127.0.0.1:8000/api/contacts` → proxy شغّال.
   - رفع نفس الملف تاني → `created: 0, updated: 12`.
   - إيقاف السيرفرات بعد الانتهاء.

---

## مخاطر / ملاحظات

- `migrate:fresh` بيمسح قاعدة `excel` المحلية (6 صفوف تجريبية مكررة بس، باقي الجداول فاضية) — لو عايز نحافظ على البيانات بديل: migration تاني يمسح الدوبليكيت ويعدّل الأعمدة بدل `fresh`.
- تثبيت Boost في `excel-app-1` مكتوب في AGENTS.md بتاعه ويضيف dev dependency + يعيد كتابة AGENTS.md/CLAUDE.md — يحتاج network/_packagist. يقدر المستخدم يلغاه لو مش عايزه.
- `maatwebsite/excel` هيفضل مركب في `D:\excel` بدون استخدام (بدون إزالة deps).
- تصحيح `ContactsImport` (خطوة 2.2) هو الـ deviation الوحيد عن الكود الحرفي للزيب — الكود الأصلي فيه TypeError.

## ملفات ستتغير

**`D:\excel`**: `app/Http/Controllers/ContactController.php` (replace), `routes/api.php` (replace), `app/Models/Contact.php` (replace), `database/migrations/2026_09_19_165750_create_contacts_table.php` (edit), `app/Services/ContactService.php` (delete), `app/Imports/ContactsImport.php` (delete), `tests/Feature/ContactBulkTest.php` (new).

**`D:\excel-app-1`**: `composer.json` (+boost, +maatwebsite/excel), `AGENTS.md`/`CLAUDE.md` (boost), `app/Http/Controllers/ContactController.php` (replace), `app/Imports/ContactsImport.php` (new, fixed), `routes/api.php` (replace), `config/services.php` (+receiver), `.env`/`.env.example` (+RECEIVER_URL), `phpunit.xml` (+RECEIVER_URL env), `app/Services/ContactService.php` (delete), `tests/Feature/ContactUploadTest.php` (new).
