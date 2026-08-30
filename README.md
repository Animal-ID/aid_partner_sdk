# Animal ID — Partner SDK для PHP

`animalid/partner-sdk` — офіційний PHP SDK для **Partner Integration API** сервісу [Animal ID](https://animal-id.net). Пакет бере на себе всю технічну рутину інтеграції:

- **HMAC-SHA256 підпис** кожного запиту (заголовки `X-Eternity-*`) — ви ніколи не працюєте з криптографією вручну;
- **Ідемпотентність** — для кожного `POST`/`PATCH`/`DELETE` автоматично генерується UUID-ключ `X-Eternity-Idempotency-Key` (або використовується ваш — для безпечних повторів);
- **Типізовані моделі** відповідей (`Owner`, `Animal`, `Procedure`, …) з автодоповненням в IDE;
- **Типізовані винятки** за HTTP-статусами (`ValidationException`, `NotFoundException`, …);
- **ETag-кешування** словників (`If-None-Match` → 304).

Покривається весь Stage 1 API: словники, власники, тварини, процедури, фото — **плюс площина заведення** (клініки, лікарі, дозволи), див. [«Заведення клінік і лікарів»](#заведення-клінік-і-лікарів).

### Дві площини — два ключі

| | `PlatformClient` | `PartnerClient` |
|---|---|---|
| Що робить | заводить клініки й лікарів, просить дозволи | тварини, власники, процедури, фото |
| Яким ключем | **ключ платформи** — видається один раз при заведенні партнерського акаунта | **ключ лікаря** — повертається, коли ви заводите лікаря або отримуєте його креденшели |
| Чого не може | ніколи не дістає до даних тварин | нічого не заводить |

Це не два режими одного ключа: на сервері площини — окремі групи маршрутів, які резолвлять **різні типи застосунку**. Ключ платформи на `/v1/partner/` віддасть `401`, і навпаки. Тому в SDK це два різні клієнти з різними `Config` — щоб їх не можна було переплутати.

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
        // Зафіксувати версію API (заголовок X-Eternity-Animal-ID-Version). За замовчуванням SDK
        // надсилає 2026-07-04 — з цієї версії власник у реєстрації прив'язується за public_id.
        'api_version' => '2026-07-04',
        'timeout' => 15,               // загальний таймаут запиту, сек (default 30)
        'connect_timeout' => 5,        // таймаут з'єднання, сек (default 10)
    ]
);
```

---

## Заведення клінік і лікарів

Звідси починається інтеграція: спершу ви створюєте клініку й лікарів, і аж тоді отримуєте ключі, якими з ними працюватимете.

```php
use AnimalId\PartnerSdk\Config;
use AnimalId\PartnerSdk\PartnerClient;
use AnimalId\PartnerSdk\PlatformClient;

$platform = new PlatformClient(new Config(
    'aid_app_platform_xxx', // App-Id вашої ПЛАТФОРМИ, не клініки
    'pk_xxx',
    'sk_xxx'
));
```

### Спершу пошук, потім створення

Клініка, яка вже є в Animal ID, має директора, історію і, можливо, пацієнтів. Другий її екземпляр розділить і те, і інше навпіл.

```php
foreach ($platform->clinics()->search('Лапа') as $clinic) {
    echo $clinic->getName(), ' — ', $clinic->getFullAddress(), PHP_EOL;

    // Чи це ваша клініка. Якщо ні — щоб посадити туди лікаря, потрібен дозвіл директора.
    if ($clinic->isLinked()) {
        echo '  (ваша)', PHP_EOL;
    }
}
```

Якщо потрібної немає — створюйте. `external_org_id` має бути **стабільним**: повторний виклик із тим самим значенням поверне ту саму клініку, а не створить другу. Саме це робить повторну спробу реєстрації безпечною.

```php
$clinic = $platform->clinics()->provision([
    'external_org_id'    => 'crm-clinic-118',   // ваш власний ідентифікатор
    'name'               => 'Лапа',
    'director_public_id' => $director->getPublicId(),
    'email'              => 'clinic@example.com',
]);

$clinic->getPublicId();   // передавайте його далі
$clinic->wasCreated();    // false — знайшли вже наявну
```

Клініка не може існувати без директора, а лікар може керувати лише однією клінікою: якщо назвати того, хто вже десь директор, повернеться `ConflictException`.

### Завести лікаря і отримати його ключ

```php
$doctor = $platform->doctors()->seat($clinic->getPublicId(), [
    'email'              => 'doctor@example.com',
    'first_name'         => 'Ihor',
    'last_name'          => 'Melnyk',
    'language'           => 'uk',
    'external_doctor_id' => 'crm-doc-4471',      // ваш ідентифікатор, як external_owner_id
    'consent'            => ['account_creation' => true],
]);
```

> ⚠️ **Приватний ключ повертається один раз.** Збережіть його одразу — жоден ендпоінт не віддасть його вдруге, а повторний запит поки ключ активний відповість `409`.

Далі цим ключем працюють уже зі звичайним `PartnerClient`:

```php
$vet = new PartnerClient($doctor->toConfig());
$vet->animals()->create([...]);
```

`toConfig()` існує саме для того, щоб приватний ключ потрапив із відповіді прямо в клієнта, не проходячи через ваш код, який міг би його залогувати.

### Лікар, який уже є в Animal ID

Забрати креденшели наявного лікаря просто так не можна — ключ підписується його іменем, тож дозволити це може **лише він сам**.

```php
use AnimalId\PartnerSdk\Model\ConsentRequest;

$consent = $platform->consents()->requestKeyHandover($doctorPublicId);
// Лікарю приходить лист; він вирішує у своєму кабінеті, у розділі «Доступ партнерів».

$consent = $platform->consents()->status($consent->getPublicId());

if ($consent->isUsable()) {
    $credentials = $platform->doctors()->credentials($clinicPublicId, $doctorPublicId);
}
```

`isUsable()` — це не те саме, що `getStatus() === 'approved'`: дозвіл має строк, і схвалення річної давнини вже не діє.

Щоб посадити лікаря в **чужу** клініку, питають її директора:

```php
$platform->consents()->requestClinicMembership($doctorPublicId, $clinicPublicId);
```

Повторний запит **не створює другий**: повертається той самий відкритий, тож ретрай або подвійний клік не задовбають людину на тому боці.

### Не опитуйте — підпишіться

`status()` існує як запасний варіант. Основний шлях — вебхуки `consent.*`, бо **відкликання** може статися через місяці, і переданий ключ перестає працювати тієї ж миті. Див. [«Вебхуки»](#вебхуки).

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
    'external_owner_id' => 'crm-4471', // ваш власний id цієї людини (необов'язково)
    'consent' => [
        'account_creation' => true, // власник погодився на створення акаунта
    ],
]);

echo $owner->getPublicId();        // "V1StGXR8..." — стабільний ідентифікатор власника для реєстрації тварини
echo $owner->getUserGid();         // 90231 — легасі числовий id (для старих версій API)
echo $owner->hasAccount();         // чи вже має робочий акаунт
echo $owner->getDisplayHint();     // маскована назва без PII, напр. "Ол*** К."
echo $owner->getExternalOwnerId(); // "crm-4471" — ваш id, якщо ви його передавали

// Пошук власника за точним email або телефоном (одне поле — формат визначається автоматично)
try {
    $owner = $client->owners()->search('jane@example.com');
} catch (NotFoundException $e) {
    // власника з таким email/телефоном не існує
}
```

#### Ваш власний ідентифікатор власника

`external_owner_id` — це id, під яким ця людина відома у **вашій** системі. Передайте його при
створенні власника (або в inline-власнику при реєстрації тварини), і він повернеться у
`owners()->create()`, `owners()->search()` та в розгортанні `owners` на картці тварини.

Дві властивості, про які варто знати наперед:

- **Записується один раз** — при першому контакті — і **ніколи не перезаписується**. Повторний
  виклик з іншим значенням його не змінить.
- **Ізольований по інтеграції**: ви бачите лише той id, який передали самі. Що іншого партнера
  та сама людина зветься інакше — вам не видно, і навпаки.

Це те, за чим ваш експорт і наші дані звіряються по одній людині, коли ідентифікатори
з обох боків треба зіставити.

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
        ['public_id' => 'V1StGXR8...'],  // прив'язати існуючого власника за public_id (з owners()->search())...
        [                                // ...або зареєструвати нового "інлайн"
            'email' => 'jane@example.com',
            'first_name' => 'Jane',
            'external_owner_id' => 'crm-4471', // ваш власний id цієї людини (необов'язково)
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
    $owner->getPublicId();        // стабільний ідентифікатор власника (для реєстрації інших тварин)
    $owner->getUserGid();         // легасі числовий id
    $owner->getExternalOwnerId(); // ваш id цієї людини, якщо ви його передавали; інакше null
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

### Події про дозволи (`consent.*`)

Чим закінчився ваш запит дозволу. **Обовʼязково обробляйте `consent.revoked`**: людина може забрати дозвіл будь-коли, переданий ключ перестає працювати тієї ж миті, і без цієї події ви дізнаєтесь про це аж коли впаде наступний виклик.

```php
if ($event->isConsentEvent()) {
    $event->getConsentId();    // той самий public_id, що повернув запит
    $event->getConsentKind();  // key_handover | clinic_membership
    $event->getDoctorId();     // для key_handover
    $event->getClinicId();     // для clinic_membership

    if ($event->isConsentRevoked()) {
        // Ключ цього лікаря вже мертвий — приберіть його зі сховища.
    }

    if ($event->isConsentExpired()) {
        // Ніхто не відповів. Це не відмова — можна спитати ще раз.
    }
}
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
