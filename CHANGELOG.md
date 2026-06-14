# Changelog

Усі суттєві зміни цього пакета документуються в цьому файлі.

Формат базується на [Keep a Changelog](https://keepachangelog.com/uk/1.1.0/),
а версіонування — на [Semantic Versioning](https://semver.org/lang/uk/).

## [Unreleased]

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

[Unreleased]: https://github.com/animalid/partner-sdk/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/animalid/partner-sdk/releases/tag/v1.0.0
