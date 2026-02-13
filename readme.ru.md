# Plugin Core Logistic

> Базовый фреймворк для логистических плагинов SalesRender (доставка и фулфилмент)

[English version](README.MD)

## Обзор

`salesrender/plugin-core-logistic` -- наиболее функционально насыщенная специализированная библиотека в экосистеме плагинов SalesRender. Расширяет `salesrender/plugin-core` (через `salesrender/plugin-core-geocoder`) и предоставляет полную инфраструктуру для логистических плагинов.

Логистические плагины работают в двух основных доменах:

- **Shipping (доставка)** -- создание отгрузок, формирование накладных, добавление заказов в отгрузки, отслеживание посылок, отмена отгрузок, удаление заказов из отгрузок
- **Fulfillment (фулфилмент)** -- синхронизация остатков с внешними складами, привязка SKU SalesRender к внешним идентификаторам товаров, обработка синхронизации заказов

Каждый логистический плагин работает в **одном режиме**, определяемом классом в `Info::config()`:

| Класс | Режим | Описание |
|-------|-------|----------|
| `LogisticPluginClass::CLASS_DELIVERY` | Shipping | Стандартные службы доставки (курьер, ПВЗ, почта) |
| `LogisticPluginClass::CLASS_FULFILLMENT` | Fulfillment | Интеграции со складами и фулфилмент-центрами |

## Установка

```bash
composer require salesrender/plugin-core-logistic
```

### Требования

- PHP >= 7.4
- ext-json
- `salesrender/plugin-core` ^0.4.0
- `salesrender/plugin-core-geocoder` ^0.3.0
- `salesrender/plugin-component-logistic` ^2.0.0
- `salesrender/plugin-component-purpose` ^2.0
- `xakepehok/array-to-uuid-helper` ^0.1.0

## Архитектура

### Расширение plugin-core

`plugin-core-logistic` расширяет цепочку: `plugin-core` -> `plugin-core-geocoder` -> `plugin-core-logistic`.

1. **WebAppFactory** (`SalesRender\Plugin\Core\Logistic\Factories\WebAppFactory`) расширяет `WebAppFactory` из geocoder и автоматически:
   - Добавляет поддержку CORS
   - Регистрирует действия пакетной обработки (batch)
   - Регистрирует форму и обработчик накладных по адресу `/protected/forms/waybill`
   - Регистрирует endpoint получения статусов трекинга: `/protected/track/statuses/{trackNumber}`
   - **Для режима shipping:** Регистрирует `ShippingCancelAction` и `RemoveOrdersAction` как special request actions
   - **Для режима fulfillment:** Регистрирует `SyncAction` как special request action; добавляет обработчик сохранения настроек для запуска синхронизации привязок

2. **ConsoleAppFactory** (`SalesRender\Plugin\Core\Logistic\Factories\ConsoleAppFactory`) расширяет базовую консольную фабрику и:
   - Добавляет команды пакетной обработки
   - Добавляет `FulfillmentSyncCommand` (`fulfillment:sync`)
   - **Для режима fulfillment:** Регистрирует три cron-задачи для автоматической синхронизации привязок

### Что должен реализовать разработчик

**Для Shipping-плагинов:**

| Интерфейс / Класс | Назначение |
|--------------------|------------|
| `WaybillHandlerInterface` | Обработка данных формы накладной и возврат `WaybillResponse` |
| `BatchShippingHandler` (наследовать) | Пакетная обработка отгрузки: создание, добавление заказов, завершение |
| `ShippingCancelAction` (наследовать) | Обработка запросов на отмену отгрузки |
| `RemoveOrdersAction` (наследовать) | Обработка удаления заказов из отгрузки |

**Для Fulfillment-плагинов:**

| Интерфейс / Класс | Назначение |
|--------------------|------------|
| `WaybillHandlerInterface` | Обработка данных формы накладной и возврат `WaybillResponse` |
| `FulfillmentBindingHandlerInterface` | Построение привязок товаров из настроек плагина |
| `FulfillmentSyncHandlerInterface` | Синхронизация отдельных заказов с внешней системой фулфилмента |
| `FulfillmentRemoveHandlerInterface` | Удаление заказов из системы фулфилмента |
| `BatchFulfillmentHandler` (наследовать) | Пакетная обработка фулфилмента |

### Порядок конфигурации в bootstrap

Файл `bootstrap.php` связывает все компоненты (см. `bootstrap.example.php` в репозитории):

1. Настройка подключения к БД (`Connector::config`)
2. Установка языка по умолчанию (`Translator::config`)
3. Настройка загрузки файлов (`UploadersContainer::addDefaultUploader`)
4. Настройка информации о плагине (`Info::config` с `PluginType::LOGISTIC`)
5. Настройка формы настроек (`Settings::setForm`)
6. Настройка autocomplete (опционально)
7. Настройка table preview (опционально)
8. Настройка batch-форм и обработчика (`BatchContainer::config`)
9. Настройка формы и обработчика накладных (`WaybillContainer::config`)
10. **Для shipping:** Настройка действий отмены и удаления (`ShippingContainer::config`)
11. **Для fulfillment:** Настройка обработчиков фулфилмента (`FulfillmentContainer::config`)

## Начало работы: Создание логистического плагина

### Шаг 1: Настройка проекта

Создайте `composer.json`:

```json
{
  "name": "your-vendor/plugin-logistic-your-provider",
  "type": "project",
  "autoload": {
    "psr-4": {
      "YourVendor\\Plugin\\Logistic\\YourProvider\\": "src/"
    }
  },
  "require": {
    "php": "^7.4.0",
    "ext-json": "*",
    "salesrender/plugin-core-logistic": "^0.7.0"
  }
}
```

```bash
composer install
```

### Шаг 2: Конфигурация bootstrap

Создайте `bootstrap.php`. Этот пример показывает плагин **доставки (shipping)**:

```php
<?php

use SalesRender\Plugin\Components\Batch\BatchContainer;
use SalesRender\Plugin\Components\Db\Components\Connector;
use SalesRender\Plugin\Components\Info\Developer;
use SalesRender\Plugin\Components\Info\Info;
use SalesRender\Plugin\Components\Info\PluginType;
use SalesRender\Plugin\Components\Purpose\LogisticPluginClass;
use SalesRender\Plugin\Components\Purpose\PluginEntity;
use SalesRender\Plugin\Components\Settings\Settings;
use SalesRender\Plugin\Components\Translations\Translator;
use SalesRender\Plugin\Core\Actions\Upload\LocalUploadAction;
use SalesRender\Plugin\Core\Actions\Upload\UploadersContainer;
use SalesRender\Plugin\Core\Logistic\Components\Actions\Shipping\ShippingContainer;
use SalesRender\Plugin\Core\Logistic\Components\Waybill\WaybillContainer;
use YourVendor\Plugin\Logistic\YourProvider\Batch\Batch_1;
use YourVendor\Plugin\Logistic\YourProvider\Batch\BatchShippingHandler;
use YourVendor\Plugin\Logistic\YourProvider\Actions\CancelAction;
use YourVendor\Plugin\Logistic\YourProvider\Actions\RemoveOrdersAction;
use YourVendor\Plugin\Logistic\YourProvider\Settings\SettingsForm;
use YourVendor\Plugin\Logistic\YourProvider\Waybill\WaybillForm;
use YourVendor\Plugin\Logistic\YourProvider\Waybill\WaybillHandler;
use Medoo\Medoo;
use XAKEPEHOK\Path\Path;

# 1. Настройка БД
Connector::config(new Medoo([
    'database_type' => 'sqlite',
    'database_file' => Path::root()->down('db/database.db')
]));

# 2. Установка языка по умолчанию
Translator::config('ru_RU');

# 3. Настройка загрузки файлов
UploadersContainer::addDefaultUploader(new LocalUploadAction([]));

# 4. Информация о плагине
Info::config(
    new PluginType(PluginType::LOGISTIC),
    fn() => Translator::get('info', 'Ваш логистический плагин'),
    fn() => Translator::get('info', 'Описание плагина в **markdown**'),
    [
        "class"    => LogisticPluginClass::CLASS_DELIVERY,
        "entity"   => PluginEntity::ENTITY_ORDER,
        "currency" => ["RUB"],
        "codename" => "SR_LOGISTIC_YOUR_PROVIDER",
    ],
    new Developer(
        'Ваша компания',
        'support@example.com',
        'example.com',
    )
);

# 5. Форма настроек
Settings::setForm(fn() => new SettingsForm());

# 6. Batch-формы и обработчик
BatchContainer::config(
    function (int $number) {
        switch ($number) {
            case 1: return new Batch_1();
            default: return null;
        }
    },
    new BatchShippingHandler()
);

# 7. Форма и обработчик накладных
WaybillContainer::config(
    fn() => new WaybillForm(),
    new WaybillHandler()
);

# 8. Действия отмены отгрузки и удаления заказов
ShippingContainer::config(
    new CancelAction(),
    new RemoveOrdersAction(),
);
```

### Шаг 3: Реализация обязательных интерфейсов

#### 3a. WaybillHandlerInterface

Обработчик накладных обрабатывает данные формы и возвращает `WaybillResponse`:

```php
<?php

namespace YourVendor\Plugin\Logistic\YourProvider\Waybill;

use SalesRender\Plugin\Components\Form\Form;
use SalesRender\Plugin\Components\Form\FormData;
use SalesRender\Plugin\Components\Logistic\Logistic;
use SalesRender\Plugin\Components\Logistic\LogisticStatus;
use SalesRender\Plugin\Components\Logistic\Waybill\Waybill;
use SalesRender\Plugin\Components\Logistic\Waybill\Track;
use SalesRender\Plugin\Core\Logistic\Components\Waybill\Response\WaybillAddress;
use SalesRender\Plugin\Core\Logistic\Components\Waybill\Response\WaybillResponse;
use SalesRender\Plugin\Core\Logistic\Components\Waybill\WaybillHandlerInterface;
use SalesRender\Components\Address\Address;

class WaybillHandler implements WaybillHandlerInterface
{
    public function __invoke(Form $form, FormData $data): WaybillResponse
    {
        // Создание накладной из данных формы
        $track = $data->get('waybill.track')
            ? new Track($data->get('waybill.track'))
            : null;

        $waybill = new Waybill(
            $track,
            null,  // цена
            null,  // сроки доставки
            null,  // тип доставки
            false  // наложенный платёж
        );

        $logistic = new Logistic(
            $waybill,
            new LogisticStatus(LogisticStatus::CREATED)
        );

        // Опционально возвращаем обновлённый адрес
        $address = new WaybillAddress(
            $data->get('address.field.0'),
            new Address(
                (string) $data->get('address.region'),
                (string) $data->get('address.city'),
                (string) $data->get('address.address_1'),
                (string) $data->get('address.address_2'),
                (string) $data->get('address.postcode'),
                (string) $data->get('address.countryCode.0')
            )
        );

        return new WaybillResponse($logistic, $address);
    }
}
```

#### 3b. BatchShippingHandler

Наследуйте абстрактный `BatchShippingHandler` для реализации пакетной обработки отгрузок:

```php
<?php

namespace YourVendor\Plugin\Logistic\YourProvider\Batch;

use SalesRender\Plugin\Components\Batch\Batch;
use SalesRender\Plugin\Components\Batch\Process\Error;
use SalesRender\Plugin\Components\Batch\Process\Process;

class BatchShippingHandler extends \SalesRender\Plugin\Core\Logistic\Components\BatchShippingHandler
{
    public function __invoke(Process $process, Batch $batch)
    {
        // 1. Создание отгрузки в SalesRender
        $shippingId = $this->createShipping($batch);

        // 2. Итерация по заказам, блокировка, подготовка данных
        // 3. Добавление заказов в отгрузку
        $this->addOrders($batch, $shippingId, $ordersData);

        // 4. Отметка отгрузки как экспортированной
        $this->markAsExported($batch, $shippingId, $ordersCount);

        // При ошибке:
        // $this->markAsFailed($batch, $shippingId);

        $process->finish(true);
        $process->save();
    }
}
```

Базовый класс предоставляет следующие защищённые методы:

| Метод | Возвращает | Описание |
|-------|------------|----------|
| `createShipping(Batch $batch, array $removeOnCancelFields = [])` | `int` | Создать отгрузку в SalesRender, возвращает ID |
| `addOrders(Batch $batch, string $shippingId, array $orders)` | `ResponseInterface` | Добавить заказы в отгрузку |
| `markAsExported(Batch $batch, string $shippingId, int $ordersCount)` | `ResponseInterface` | Пометить отгрузку как экспортированную |
| `markAsFailed(Batch $batch, string $shippingId)` | `ResponseInterface` | Пометить отгрузку как неудачную |
| `addShippingAttachments(Batch $batch, string $shippingId, ShippingAttachment ...$attachments)` | `ResponseInterface` | Добавить файлы-вложения к отгрузке |
| `lockOrder(int $timeout, int $orderId, Batch $batch)` | `bool` | Заблокировать заказ от параллельных изменений |

#### 3c. ShippingCancelAction и RemoveOrdersAction

Наследуйте абстрактные классы для обработки отмены и удаления заказов:

```php
<?php

namespace YourVendor\Plugin\Logistic\YourProvider\Actions;

use SalesRender\Plugin\Core\Logistic\Components\Actions\Shipping\ShippingCancelAction;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

class CancelAction extends ShippingCancelAction
{
    protected function handle(array $body, ServerRequest $request, Response $response, array $args): Response
    {
        // Реализация логики отмены через API провайдера
        // $body содержит payload special request
        return $response->withStatus(202);
    }
}
```

```php
<?php

namespace YourVendor\Plugin\Logistic\YourProvider\Actions;

use Slim\Http\Response;
use Slim\Http\ServerRequest;

class RemoveOrdersAction extends \SalesRender\Plugin\Core\Logistic\Components\Actions\Shipping\RemoveOrdersAction
{
    protected function handle(array $body, ServerRequest $request, Response $response, array $args): Response
    {
        // Реализация логики удаления заказов
        return $response->withStatus(202);
    }
}
```

### Шаг 4: Точки входа Web и Console

**`public/index.php`** (HTTP-точка входа):

```php
<?php

use SalesRender\Plugin\Core\Logistic\Factories\WebAppFactory;

require_once __DIR__ . '/../vendor/autoload.php';

$factory = new WebAppFactory();
$application = $factory->build();
$application->run();
```

**`console.php`** (CLI-точка входа):

```php
#!/usr/bin/env php
<?php

use SalesRender\Plugin\Core\Logistic\Factories\ConsoleAppFactory;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

$factory = new ConsoleAppFactory();
$application = $factory->build();
$application->run();
```

### Шаг 5: Развёртывание

1. Укажите document root веб-сервера на директорию `public/`
2. Убедитесь, что директория `db/` доступна для записи (для SQLite)
3. Добавьте cron-задачу: `* * * * * php /path/to/console.php cron`

## HTTP-маршруты

### Унаследованные от plugin-core

| Метод | Путь | Описание |
|-------|------|----------|
| POST | `/protected/info` | Информация о плагине |
| POST | `/protected/settings` | Получение/сохранение настроек |
| POST | `/protected/registration` | Регистрация плагина |

### Накладные (Waybill)

| Метод | Путь | Описание |
|-------|------|----------|
| GET | `/protected/forms/waybill` | Получение определения формы накладной |
| POST | `/protected/forms/waybill` | Отправка данных формы накладной |

Endpoint накладной валидирует данные формы через зарегистрированную форму `WaybillContainer`, затем вызывает реализацию `WaybillHandlerInterface`. Ответ включает подписанный JWT-токен с логистическими данными, адрес, информацию о накладной и начальный статус.

### Трекинг (Track)

| Метод | Путь | Описание |
|-------|------|----------|
| GET | `/protected/track/statuses/{trackNumber}` | Получение статусов трекинга отправления |

Параметр `trackNumber` должен соответствовать паттерну `[A-z\d\-_]{6,36}`.

### Пакетная обработка (Batch)

| Метод | Путь | Описание |
|-------|------|----------|
| POST | `/protected/batch/{number}` | Получение определения batch-формы |
| POST | `/protected/batch/handle` | Запуск пакетной обработки |

### Маршруты Special Request

**Режим shipping:**

| Метод | Путь | Описание |
|-------|------|----------|
| POST | `/protected/special-request/shippingCancel` | Отмена отгрузки |
| POST | `/protected/special-request/removeOrders` | Удаление заказов из отгрузки |

**Режим fulfillment:**

| Метод | Путь | Описание |
|-------|------|----------|
| POST | `/protected/special-request/ffSync` | Синхронизация заказа с системой фулфилмента |

## CLI-команды

| Команда | Описание |
|---------|----------|
| `cron` | Запуск плановых cron-задач |
| `special-request:send` | Обработка очереди special requests |
| `db:migrate` | Выполнение миграций базы данных |
| `batch:handle` | Запуск пакетной обработки (внутренняя команда) |
| `fulfillment:sync {selectWithinHours} [-o\|--outdatedOnly]` | Синхронизация привязок фулфилмента |

### Команда fulfillment:sync

Синхронизирует привязки товаров с бэкендом SalesRender. Используется только в режиме fulfillment.

| Аргумент/Опция | Описание |
|-----------------|----------|
| `selectWithinHours` (обязательный) | Синхронизировать только привязки, обновлённые за указанное количество часов |
| `--outdatedOnly` / `-o` | Синхронизировать только привязки, где `syncedAt < updatedAt` |

**Автоматические cron-расписания** (регистрируются для режима fulfillment):

| Расписание | Команда | Назначение |
|------------|---------|------------|
| `*/10 * * * *` | `fulfillment:sync 12 -o` | Синхронизация недавно изменённых привязок каждые 10 минут |
| `15 * * * *` | `fulfillment:sync 24` | Полная синхронизация привязок за последние 24 часа |
| `25 5 * * 6` | `fulfillment:sync 720` | Еженедельная глубокая синхронизация (за 30 дней) |

## Основные классы и интерфейсы

### Shipping (доставка)

#### `ShippingContainer`

**Пространство имён:** `SalesRender\Plugin\Core\Logistic\Components\Actions\Shipping\ShippingContainer`

Статический контейнер для действий, связанных с доставкой. Должен быть сконфигурирован в `bootstrap.php` для плагинов доставки.

| Метод | Описание |
|-------|----------|
| `static config(ShippingCancelAction, RemoveOrdersAction)` | Зарегистрировать действия отмены и удаления |
| `static getShippingCancelAction(): ShippingCancelAction` | Получить действие отмены (бросает `ShippingContainerException`, если не сконфигурирован) |
| `static getRemoveOrdersAction(): RemoveOrdersAction` | Получить действие удаления (бросает `ShippingContainerException`, если не сконфигурирован) |

#### `ShippingCancelAction`

**Пространство имён:** `SalesRender\Plugin\Core\Logistic\Components\Actions\Shipping\ShippingCancelAction`

Абстрактный класс, расширяющий `SpecialRequestAction`. Имя действия -- `'shippingCancel'`. Переопределите метод `handle(array $body, ServerRequest $request, Response $response, array $args): Response`.

#### `RemoveOrdersAction`

**Пространство имён:** `SalesRender\Plugin\Core\Logistic\Components\Actions\Shipping\RemoveOrdersAction`

Абстрактный класс, расширяющий `SpecialRequestAction`. Имя действия -- `'removeOrders'`. Переопределите метод `handle()`.

#### `BatchShippingHandler`

**Пространство имён:** `SalesRender\Plugin\Core\Logistic\Components\BatchShippingHandler`

Абстрактный обработчик пакетной обработки для операций доставки. Реализует `BatchHandlerInterface` и использует `BatchLockTrait`.

Защищённые методы для взаимодействия с бэкендом SalesRender:

| Метод | HTTP | Endpoint | Описание |
|-------|------|----------|----------|
| `createShipping($batch, $removeOnCancelFields)` | POST | `/CRM/plugin/logistic/shipping` | Создать новую отгрузку, возвращает ID |
| `addOrders($batch, $shippingId, $orders)` | PATCH | `/CRM/plugin/logistic/shipping/{id}/orders` | Добавить заказы в отгрузку |
| `markAsExported($batch, $shippingId, $ordersCount)` | POST | `/CRM/plugin/logistic/shipping/{id}/status/exported` | Пометить как экспортированную |
| `markAsFailed($batch, $shippingId)` | POST | `/CRM/plugin/logistic/shipping/{id}/status/failed` | Пометить как неудачную |
| `addShippingAttachments($batch, $shippingId, ...$attachments)` | PATCH | `/CRM/plugin/logistic/shipping/{id}/attachments/add` | Добавить вложения |

### Fulfillment (фулфилмент)

#### `FulfillmentContainer`

**Пространство имён:** `SalesRender\Plugin\Core\Logistic\Components\Fulfillment\FulfillmentContainer`

Статический контейнер для обработчиков фулфилмента. Должен быть сконфигурирован в `bootstrap.php` для плагинов фулфилмента.

| Метод | Описание |
|-------|----------|
| `static config(FulfillmentBindingHandlerInterface, FulfillmentSyncHandlerInterface, FulfillmentRemoveHandlerInterface)` | Зарегистрировать все три обработчика |
| `static getBindingHandler()` | Получить обработчик привязок |
| `static getSyncHandler()` | Получить обработчик синхронизации |
| `static getRemoveHandler()` | Получить обработчик удаления |

Все геттеры бросают `FulfillmentContainerException`, если обработчик не сконфигурирован.

#### `FulfillmentBindingHandlerInterface`

**Пространство имён:** `SalesRender\Plugin\Core\Logistic\Components\Fulfillment\FulfillmentBindingHandlerInterface`

| Метод | Возвращает | Описание |
|-------|------------|----------|
| `__invoke(Settings $settings)` | `Binding` | Построить привязки товаров из настроек плагина |

Вызывается при сохранении настроек (в режиме fulfillment). Возвращённый объект `Binding` автоматически синхронизируется с бэкендом.

#### `FulfillmentSyncHandlerInterface`

**Пространство имён:** `SalesRender\Plugin\Core\Logistic\Components\Fulfillment\FulfillmentSyncHandlerInterface`

| Метод | Возвращает | Описание |
|-------|------------|----------|
| `getGraphqlOrderFields()` | `array` | Выбор полей GraphQL для заказов (используется для запроса данных) |
| `handle(array $graphqlOrder)` | `?string` | Обработка синхронизации заказа; вернуть `null` при успехе или сообщение об ошибке |

#### `FulfillmentRemoveHandlerInterface`

**Пространство имён:** `SalesRender\Plugin\Core\Logistic\Components\Fulfillment\FulfillmentRemoveHandlerInterface`

| Метод | Возвращает | Описание |
|-------|------------|----------|
| `handle(string $orderId)` | `?string` | Удалить заказ из системы фулфилмента; вернуть `null` при успехе или сообщение об ошибке |

#### `SyncAction`

**Пространство имён:** `SalesRender\Plugin\Core\Logistic\Components\Actions\Fulfillment\SyncAction`

Special request action для синхронизации заказов фулфилмента. Имя действия -- `'ffSync'`. Автоматически получает заказ через GraphQL, вызывает `FulfillmentSyncHandlerInterface` и отправляет результат в бэкенд по адресу `/CRM/plugin/logistic/fulfillment/sync`.

#### `BatchFulfillmentHandler`

**Пространство имён:** `SalesRender\Plugin\Core\Logistic\Components\BatchFulfillmentHandler`

Абстрактный обработчик пакетной обработки для операций фулфилмента. Реализует `BatchHandlerInterface` и использует `BatchLockTrait`.

| Метод | Возвращает | Описание |
|-------|------------|----------|
| `updateLogistic(Batch $batch, string $orderId, Waybill $waybill)` | `ResponseInterface` | Отправить обновление логистики для заказа (PUT на `/CRM/plugin/logistic/fulfillment`) |

### Накладные (Waybill)

#### `WaybillContainer`

**Пространство имён:** `SalesRender\Plugin\Core\Logistic\Components\Waybill\WaybillContainer`

Статический контейнер для формы и обработчика накладных.

| Метод | Описание |
|-------|----------|
| `static config(callable $form, WaybillHandlerInterface $handler)` | Зарегистрировать фабрику формы и обработчик |
| `static getForm(array $context = []): Form` | Получить форму (бросает `WaybillContainerException`, если не сконфигурирован) |
| `static getHandler(): WaybillHandlerInterface` | Получить обработчик |

#### `WaybillHandlerInterface`

**Пространство имён:** `SalesRender\Plugin\Core\Logistic\Components\Waybill\WaybillHandlerInterface`

| Метод | Возвращает | Описание |
|-------|------------|----------|
| `__invoke(Form $form, FormData $data)` | `WaybillResponse` | Обработать данные формы накладной и вернуть ответ |

#### `WaybillHandlerAction`

**Пространство имён:** `SalesRender\Plugin\Core\Logistic\Components\Waybill\WaybillHandlerAction`

Внутренний класс действия, реализующий `ActionInterface`. Обрабатывает HTTP endpoint для подачи накладных:

1. Получает форму из `WaybillContainer`
2. Валидирует данные формы
3. Вызывает `WaybillHandlerInterface`
4. Подписывает логистические данные JWT-токеном
5. Возвращает ответ с `logistic` (JWT-токен), `address`, `waybill` и `status`

#### `WaybillResponse`

**Пространство имён:** `SalesRender\Plugin\Core\Logistic\Components\Waybill\Response\WaybillResponse`

Value object, возвращаемый `WaybillHandlerInterface`.

| Свойство | Тип | Описание |
|----------|-----|----------|
| `$logistic` | `Logistic` | Логистические данные (накладная + статус) |
| `$address` | `?WaybillAddress` | Опциональный обновлённый адрес доставки |

#### `WaybillAddress`

**Пространство имён:** `SalesRender\Plugin\Core\Logistic\Components\Waybill\Response\WaybillAddress`

Адрес доставки, связанный с накладной. Реализует `JsonSerializable`.

| Свойство | Тип | Описание |
|----------|-----|----------|
| `$field` | `string` | Имя поля заказа для этого адреса |
| `$address` | `Address` | Объект адреса |

### Трекинг (Track)

#### `Track`

**Пространство имён:** `SalesRender\Plugin\Core\Logistic\Components\Track\Track`

Модель базы данных для отслеживания отправлений. Расширяет `Model` и реализует `PluginModelInterface`. Хранится в таблице `tracks`.

| Свойство/Метод | Тип | Описание |
|----------------|-----|----------|
| `$track` | `string` | Трек-номер |
| `$shippingId` | `string` | ID связанной отгрузки |
| `$createdAt` | `int` | Время создания |
| `$nextTrackingAt` | `?int` | Время следующей проверки трекинга |
| `$lastTrackedAt` | `?int` | Время последней проверки трекинга |
| `$statuses` | `LogisticStatus[]` | Массив статусов трекинга |
| `$stoppedAt` | `?int` | Время остановки отслеживания |
| `$waybill` | `Waybill` | Связанная накладная |
| `addStatus(LogisticStatus)` | `void` | Добавить один статус |
| `setStatuses(LogisticStatus[])` | `void` | Установить/объединить все статусы |
| `setLastTrackedAt()` | `void` | Отметить как только что проверенный |
| `setNextTrackingAt(int $minutes)` | `void` | Запланировать следующую проверку |
| `setStoppedAt()` | `void` | Остановить отслеживание этого отправления |
| `static findForTracking(string $segments, int $limit)` | `Track[]` | Найти треки, готовые к проверке |
| `static findByTrack(string $track)` | `Track[]` | Найти треки по трек-номеру |

Константа `MAX_TRACKING_TIME` равна 5 месяцам (150 дней). Треки старше этого срока не выбираются для отслеживания.

Логика объединения статусов (`mergeStatuses`) обрабатывает дедупликацию и автоматически маппит статусы после `RETURNED` в `RETURNING_TO_SENDER` (при включённой сортировке статусов).

При обнаружении новых статусов `Track` автоматически создаёт нотификацию (special request) в бэкенд SalesRender по адресу `/CRM/plugin/logistic/status/{class}`.

#### `TrackGetStatusesAction`

**Пространство имён:** `SalesRender\Plugin\Core\Logistic\Components\Actions\Track\TrackGetStatusesAction`

HTTP action для endpoint `GET /protected/track/statuses/{trackNumber}`. Находит трек по номеру, разрешает и сортирует статусы, возвращает их в формате JSON.

### Привязки (Binding)

#### `Binding`

**Пространство имён:** `SalesRender\Plugin\Core\Logistic\Components\Binding\Binding`

Модель базы данных для привязок товаров (маппинг SKU). Расширяет `Model` и реализует `SinglePluginModelInterface`. Хранится в таблице `bindings`.

| Метод | Возвращает | Описание |
|-------|------------|----------|
| `getPairs()` | `BindingPair[]` | Получить все пары привязок |
| `getPairBySku(int $itemId, int $variation)` | `?BindingPair` | Найти пару по SKU SalesRender |
| `getPairByExternalId(string $externalId)` | `?BindingPair` | Найти пару по внешнему ID товара |
| `setPair(BindingPair $pair)` | `void` | Добавить или обновить пару привязки |
| `deletePair(BindingPair $pair)` | `void` | Удалить пару привязки |
| `clearAllPairs()` | `void` | Удалить все пары |
| `sync()` | `void` | Синхронизировать привязки с бэкендом SalesRender (PUT на `/CRM/plugin/logistic/fulfillment/stock`) |
| `static find()` | `Binding` | Найти или создать привязку для текущего plugin reference |

Метод `sync()` подписывает данные об остатках JWT-токеном и ставит `SpecialRequestTask` в очередь.

#### `BindingPair`

**Пространство имён:** `SalesRender\Plugin\Core\Logistic\Components\Binding\BindingPair`

Единичная привязка SKU к внешнему товару с остатками. Реализует `JsonSerializable`.

| Метод | Возвращает | Описание |
|-------|------------|----------|
| `__construct(int $itemId, int $variation, string $externalId, array $balances)` | -- | Создать пару привязки |
| `getItemId()` | `int` | ID товара в SalesRender |
| `getVariation()` | `int` | Вариация в SalesRender |
| `getExternalId()` | `string` | Внешний идентификатор товара (по умолчанию `{itemId}_{variation}`, если пустой) |
| `getBalanceByLabel(string $label)` | `?int` | Получить остаток по метке |
| `getBalances()` | `array` | Получить все остатки как ассоциативный массив |

#### `FulfillmentSyncCommand`

**Пространство имён:** `SalesRender\Plugin\Core\Logistic\Components\Commands\FulfillmentSyncCommand`

Консольная команда `fulfillment:sync`, которая перебирает все записи `Binding`, соответствующие критериям, и вызывает `Binding::sync()` для каждой. Использует mutex-блокировку для предотвращения параллельного выполнения.

### Вспомогательные классы и сервисы

#### `LogisticHelper`

**Пространство имён:** `SalesRender\Plugin\Core\Logistic\Helpers\LogisticHelper`

Утилитарный класс для определения режима работы и конфигурации логистики.

| Метод | Возвращает | Описание |
|-------|------------|----------|
| `static config(bool $sortTrackStatuses = true)` | `void` | Настроить поведение сортировки статусов трекинга |
| `static isFulfillment()` | `bool` | Проверить, работает ли текущий плагин в режиме fulfillment |
| `static isSortTrackStatuses()` | `bool` | Проверить, включена ли сортировка статусов трекинга |

#### `OrderFetcherIterator`

**Пространство имён:** `SalesRender\Plugin\Core\Logistic\Components\OrderFetcherIterator`

Итератор для получения заказов через GraphQL API SalesRender. Расширяет `ApiFetcherIterator`. Использует GraphQL-запрос `ordersFetcher`.

#### `BatchLockTrait`

**Пространство имён:** `SalesRender\Plugin\Core\Logistic\Components\Traits\BatchLockTrait`

Trait, предоставляющий функциональность блокировки заказов для обработчиков пакетной обработки.

| Метод | Возвращает | Описание |
|-------|------------|----------|
| `lockOrder(int $timeout, int $orderId, Batch $batch)` | `bool` | Заблокировать заказ через GraphQL mutation на указанное время (в секундах) |

#### `LogisticStatusesResolverService`

**Пространство имён:** `SalesRender\Plugin\Core\Logistic\Services\LogisticStatusesResolverService`

Сервис сортировки и разрешения логистических статусов.

| Метод | Возвращает | Описание |
|-------|------------|----------|
| `__construct(Track $track)` | -- | Инициализация с объектом трека |
| `sort()` | `LogisticStatus[]` | Сортировка статусов по порядку из `LogisticStatus::values()`, затем по времени |
| `getLastStatusForNotify()` | `?LogisticStatus` | Получить последний статус, по которому ещё не было отправлено уведомление |

### Исключения

| Исключение | Источник | Описание |
|------------|----------|----------|
| `ShippingContainerException` | `ShippingContainer` | Контейнер не сконфигурирован |
| `FulfillmentContainerException` | `FulfillmentContainer` | Контейнер не сконфигурирован |
| `FulfillmentSyncException` | `SyncAction` | Плагин не зарегистрирован |
| `WaybillContainerException` | `WaybillContainer` | Форма или обработчик не сконфигурированы |
| `BindingSyncException` | `Binding::sync()` | Плагин не зарегистрирован |
| `TrackException` | `Track::createNotification()` | Плагин не зарегистрирован |

## Special Requests

| Отправитель | HTTP-метод | Endpoint бэкенда | Описание |
|-------------|------------|------------------|----------|
| `BatchShippingHandler` (разные) | POST/PATCH/POST | `/CRM/plugin/logistic/shipping/...` | Операции жизненного цикла отгрузки |
| `BatchFulfillmentHandler` | PUT | `/CRM/plugin/logistic/fulfillment` | Обновление логистики фулфилмента |
| `SyncAction` | PUT | `/CRM/plugin/logistic/fulfillment/sync` | Результаты синхронизации отдельных заказов |
| `Binding::sync()` | PUT | `/CRM/plugin/logistic/fulfillment/stock` | Данные об остатках |
| `Track::createNotification()` | PATCH | `/CRM/plugin/logistic/status/{class}` | Нотификации о статусах трекинга |

## Пример плагина

Полный рабочий пример доступен в [`salesrender/plugin-logistic-example`](https://github.com/SalesRender/plugin-logistic-example).

Пример демонстрирует плагин **доставки (shipping)**:

- **`BatchShippingHandler`** -- полный цикл пакетной отгрузки: создание отгрузки, итерация по заказам с `OrderFetcherIterator`, блокировка заказов, добавление заказов в отгрузку, отметка как экспортированной/неудачной
- **`WaybillHandler`** -- создание накладных с трек-номерами, ценами, сроками доставки, типом доставки и поддержкой наложенного платежа
- **`WaybillForm`** -- полная форма накладной с полями доставки (трек, цена, сроки, тип, наложенный платёж) и полями адреса (индекс, регион, город, адрес, страна, координаты)
- **`Batch_1`** -- batch-форма с выбором отправителя и переопределением типа доставки
- **`CancelAction`** и **`RemoveOrdersAction`** -- заглушки реализации отмены отгрузки и удаления заказов
- **`SettingsForm`** -- форма настроек с логином/паролем и конфигурацией нескольких отправителей

## Зависимости

| Пакет | Версия | Назначение |
|-------|--------|------------|
| [`salesrender/plugin-core`](https://github.com/SalesRender/plugin-core) | ^0.4.0 | Базовый фреймворк плагинов |
| [`salesrender/plugin-core-geocoder`](https://github.com/SalesRender/plugin-core-geocoder) | ^0.3.0 | Поддержка геокодирования |
| [`salesrender/plugin-component-logistic`](https://github.com/SalesRender/plugin-component-logistic) | ^2.0.0 | Модели данных логистики (Waybill, LogisticStatus, Logistic и др.) |
| [`salesrender/plugin-component-purpose`](https://github.com/SalesRender/plugin-component-purpose) | ^2.0 | Определения типов плагинов (`LogisticPluginClass`) |
| `xakepehok/array-to-uuid-helper` | ^0.1.0 | Генерация UUID из массивов (для обнаружения изменений в привязках) |

## Смотрите также

- [salesrender/plugin-core](https://github.com/SalesRender/plugin-core) -- Базовый фреймворк плагинов
- [salesrender/plugin-core-geocoder](https://github.com/SalesRender/plugin-core-geocoder) -- Ядро для плагинов с геокодированием
- [salesrender/plugin-logistic-example](https://github.com/SalesRender/plugin-logistic-example) -- Полный пример логистического плагина
- [salesrender/plugin-core-pbx](https://github.com/SalesRender/plugin-core-pbx) -- Ядро для PBX-плагинов
