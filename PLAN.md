# `linqelio/laravel` — план пакета

Офіційний Laravel-пакет для інтеграції з Linqelio: мультиканальний хаб для
WhatsApp / Telegram / Viber з мультикабінетністю та контрактом-first API.

Репозиторій **публічний**, тож усе тут написано з розрахунку на стороннього
розробника, який ніколи не бачив нашого бекенду.

---

## 1. Чим цей пакет є і чим не є

**Є:** типізованим клієнтом над OpenAPI-контрактом Linqelio, приймачем вебхуків
із перевіркою підпису, і набором Laravel-примітивів (події, черги, команди), щоб
інтеграція складалась із кількох рядків, а не з роботи з `Http::` руками.

**Не є:** реалізацією месенджерів. Пакет не знає, що таке QR-логін WhatsApp чи
MTProto-сесія — це живе на нашому боці. Він також не є ORM над нашими даними:
контакти й повідомлення лишаються в Linqelio, пакет їх не дублює (див. §4.6).

---

## 2. Що саме обгортаємо — факти контракту

Це не деталі реалізації, а те, що диктує форму пакета.

**36 операцій** у семи групах: `channels`, `contacts`, `messages`,
`conversations`, `webhooks`, `tenancy` (кабінети й ключі), `embed`, плюс
`access-pool` і `audit`.

**Дві схеми авторизації.**
- `clientApiKey` — Bearer у форматі `<cabinetId>.<kid>.<secret>`, довгоживучий,
  зберігається як argon2id-хеш і показується один раз. Скоуп `*` дає повний
  доступ у межах свого кабінету; `platform:admin` потрібен окремо і лише для
  `/cabinets/*`.
- `embedToken` — короткоживучий JWT з `POST /embed/session`, авторизує **тільки**
  `/embed/*` і скоуплений на один контакт. Його єдиний тримач — віджет у
  браузері; хост-сторінка токен не бачить.

**`Idempotency-Key` обовʼязковий** на небезпечних командах (`sendContactMessage`).
Повтор із тим самим ключем і тим самим тілом повертає початковий результат;
той самий ключ з іншим тілом — `409 idempotency.key_reused`.

**Помилки — RFC 9457** `application/problem+json` зі стабільним полем `code` у
формі `domain.reason`. Реєстр адитивний: 27 кодів, вони ніколи не
перепризначаються. Клієнт має перемикатись саме на `code`, не на HTTP-статус і не
на текст.

**Вебхуки підписані** заголовком `X-Linqelio-Signature: sha256=<hex>`, де hex —
HMAC-SHA256 сирого тіла на спільному секреті.

**Payload вебхука навмисно неповний.** `message.inbound` несе лише маршрутні
ідентифікатори:

```json
{ "messageId": "...", "kind": "tg_bot", "channelId": "...", "chatId": "...",
  "contactRef": "...", "type": "image", "occurredAt": "..." }
```

Тіла повідомлення там **немає** — його треба дотягнути через API за `messageId`.
Це свідоме рішення нашого боку (ADR-0031 §4), і воно визначає архітектуру
приймача: обробник вебхука не самодостатній, він завжди робить зустрічний запит.

**Медіа — трифазне.** Вихідне: `POST /media` (сирі байти) → отримали URL →
кладемо його в `content.media.url` команди відправки. Вхідне: читається через
`GET /messages/{id}/media`, який стрімить байти через платформу під нашою
авторизацією; presigned-посилання назовні не віддається.

---

## 3. Підтримка версій

| | |
|---|---|
| PHP | 8.2, 8.3, 8.4 |
| Laravel | 12.x |
| Тести | Pest 3 + Orchestra Testbench |
| Статичний аналіз | PHPStan level 8 (`larastan`) |
| Стиль | Laravel Pint |

Матриця CI — усі версії PHP, `prefer-lowest` окремим прогоном.

> Laravel 11 підтримувати неможливо: лінійка вийшла з вікна security-підтримки,
> тож **кожен** її реліз має незакриті адвайзорі, і Composer за дефолтною
> політикою відмовляється його ставити. Підтримка означала б прохання до
> користувачів вимкнути перевірку вразливостей.

---

## 4. Рішення дизайну

### 4.1 Клієнт — рукописний, не згенерований

Генератор з OpenAPI дав би 36 методів і сотню DTO за годину, але згенерований
PHP-клієнт зазвичай неприємний у використанні: іменування з `operationId`,
масиви замість типів, нульова документація в IDE.

Пакет — це **обгортка заради ергономіки**; якщо вона повторює форму контракту
один-до-одного, вона не потрібна. Тому ресурси пишемо руками, а контракт
використовуємо як джерело істини у **тестах**: окремий тест звіряє, що кожен
`operationId` зі спеки або покритий методом, або явно виключений зі списку —
рівно так, як `admin-parity.mjs` робить це для нашої адмінки.

### 4.2 Транспорт

Laravel `Http` client з `RequestException` вимкненим — статуси обробляємо самі,
бо тіло помилки несе `code`, який важливіший за статус.

- Ретраї: `429` і `5xx` з експоненційним backoff і повагою до `Retry-After`.
- `4xx` крім `429` — не ретраїмо ніколи.
- Таймаут за замовчуванням 15 с, для `POST /media` — 60 с.
- Кожен запит несе `User-Agent: linqelio-laravel/<version> php/<version>`.

### 4.3 Помилки → типізовані винятки

Базовий `LinqelioException`, від нього — по домену з реєстру:

```
AuthException            auth.*        (invalid_key, key_expired, forbidden_scope)
TenancyException         tenancy.*
ChannelException         channel.*     (not_connected, capability_unsupported, pairing_required)
PolicyException          policy.*      (rate_limited, quota_exceeded, rule_blocked)
ContactException         contact.*     (not_found, version_conflict, identity_conflict)
MessageException         message.*     (type_unsupported, too_large)
IdempotencyException     idempotency.key_reused
EmbedException           embed.*
ProviderException        provider.*    (upstream_error, unavailable)
ValidationException      validation.invalid_request  + errors[] по полях
```

Кожен несе `->code()`, `->status()`, `->problem()` (сире тіло) і `->requestId()`.
`PolicyException` додатково дає `retryAfter()`.

Реєстр кодів — один `enum ErrorCode: string`, згенерований з контракту скриптом,
із тестом на дрейф. Адитивність реєстру означає, що невідомий код не має
ламати клієнт: незнайоме значення падає в `ErrorCode::Unknown` із збереженням
сирого рядка.

### 4.4 Ідемпотентність — за замовчуванням, не опційно

Найлегша помилка інтеграції: відправити повідомлення, отримати таймаут, повторити
і надіслати дубль людині. Тому:

- `Idempotency-Key` генерується автоматично (UUIDv7), якщо не заданий явно;
- при відправці з черги ключ **виводиться з ID джоби**, тож ретрай черги
  повторює той самий ключ, а не створює новий;
- `Linqelio::messages()->send(...)` приймає `idempotencyKey:` для випадків, коли
  ключем має бути щось з боку хоста (напр. ID замовлення).

### 4.5 Вебхуки

Пакет реєструє один роут (шлях налаштовний, за замовчуванням
`POST /linqelio/webhook`) із middleware `VerifyLinqelioSignature`:

- звіряє `X-Linqelio-Signature` через `hash_equals` над **сирим** тілом;
- має захист від replay: відкидає події, старші за `tolerance` (за замовчуванням
  5 хв), і памʼятає `messageId` у кеші на той самий строк.

Далі диспатчить Laravel-подію. І тут — головне рішення:

**`MessageReceived` містить лише те, що прийшло у вебхуці.** Ліниве
довантаження тіла — окремим викликом `$event->message()`, який ходить в API і
кешує результат у межах запиту. Так споживач, якому потрібен лише `messageId`
для черги, не платить зайвим HTTP-запитом, а той, кому потрібне тіло, пише один
метод замість власного клієнта.

За замовчуванням обробка вебхука **ставиться в чергу** (`ProcessWebhook` job),
бо зустрічний запит до API всередині HTTP-обробника вебхука — це спосіб отримати
таймаути на їхньому боці й ретраї на нашому.

### 4.6 Проєкція повідомлень — так, контактів — ні

Пакет веде локальну таблицю повідомлень (`linqelio_messages`), щоб застосунок міг
робити те, чого API не дає дешево: приєднувати листування до своїх сутностей,
шукати повнотекстово, будувати звіти, показувати історію без мережевого виклику.

Але це **проєкція, а не джерело істини**, і з цього випливають три обмеження.

**Ключ — `messageId`, ніколи не `chatId`.** Адреса розмови у платформі може
змінитись під ногами: коли контакт заведено за номером, а Telegram резолвить його
в числовий id, платформа переклює ідентичність і переносить розмову. Проєкція,
побудована на `chatId`, після такої канонізації тихо роздвоїться. `messageId` —
ULID, стабільний назавжди.

**Контакти не дзеркалимо.** Контакт — це особа з `identities[]`, і рішення «ці дві
адреси — одна людина» ухвалює платформа (ADR-0008). Локальна копія розійдеться
саме в цій точці. Плюс `_meta.version` існує для optimistic concurrency —
копія без нього перетворює конфлікт на мовчазне перезаписування. Тому для звʼязку
з моделями хоста лишається трейт `HasLinqelioContact` з єдиним полем
`linqelio_contact_id`.

**Байти медіа не зберігаємо.** У рядку лежить лише `messageId`; вміст тягнеться
через `GET /messages/{id}/media` під нашою авторизацією. Presigned-посилання
протухають, а копіювати вкладення в сховище хоста — це дублювати відповідальність
за їх видалення.

Заповнюється проєкція з `ProcessWebhook` (вхідні) і з `SendMessage` (вихідні,
після `202`). Вимикається одним прапорцем у конфігу — застосунку, якому вистачає
подій, таблиця не нав'язується.

Для наявних кабінетів є `php artisan linqelio:backfill` — тягне історію через
`listConversations` + `listConversationMessages` посторінково, ідемпотентно за
`messageId`.

### 4.7 Embed-токени

`Linqelio::embed()->session(contactId, cap: [...])` повертає короткоживучий токен
для віджета. Пакет **не** віддає його у Blade напряму — натомість дає роут
`GET /linqelio/embed-token`, який видає токен уже авторизованому користувачеві
хоста.

Причина: токен несе скоуп на конкретний контакт. Якщо його рендерити в HTML,
він осідає в кеші сторінки, в історії, в логах CDN. Окремий роут дозволяє
віджету взяти токен по XHR і тримати лише в памʼяті — так само, як це робить
наша адмінка.

---

## 5. Дерево

```
linqelio-laravel/
├── src/
│   ├── LinqelioServiceProvider.php
│   ├── Linqelio.php                    # головний вхід, тримає ресурси
│   ├── Facades/Linqelio.php
│   ├── Client/
│   │   ├── HttpClient.php              # транспорт, ретраї, User-Agent
│   │   ├── Response.php
│   │   └── IdempotencyKey.php
│   ├── Resources/
│   │   ├── ChannelsResource.php
│   │   ├── ContactsResource.php
│   │   ├── MessagesResource.php
│   │   ├── ConversationsResource.php
│   │   ├── MediaResource.php
│   │   ├── WebhooksResource.php
│   │   ├── TenancyResource.php         # platform:admin
│   │   ├── AccessPoolResource.php
│   │   ├── AuditResource.php
│   │   └── EmbedResource.php
│   ├── Data/                           # readonly DTO
│   │   ├── Channel.php  ChannelStatus.php
│   │   ├── Contact.php  Identity.php
│   │   ├── Message.php  MediaContent.php
│   │   ├── Conversation.php  PageInfo.php
│   │   └── Enums/{ChannelKind,MessageType,MessageStatus,ErrorCode}.php
│   ├── Exceptions/                     # §4.3
│   ├── Webhooks/
│   │   ├── WebhookController.php
│   │   ├── VerifySignature.php
│   │   ├── SignatureVerifier.php
│   │   └── ProcessWebhook.php          # job
│   ├── Events/
│   │   ├── MessageReceived.php
│   │   └── WebhookReceived.php         # сирий, для нетипізованих подій
│   ├── Jobs/SendMessage.php
│   ├── Models/LinqelioMessage.php      # проєкція, §4.6
│   ├── Concerns/HasLinqelioContact.php
│   └── Console/
│       ├── InstallCommand.php
│       ├── ChannelsCommand.php
│       ├── BackfillCommand.php
│       └── SendTestCommand.php
├── config/linqelio.php
├── database/migrations/
│   └── 0001_01_01_000000_create_linqelio_messages_table.php
├── routes/linqelio.php
├── tests/
│   ├── Feature/…                       # Testbench
│   ├── Unit/…
│   ├── Contract/ContractParityTest.php # §4.1
│   └── Fixtures/openapi.yaml           # копія спеки, оновлюється скриптом
├── .github/workflows/{tests,static,pint}.yml
├── composer.json  README.md  CHANGELOG.md  LICENSE  UPGRADING.md
└── SECURITY.md  CONTRIBUTING.md
```

---

## 6. Як це виглядає у застосунку

```php
// config/linqelio.php ← .env
LINQELIO_URL=https://api.linqelio.com/v1
LINQELIO_KEY=<cabinetId>.<kid>.<secret>
LINQELIO_WEBHOOK_SECRET=...
```

```php
use Linqelio\Laravel\Facades\Linqelio;

// відправити
Linqelio::messages()->send(
    contactId: $customer->linqelio_contact_id,
    type: MessageType::Text,
    content: ['text' => 'Ваше замовлення відправлено'],
    idempotencyKey: "order-{$order->id}-shipped",
);

// з вкладенням
$media = Linqelio::media()->upload($request->file('photo'));
Linqelio::messages()->send($contactId, MessageType::Image, [
    'media' => $media->toContent(caption: 'Накладна'),
]);

// завести контакт за номером
$contact = Linqelio::contacts()->create(
    channelType: ChannelKind::TgClient,
    phone: '+380501082555',
);
```

```php
// приймання
class NotifyOperator
{
    public function handle(MessageReceived $event): void
    {
        $message = $event->message();          // ← довантаження за потреби
        Operator::notify($message->text);
    }
}
```

```php
// модель хоста
class Customer extends Model
{
    use HasLinqelioContact;
}

$customer->sendMessage('Дякуємо за замовлення');
```

---

## 7. Майлстоуни

**M1 — кістяк.** ServiceProvider, конфіг, транспорт, помилки, `channels` +
`contacts`. Тести на Testbench, CI зелений. Цього достатньо, щоб пакет уже був
корисним.

**M2 — повідомлення.** `messages`, `media`, `conversations`, ідемпотентність,
`SendMessage` job. Тут же — `ContractParityTest`.

**M3 — вебхуки.** Роут, перевірка підпису, захист від replay, `MessageReceived`
із лінивим довантаженням, `ProcessWebhook`.

**M4 — проєкція.** Міграція, `LinqelioMessage`, запис із вебхука й з відправки,
`linqelio:backfill`, повнотекстовий пошук, звʼязок із моделями хоста.

**M5 — периферія.** `embed`, `tenancy`, `access-pool`, `audit`, artisan-команди,
трейт `HasLinqelioContact`.

**M6 — публікація.** README з прикладами під кожен канал, `UPGRADING.md`,
`SECURITY.md`, семантичне версіонування, реліз `v0.1.0` у Packagist.

---

## 8. Рішення

| | |
|---|---|
| Пакет | `linqelio/linqelio-laravel` |
| Ліцензія | MIT |
| README | англійська (Packagist) |
| Проєкція повідомлень | так — §4.6 |
| Базовий URL | **без дефолту в коді** |

Останнє окремо. Репозиторій публічний, тому адреси інсталяції в ньому не має бути
ні як дефолту в `config/linqelio.php`, ні як прикладу в README. `LINQELIO_URL`
обовʼязковий; порожнє значення — виняток на старті з внятним текстом, а не тихий
фолбек на чийсь чужий хост. У прикладах — нейтральний плейсхолдер.
