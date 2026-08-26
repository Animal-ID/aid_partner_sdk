# Changelog

Усі суттєві зміни цього пакета документуються в цьому файлі.

Формат базується на [Keep a Changelog](https://keepachangelog.com/uk/1.1.0/),
а версіонування — на [Semantic Versioning](https://semver.org/lang/uk/).

## [Unreleased]

## [1.3.0] — 2026-08-27

### Added

- **Площина заведення** — новий `PlatformClient`, окремий від `PartnerClient`, бо це окремий ключ. На сервері площини резолвлять різні типи застосунку: ключ платформи на `/v1/partner/` віддає `401`, і навпаки. Два клієнти замість одного — щоб їх не можна було переплутати.
  - `clinics()->search()` — знайти клініку, яка вже є, перш ніж створювати другу її копію. `Clinic::isLinked()` каже, чи вона ваша: у чужу потрібен дозвіл директора.
  - `clinics()->provision()` — створити клініку або отримати ту саму за вашим `external_org_id` (повторний виклик безпечний). Клініка не існує без директора, а лікар може керувати лише однією — інакше `ConflictException`.
  - `doctors()->seat()` — завести лікаря в клініку і отримати його ключ. **Приватна половина повертається один раз.**
  - `doctors()->credentials()` — забрати креденшели лікаря, який уже є в Animal ID і **погодився** на передачу. Ключ належить вам і відділений від власного ключа лікаря: коли він відкликає дозвіл, ваш перестає працювати, а його — ні.
  - `consents()->requestKeyHandover()` / `requestClinicMembership()` / `status()` — дозволи, які партнер не може взяти сам. Повторний запит повертає вже відкритий, а не створює другий.
- `DoctorCredentials::toConfig()` — передає приватний ключ із відповіді прямо в `PartnerClient`, не проходячи через ваш код, який міг би його залогувати.
- `ConsentRequest::isUsable()` — не те саме, що статус `approved`: дозвіл має строк, і схвалення річної давнини вже не діє.
- **Вебхуки `consent.approved` / `.denied` / `.revoked` / `.expired`** з типізованими аксесорами (`isConsentRevoked()`, `getConsentId()`, `getConsentKind()`, `getDoctorId()`, `getClinicId()`).
  - Обробляйте `consent.revoked`: відкликання може статися через місяці, ключ помирає тієї ж миті, і без події ви дізнаєтесь про це аж коли впаде наступний виклик.
  - `expired` ≠ `denied`: ніхто не відмовляв, ніхто не глянув. Можна спитати ще раз.

## [1.2.0] — 2026-08-25

### Added

- Ваш власний ідентифікатор власника: поле `external_owner_id` приймається у `owners()->create()` та в inline-власнику при реєстрації тварини, і повертається через `Owner::getExternalOwnerId()` / `AnimalOwner::getExternalOwnerId()` — у `owners()->search()` і в розгортанні `owners` на картці тварини.
  - Записується **один раз**, при першому контакті, і ніколи не перезаписується.
  - **Ізольований по інтеграції**: партнер бачить лише той id, який передав сам, і ніколи не бачить id іншого партнера для тієї ж людини.

## [1.1.0] — 2026-07-07

### Added

- Поле `public_id` у власника: `Owner::getPublicId()` та `AnimalOwner::getPublicId()` — стабільний публічний ідентифікатор власника, що повертається у `owners()->create()`, `owners()->search()` та в розгортанні `owners` на картці тварини.
- Прив'язка наявного власника при реєстрації тварини за `public_id`: елемент `owners[]` тепер приймає `['public_id' => '...']` (поряд із режимом `user_gid` та inline-реєстрацією за email/phone).

### Changed

- SDK за замовчуванням націлений на версію API `2026-07-04` (`Config::DEFAULT_API_VERSION`, заголовок `X-Eternity-Animal-ID-Version`) — з цієї версії власник у реєстрації прив'язується за `public_id` замість `user_gid`. Щоб зберегти попередню поведінку, задайте `api_version` явно (напр. `['api_version' => '2026-05-30']`).

## [1.0.2] — 2026-07-02

### Changed

- Задокументовано актуальну форму відповіді `POST /animals/{id}/procedures`: API тепер повертає ту саму partner-картку процедури, що й `GET` (`type` / `occurred_at` / `visit_id` / `type_specific_payload`, публічний `animal_id`). `Procedure::fromArray()` і надалі нормалізує застарілу внутрішню форму (`procedure_type_id` / `performed_at` / `extra_fields`) для сумісності зі старими версіями gateway — поведінка SDK не змінилася.
- Пакетний запис процедур масивом (`procedures()->create($id, [...])`) працює на актуальному gateway: виправлено ваду API, через яку тіло-масив відхилялося з помилкою 422 "The procedures field is required" (вада була на боці сервера, не SDK).

## [1.0.1] — 2026-06-25

### Added

- Запити доступу до тварини: `animals()->requestAccess($id)` (POST) і `animals()->accessStatus($id)` (GET) з типізованим станом `Model\AnimalAccessRequest` (`granted`/`pending`/`denied`/`none`, `retryAfterSeconds`, `isGranted()` тощо).
- Прапорці доступу на картці тварини: `Animal::getAbilities()` і зручний `Animal::canEdit()` (`abilities.can_edit`).
- Розгортання `owners` для пошуку тварин через `$expand` (`AnimalsResource::EXPAND_OWNERS`); `Animal::getOwners()` повертає `Model\AnimalOwner[]` з `is_main_owner`.
- Прийом вебхуків: `Webhook\WebhookVerifier` (`constructEvent()` — перевірка підпису + timestamp, `verify()`, `parse()`) і `Webhook\WebhookEvent` із типізованими аксесорами для подій `animal_access.*`. Захист від replay (толеранс за замовчуванням 300с, налаштовуваний). `Exception\WebhookVerificationException`.

## [1.0.0] — 2026-06-13

### Added

- Перший реліз SDK для Partner Integration API (Stage 1), PHP 7.3+.
- `PartnerClient` — фасад із групами ресурсів: `dictionaries()`, `owners()`, `animals()`, `procedures()`, `photos()`.
- HMAC-SHA256 підпис запитів (`X-Eternity-App-Id`, `X-Eternity-Public-Key`, `X-Eternity-Timestamp`, `X-Eternity-Signature`) — `Auth\RequestSigner`.
- Автоматична генерація `X-Eternity-Idempotency-Key` (UUID v4) для `POST`/`PATCH`/`DELETE` з можливістю передати власний ключ для безпечних повторів.
- Підтримка заголовка версії API `X-Eternity-Animal-ID-Version` через опцію `api_version`.
- Словники з ETag-кешуванням (`If-None-Match` → 304, `DictionarySet::isNotModified()`).
- Власники: реєстрація (`POST /owners`) і пошук за email/телефоном (`GET /owners/search`).
- Тварини: реєстрація, картка, пошук за ідентифікатором (типізований і наскрізний), пошук за власником, часткове оновлення.
- Процедури: запис однієї або пакета (до 100), історія з фільтрами `type`/`since`/`until`, одна процедура за id; константи типів `TYPE_*`.
- Фото: завантаження `multipart/form-data` (підпис порожнього тіла за контрактом API; kind: avatar/gallery/nose_print) і видалення.
- Типізовані DTO-моделі (`Owner`, `Animal`, `Procedure`, `ProcedureBatch`, `Photo`, `Dictionary*`) з доступом до сирого payload через `toArray()`.
- Типізовані винятки за HTTP-статусами зі спільним маркером `PartnerSdkException`; `ValidationException::getErrors()` з помилками по полях.
- Транспорт на нативному cURL без зовнішніх залежностей; підміняється через `HttpClientInterface`.
- Тести PHPUnit 9 (108 тестів, покриття ~99% рядків), перевірено на PHP 7.3 та 8.3.
- `Dockerfile` для запуску composer/phpunit без локального PHP.

[Unreleased]: https://github.com/animalid/partner-sdk/compare/v1.0.2...HEAD
[1.0.2]: https://github.com/animalid/partner-sdk/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/animalid/partner-sdk/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/animalid/partner-sdk/releases/tag/v1.0.0
