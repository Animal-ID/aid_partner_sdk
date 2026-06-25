# Animal ID — Partner SDK для PHP

`animalid/partner-sdk` — офіційний PHP SDK для **Partner Integration API** сервісу [Animal ID](https://animal-id.net). Пакет бере на себе всю технічну рутину інтеграції:

- **HMAC-SHA256 підпис** кожного запиту (заголовки `X-Eternity-*`) — ви ніколи не працюєте з криптографією вручну;
- **Ідемпотентність** — для кожного `POST`/`PATCH`/`DELETE` автоматично генерується UUID-ключ `X-Eternity-Idempotency-Key` (або використовується ваш — для безпечних повторів);
- **Типізовані моделі** відповідей (`Owner`, `Animal`, `Procedure`, …) з автодоповненням в IDE;
- **Типізовані винятки** за HTTP-статусами (`ValidationException`, `NotFoundException`, …);
- **ETag-кешування** словників (`If-None-Match` → 304).

Покривається весь Stage 1 API: словники, власники, тварини, процедури, фото.

## Вимоги

- PHP **7.3+** (включно з 8.x)
- розширення `ext-curl` та `ext-json`
- жодних інших залежностей

## Встановлення

```bash
composer require animalid/partner-sdk
```

Якщо пакет розповсюджується з приватного репозиторію, додайте його в `composer.json` свого проєкту:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/animalid/partner-sdk" }
    ]
}
```

## Швидкий старт

```php
<?php

use AnimalId\PartnerSdk\Config;
use AnimalId\PartnerSdk\PartnerClient;

require __DIR__ . '/vendor/autoload.php';

// Ключі ви отримуєте у профілі: «Редагувати профіль» → вкладка «API keys».
$client = new PartnerClient(new Config(
    'aid_app_xxx',   // App ID
    'pk_xxx',        // публічний ключ
    'sk_xxx'         // приватний ключ (зберігайте в секретах, не в коді!)
));

// Знайти тварину за номером мікрочипа
$animals = $client->animals()->findByIdentifier('microchip', '900263000123456');

foreach ($animals as $animal) {
    echo $animal->getNickname(), ' (', $animal->getId(), ')', PHP_EOL;
}
```

### Налаштування (необов'язково)

```php
$config = new Config(
    'aid_app_xxx',
    'pk_xxx',
    'sk_xxx',
    'https://gw.animal-id.net',      // базовий URL шлюзу (default)
    [
        'api_version' => '2026-05-30', // зафіксувати версію API (заголовок X-Eternity-Animal-ID-Version)
        'timeout' => 15,               // загальний таймаут запиту, сек (default 30)
        'connect_timeout' => 5,        // таймаут з'єднання, сек (default 10)
    ]
);
```

---

## Приклади використання

### Словники (довідкові значення)

Коди видів, статей, країн, мов тощо — використовуються як значення у write-запитах.

```php
// Усі словники одразу
$set = $client->dictionaries()->all();

// Лише потрібні, з проєкцією назв на одну локаль
$set = $client->dictionaries()->all(['species', 'sex'], null, 'uk');

$species = $set->get('species');
foreach ($species->getItems() as $item) {
    // getName() повертає назву локаллю з фолбеком на англійську
    echo $item->getCode(), ' — ', $item->getName('uk'), PHP_EOL; // 3 — Собаки
}

// Пошук елемента за кодом (для countries код — рядок "804")
$ukraine = $set->get('countries')->findByCode('804');
echo $ukraine->getAlpha2(); // UA

// Кешування через ETag: передайте etag з попередньої відповіді —
// якщо нічого не змінилось, сервер відповість 304 і трафік не витрачається.
$cachedEtag = $set->getEtag();
$fresh = $client->dictionaries()->all(null, null, null, $cachedEtag);
if ($fresh->isNotModified()) {
    // використовуйте свою закешовану копію
}
```

### Власники

```php
use AnimalId\PartnerSdk\Exception\NotFoundException;

// Реєстрація власника (ідемпотентна: існуючий власник буде знайдений за email/phone).
// Обов'язково: email АБО phone + згода consent.account_creation = true.
$owner = $client->owners()->create([
    'email' => 'jane@example.com',
    'phone' => '+380681234567',
    'first_name' => 'Jane',
    'last_name' => 'Doe',
    'language' => 'uk',
    'country' => '804', // ISO 3166-1 numeric у вигляді рядка (словник countries)
    'consent' => [
        'account_creation' => true, // власник погодився на створення акаунта
    ],
]);

echo $owner->getUserGid();      // 90231 — глобальний id, передається у реєстрацію тварини
echo $owner->hasAccount();      // чи вже має робочий акаунт
echo $owner->getDisplayHint();  // маскована назва без PII, напр. "Ол*** К."

// Пошук власника за точним email або телефоном (одне поле — формат визначається автоматично)
try {
    $owner = $client->owners()->search('jane@example.com');
} catch (NotFoundException $e) {
    // власника з таким email/телефоном не існує
}
```

### Тварини

```php
use AnimalId\PartnerSdk\Resource\AnimalsResource;

// Реєстрація тварини. Повертає публічний id (NanoID) — використовуйте його в усіх подальших викликах.
$animalId = $client->animals()->create([
    'species' => 3,                      // словник species
    'is_microchip' => true,              // true → microchip обов'язковий
    'microchip' => '900263000123456',
    'nickname' => 'Барсік',
    'gender_id' => 1,                    // словник sex
    'breed' => 'Labrador',               // вільний текст
    'color' => 'black',                  // вільний текст
    'dob' => '2022-03-01T00:00:00+00:00',
    'sterilization' => true,
    'owners' => [
        ['user_gid' => 90231],           // прив'язати існуючого власника...
        [                                // ...або зареєструвати нового "інлайн"
            'email' => 'jane@example.com',
            'first_name' => 'Jane',
            'consent' => ['account_creation' => true],
        ],
    ],
    'identifiers' => [                   // додаткові ідентифікатори (словник other_identifiers)
        ['type' => 3, 'value' => 'TAT-001', 'added_at' => '2026-05-01T00:00:00+00:00'],
    ],
]);

// Картка тварини
$animal = $client->animals()->get($animalId);
echo $animal->getNickname();   // Барсік
echo $animal->isLost();        // чи оголошена загубленою
echo $animal->isDeceased();    // чи зафіксована смерть

// Пошук за конкретним типом ідентифікатора (microchip або qr_tag) — повертає масив
$found = $client->animals()->findByIdentifier(AnimalsResource::IDENTIFIER_MICROCHIP, '900263000123456');

// Пошук за значенням одночасно серед microchip та qr_tag
$found = $client->animals()->findByAnyIdentifier('900263000123456');

// Усі тварини власника за його email або телефоном
$pets = $client->animals()->findByOwner('jane@example.com');

// Часткове оновлення (потрібно бути власником або ветеринаром зі зв'язком із твариною)
$client->animals()->update($animalId, [
    'nickname' => 'Барсік',
    'color' => 'black',
    'sterilization_status' => true,
    'deceased' => false,
]);
```

### Процедури (візити)

```php
use AnimalId\PartnerSdk\Resource\ProceduresResource;

// Запис однієї процедури або пакета (до 100) — відкриває візит
// і надає ветеринару зв'язок із твариною.
$result = $client->procedures()->create($animalId, [
    [
        'type' => ProceduresResource::TYPE_VACCINATION,       // 10
        'occurred_at' => '2026-05-30T08:00:00+00:00',
        'summary' => 'Annual shot',
        'revaccination_date' => '2027-05-30',
        'type_specific_payload' => [                          // поля залежать від типу
            'vaccine_name' => 'Nobivac',
            'batch_number' => 'A123',
        ],
    ],
    [
        'type' => ProceduresResource::TYPE_TRANSPONDER_IDENTIFICATION, // 30
        'occurred_at' => '2026-05-30T08:05:00+00:00',
        'type_specific_payload' => [
            'transponder_number' => '900263000123456', // 15 цифр
        ],
    ],
]);

echo $result->getAppointmentId();              // id відкритого візиту
foreach ($result->getProcedures() as $procedure) {
    echo $procedure->getId(), ': тип ', $procedure->getType(), PHP_EOL;
}

// Історія процедур тварини з фільтрами
$history = $client->procedures()->listForAnimal(
    $animalId,
    ProceduresResource::TYPE_VACCINATION,  // лише вакцинації (null — всі типи)
    '2026-01-01T00:00:00+00:00',           // since
    '2026-12-31T23:59:59+00:00'            // until
);

// Одна процедура за id
$procedure = $client->procedures()->get(99001);
```

Доступні константи типів: `TYPE_VACCINATION` (10), `TYPE_RABIES_VACCINATION` (20), `TYPE_TRANSPONDER_IDENTIFICATION` (30), `TYPE_TOKEN_IDENTIFICATION` (40), `TYPE_DEWORMING` (50), `TYPE_STERILIZATION` (60), `TYPE_EUTHANASIA` (70).

### Фото

```php
use AnimalId\PartnerSdk\Resource\PhotosResource;

// Завантаження фото (multipart/form-data, до 8 МБ на файл).
// kind: avatar — головне фото, gallery (за замовчуванням), nose_print.
$photo = $client->photos()->upload($animalId, '/path/to/photo.jpg', PhotosResource::KIND_AVATAR);
echo $photo->getId(); // 33015

// Видалення фото (soft-delete)
$client->photos()->delete($animalId, $photo->getId());
```

### Запити доступу до тварини

Оновлення даних, додавання процедур і зміна фото потребують доступу до тварини (ви її власник або ветеринар із активним звʼязком). Без доступу API відповідає `403` (`AccessDeniedException`). Запросіть доступ — власник підтвердить його у кабінеті:

```php
use AnimalId\PartnerSdk\Resource\AnimalsResource;

// Запит доступу (POST). status: granted | pending | denied.
$state = $client->animals()->requestAccess($animalId);
if ($state->isPending()) {
    // власника сповіщено; повторний запит — не раніше ніж через getRetryAfterSeconds()
}

// Перевірка поточного стану (GET). status: granted | pending | denied | none.
$status = $client->animals()->accessStatus($animalId);
if ($status->isGranted()) {
    $client->animals()->update($animalId, ['nickname' => 'Барсік']);
}
```

Рішення власника надходить вебхуком (`animal_access.approved` / `animal_access.denied`) — див. розділ «Вебхуки».

### Прапорці доступу та власники (expand)

Кожна картка тварини несе `abilities.can_edit`; під час пошуку можна вбудувати власників через `expand`:

```php
$animal = $client->animals()->get($animalId, [AnimalsResource::EXPAND_OWNERS]);

$animal->canEdit();      // bool|null — чи можете редагувати цю тварину
foreach ($animal->getOwners() ?? [] as $owner) {
    $owner->getUserGid();
    $owner->isMainOwner();
}
```

---

## Вебхуки

Animal ID надсилає підписані `POST`-запити на ваш webhook URL (налаштовується там, де ви отримуєте API-ключі), коли стається відкладена подія — наприклад, власник погодив або відхилив запит ветеринара на доступ.

`Webhook\WebhookVerifier` перевіряє підпис і timestamp та повертає типізовану подію. Підпис рахується тим самим алгоритмом, що й ваші запити, але ключем є **окремий webhook-секрет** (показується один раз у кабінеті):

```php
use AnimalId\PartnerSdk\Webhook\WebhookVerifier;
use AnimalId\PartnerSdk\Exception\WebhookVerificationException;

$verifier = new WebhookVerifier(getenv('AID_WEBHOOK_SECRET')); // толеранс replay = 300с

try {
    $event = $verifier->constructEvent(
        file_get_contents('php://input'), // точні байти тіла
        $_SERVER,                          // або getallheaders()
        $_SERVER['REQUEST_URI']            // шлях вашого webhook URL, як отримано
    );
} catch (WebhookVerificationException $e) {
    http_response_code(401);
    exit;
}

if ($event->isAccessApproved()) {
    $event->getAnimalId();          // public_id тварини
    $event->getRequesterUserGid();  // gid ветеринара
}

http_response_code(204); // підтвердьте будь-яким 2xx
```

- `constructEvent()` кидає `WebhookVerificationException` при невалідному підписі, простроченому timestamp або зіпсованому тілі.
- `verify(...): bool` — булева форма; `parse(...): WebhookEvent` — лише декодування без перевірки.
- Вимкнути перевірку часу: `new WebhookVerifier($secret, 0)`.
- Невдалі доставки можна повторно надіслати з кабінету (журнал доставок).

---

## Ідемпотентність і безпечні повтори

Кожен write-запит автоматично отримує унікальний `X-Eternity-Idempotency-Key`. Якщо вам потрібен контрольований повтор (наприклад, retry після таймауту), передайте **власний ключ** — повторний запит з тим самим ключем і тілом поверне першу відповідь, а не створить дубль:

```php
$key = bin2hex(random_bytes(16)); // збережіть ключ до першої спроби

try {
    $owner = $client->owners()->create($ownerData, $key);
} catch (\AnimalId\PartnerSdk\Exception\TransportException $e) {
    // мережа обірвалась — повторюємо З ТИМ САМИМ ключем: дубля не буде
    $owner = $client->owners()->create($ownerData, $key);
}
```

> Той самий ключ з **іншим** тілом запиту поверне `409` (`ConflictException`).

## Обробка помилок

Усі винятки SDK реалізують маркерний інтерфейс `PartnerSdkException`, тож їх можна ловити одним блоком:

```php
use AnimalId\PartnerSdk\Exception\AccessDeniedException;
use AnimalId\PartnerSdk\Exception\ConflictException;
use AnimalId\PartnerSdk\Exception\NotFoundException;
use AnimalId\PartnerSdk\Exception\PartnerSdkException;
use AnimalId\PartnerSdk\Exception\TransportException;
use AnimalId\PartnerSdk\Exception\ValidationException;

try {
    $animalId = $client->animals()->create($data);
} catch (ValidationException $e) {        // 422 — помилки валідації
    print_r($e->getErrors());              // помилки по полях від сервера
} catch (ConflictException $e) {           // 409 — конфлікт idempotency-ключа
    // та сама операція ще обробляється або ключ використано з іншим тілом
} catch (NotFoundException $e) {           // 404
} catch (AccessDeniedException $e) {       // 403 — немає зв'язку з твариною
} catch (TransportException $e) {          // мережа: DNS, таймаут, TLS
} catch (PartnerSdkException $e) {         // будь-яка інша помилка SDK
    echo $e->getMessage();
}
```

| Виняток | HTTP | Коли виникає |
|---|---|---|
| `InvalidArgumentException` | — | некоректне використання SDK (до запиту) |
| `TransportException` | — | мережева помилка, відповіді немає |
| `UnexpectedResponseException` | — | відповідь сервера не є валідним JSON |
| `AuthenticationException` | 401 | невірний підпис, ключі або таймстемп |
| `AccessDeniedException` | 403 | дія заборонена (немає зв'язку з твариною) |
| `NotFoundException` | 404 | ресурс не знайдено |
| `ConflictException` | 409 | конфлікт ідемпотентності |
| `PayloadTooLargeException` | 413 | запит перевищує ліміт шлюзу (15 МБ) |
| `ValidationException` | 422 | помилки валідації (`getErrors()`) |
| `ApiException` | інші | будь-яка інша 4xx/5xx відповідь |

## Власний HTTP-транспорт

За замовчуванням використовується вбудований cURL-клієнт. Для тестів або інтеграції з власним стеком реалізуйте `HttpClientInterface` і передайте його другим аргументом:

```php
use AnimalId\PartnerSdk\Http\HttpClientInterface;
use AnimalId\PartnerSdk\Http\Request;
use AnimalId\PartnerSdk\Http\Response;

final class MyTransport implements HttpClientInterface
{
    public function send(Request $request): Response
    {
        // делегуйте Guzzle, Symfony HttpClient, моку — будь-чому
    }
}

$client = new PartnerClient($config, new MyTransport());
```

## Тестування пакета

```bash
composer install
vendor/bin/phpunit                  # повний прогін (unit + integration)
vendor/bin/phpunit --testsuite unit # лише unit-тести (без локального HTTP-сервера)
vendor/bin/phpunit --coverage-text  # покриття (потрібен pcov або xdebug)
```

### Через Docker (без локального PHP)

У репозиторії є `Dockerfile` (PHP 7.3 + Composer + pcov):

```bash
docker build -t partner-sdk .
docker run --rm -v "$PWD":/app partner-sdk composer install
docker run --rm -v "$PWD":/app partner-sdk                                  # phpunit (CMD за замовчуванням)
docker run --rm -v "$PWD":/app partner-sdk vendor/bin/phpunit --coverage-text
```

Поточне покриття: **99% рядків / 98% методів** (108 тестів, 370 assertions), перевірено на PHP 7.3.

## Ліцензія

MIT
