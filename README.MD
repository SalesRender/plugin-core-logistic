# Plugin Core Logistic

> Core framework for SalesRender logistic (shipping & fulfillment) plugins

[Russian version / Русская версия](readme.ru.md)

## Overview

`salesrender/plugin-core-logistic` is the most feature-rich specialized core library in the SalesRender plugin ecosystem. It extends `salesrender/plugin-core` (via `salesrender/plugin-core-geocoder`) to provide the complete infrastructure for logistic plugins.

Logistic plugins handle two major domains:

- **Shipping (delivery)** -- Creating shipments, generating waybills, adding orders to shipments, tracking packages, canceling shipments, and removing orders
- **Fulfillment** -- Syncing inventory with external warehouses, binding SalesRender SKUs to external product identifiers, handling order fulfillment sync

A single logistic plugin operates in **one mode** determined by its class in `Info::config()`:

| Class | Mode | Description |
|-------|------|-------------|
| `LogisticPluginClass::CLASS_DELIVERY` | Shipping | Standard delivery providers (courier, pickup point, post) |
| `LogisticPluginClass::CLASS_FULFILLMENT` | Fulfillment | Warehouse/fulfillment center integrations |

## Installation

```bash
composer require salesrender/plugin-core-logistic
```

### Requirements

- PHP >= 7.4
- ext-json
- `salesrender/plugin-core` ^0.4.0
- `salesrender/plugin-core-geocoder` ^0.3.0
- `salesrender/plugin-component-logistic` ^2.0.0
- `salesrender/plugin-component-purpose` ^2.0
- `xakepehok/array-to-uuid-helper` ^0.1.0

## Architecture

### How It Extends plugin-core

`plugin-core-logistic` extends the chain: `plugin-core` -> `plugin-core-geocoder` -> `plugin-core-logistic`.

1. **WebAppFactory** (`SalesRender\Plugin\Core\Logistic\Factories\WebAppFactory`) extends the geocoder's `WebAppFactory` and automatically:
   - Adds CORS support
   - Registers batch processing actions
   - Registers the waybill form and handler at `/protected/forms/waybill`
   - Registers the track status endpoint at `/protected/track/statuses/{trackNumber}`
   - **For shipping mode:** Registers `ShippingCancelAction` and `RemoveOrdersAction` as special request actions
   - **For fulfillment mode:** Registers `SyncAction` as a special request action; adds a settings save handler that triggers fulfillment binding sync

2. **ConsoleAppFactory** (`SalesRender\Plugin\Core\Logistic\Factories\ConsoleAppFactory`) extends the base console factory and:
   - Adds batch processing commands
   - Adds the `FulfillmentSyncCommand` (`fulfillment:sync`)
   - **For fulfillment mode:** Registers three cron tasks for automatic binding sync

### What the Developer Must Implement

**For Shipping plugins:**

| Interface / Class | Purpose |
|-------------------|---------|
| `WaybillHandlerInterface` | Process waybill form data and return a `WaybillResponse` |
| `BatchShippingHandler` (extend) | Handle batch shipping: create shipments, add orders, mark as exported |
| `ShippingCancelAction` (extend) | Handle shipment cancellation requests |
| `RemoveOrdersAction` (extend) | Handle order removal from shipments |

**For Fulfillment plugins:**

| Interface / Class | Purpose |
|-------------------|---------|
| `WaybillHandlerInterface` | Process waybill form data and return a `WaybillResponse` |
| `FulfillmentBindingHandlerInterface` | Build product bindings from plugin settings |
| `FulfillmentSyncHandlerInterface` | Sync individual orders with the external fulfillment system |
| `FulfillmentRemoveHandlerInterface` | Handle order removal from the fulfillment system |
| `BatchFulfillmentHandler` (extend) | Handle batch fulfillment processing |

### Bootstrap Configuration Steps

The `bootstrap.php` file wires everything together (see `bootstrap.example.php` in the repository):

1. Configure the database connection (`Connector::config`)
2. Set the default language (`Translator::config`)
3. Configure file upload settings (`UploadersContainer::addDefaultUploader`)
4. Configure plugin info (`Info::config` with `PluginType::LOGISTIC`)
5. Configure the settings form (`Settings::setForm`)
6. Configure autocompletes (optional)
7. Configure table previews (optional)
8. Configure batch forms and handler (`BatchContainer::config`)
9. Configure waybill form and handler (`WaybillContainer::config`)
10. **For shipping:** Configure cancel and remove actions (`ShippingContainer::config`)
11. **For fulfillment:** Configure fulfillment handlers (`FulfillmentContainer::config`)

## Getting Started: Creating a Logistic Plugin

### Step 1: Project Setup

Create `composer.json`:

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

### Step 2: Bootstrap Configuration

Create `bootstrap.php`. This example shows a **shipping** plugin:

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

# 1. Configure DB
Connector::config(new Medoo([
    'database_type' => 'sqlite',
    'database_file' => Path::root()->down('db/database.db')
]));

# 2. Set default language
Translator::config('ru_RU');

# 3. Configure file upload
UploadersContainer::addDefaultUploader(new LocalUploadAction([]));

# 4. Configure plugin info
Info::config(
    new PluginType(PluginType::LOGISTIC),
    fn() => Translator::get('info', 'Your Logistic Plugin'),
    fn() => Translator::get('info', 'Plugin **description**'),
    [
        "class"    => LogisticPluginClass::CLASS_DELIVERY,
        "entity"   => PluginEntity::ENTITY_ORDER,
        "currency" => ["RUB"],
        "codename" => "SR_LOGISTIC_YOUR_PROVIDER",
    ],
    new Developer(
        'Your Company',
        'support@example.com',
        'example.com',
    )
);

# 5. Configure settings form
Settings::setForm(fn() => new SettingsForm());

# 6. Configure batch forms and handler
BatchContainer::config(
    function (int $number) {
        switch ($number) {
            case 1: return new Batch_1();
            default: return null;
        }
    },
    new BatchShippingHandler()
);

# 7. Configure waybill form and handler
WaybillContainer::config(
    fn() => new WaybillForm(),
    new WaybillHandler()
);

# 8. Configure shipping cancel and remove actions
ShippingContainer::config(
    new CancelAction(),
    new RemoveOrdersAction(),
);
```

### Step 3: Implement Required Interfaces

#### 3a. WaybillHandlerInterface

The waybill handler processes waybill form submissions and returns a `WaybillResponse`:

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
        // Create waybill from form data
        $track = $data->get('waybill.track')
            ? new Track($data->get('waybill.track'))
            : null;

        $waybill = new Waybill(
            $track,
            null,  // price
            null,  // delivery terms
            null,  // delivery type
            false  // COD
        );

        $logistic = new Logistic(
            $waybill,
            new LogisticStatus(LogisticStatus::CREATED)
        );

        // Optionally return updated address
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

Extend the abstract `BatchShippingHandler` to implement the batch shipping workflow:

```php
<?php

namespace YourVendor\Plugin\Logistic\YourProvider\Batch;

use SalesRender\Plugin\Components\Batch\Batch;
use SalesRender\Plugin\Components\Batch\Process\Error;
use SalesRender\Plugin\Components\Batch\Process\Process;
use SalesRender\Plugin\Core\Logistic\Components\BatchShippingHandler;

class BatchShippingHandler extends \SalesRender\Plugin\Core\Logistic\Components\BatchShippingHandler
{
    public function __invoke(Process $process, Batch $batch)
    {
        // 1. Create a shipping in SalesRender
        $shippingId = $this->createShipping($batch);

        // 2. Iterate over orders, lock them, prepare data
        // 3. Add orders to the shipping
        $this->addOrders($batch, $shippingId, $ordersData);

        // 4. Mark shipping as exported
        $this->markAsExported($batch, $shippingId, $ordersCount);

        // On failure:
        // $this->markAsFailed($batch, $shippingId);

        $process->finish(true);
        $process->save();
    }
}
```

The base class provides these protected methods:

| Method | Returns | Description |
|--------|---------|-------------|
| `createShipping(Batch $batch, array $removeOnCancelFields = [])` | `int` | Create a shipping in SalesRender, returns shipping ID |
| `addOrders(Batch $batch, string $shippingId, array $orders)` | `ResponseInterface` | Add orders to a shipping |
| `markAsExported(Batch $batch, string $shippingId, int $ordersCount)` | `ResponseInterface` | Mark shipping as exported |
| `markAsFailed(Batch $batch, string $shippingId)` | `ResponseInterface` | Mark shipping as failed |
| `addShippingAttachments(Batch $batch, string $shippingId, ShippingAttachment ...$attachments)` | `ResponseInterface` | Add file attachments to a shipping |
| `lockOrder(int $timeout, int $orderId, Batch $batch)` | `bool` | Lock an order to prevent concurrent modifications |

#### 3c. ShippingCancelAction and RemoveOrdersAction

Extend the abstract classes to handle cancellation and order removal:

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
        // Implement cancellation logic with your provider
        // $body contains the special request payload
        return $response->withStatus(202);
    }
}
```

```php
<?php

namespace YourVendor\Plugin\Logistic\YourProvider\Actions;

use SalesRender\Plugin\Core\Logistic\Components\Actions\Shipping\RemoveOrdersAction;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

class RemoveOrdersAction extends \SalesRender\Plugin\Core\Logistic\Components\Actions\Shipping\RemoveOrdersAction
{
    protected function handle(array $body, ServerRequest $request, Response $response, array $args): Response
    {
        // Implement order removal logic
        return $response->withStatus(202);
    }
}
```

### Step 4: Web & Console Entry Points

**`public/index.php`** (HTTP entry point):

```php
<?php

use SalesRender\Plugin\Core\Logistic\Factories\WebAppFactory;

require_once __DIR__ . '/../vendor/autoload.php';

$factory = new WebAppFactory();
$application = $factory->build();
$application->run();
```

**`console.php`** (CLI entry point):

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

### Step 5: Deploy

1. Point the web server document root to `public/`
2. Ensure the `db/` directory is writable for SQLite storage
3. Set up a cron job: `* * * * * php /path/to/console.php cron`

## HTTP Routes

### Inherited from plugin-core

| Method | Path | Description |
|--------|------|-------------|
| POST | `/protected/info` | Plugin information |
| POST | `/protected/settings` | Get/save settings |
| POST | `/protected/registration` | Plugin registration |

### Waybill

| Method | Path | Description |
|--------|------|-------------|
| GET | `/protected/forms/waybill` | Get the waybill form definition |
| POST | `/protected/forms/waybill` | Submit waybill form data |

The waybill endpoint validates the form data via the registered `WaybillContainer` form, then calls the `WaybillHandlerInterface` implementation. The response includes a signed JWT token for the logistic data, the address, waybill details, and the initial status.

### Track

| Method | Path | Description |
|--------|------|-------------|
| GET | `/protected/track/statuses/{trackNumber}` | Get tracking statuses for a shipment |

The `trackNumber` parameter must match the pattern `[A-z\d\-_]{6,36}`.

### Batch

| Method | Path | Description |
|--------|------|-------------|
| POST | `/protected/batch/{number}` | Get batch form definition |
| POST | `/protected/batch/handle` | Execute batch processing |

### Special Request Routes

**Shipping mode:**

| Method | Path | Description |
|--------|------|-------------|
| POST | `/protected/special-request/shippingCancel` | Cancel a shipping |
| POST | `/protected/special-request/removeOrders` | Remove orders from a shipping |

**Fulfillment mode:**

| Method | Path | Description |
|--------|------|-------------|
| POST | `/protected/special-request/ffSync` | Sync an order with the fulfillment system |

## CLI Commands

| Command | Description |
|---------|-------------|
| `cron` | Run scheduled cron tasks |
| `special-request:send` | Process queued special requests |
| `db:migrate` | Run database migrations |
| `batch:handle` | Execute batch processing (internal) |
| `fulfillment:sync {selectWithinHours} [-o\|--outdatedOnly]` | Sync fulfillment bindings |

### fulfillment:sync Command

Synchronizes product bindings with the SalesRender backend. Used in fulfillment mode only.

| Argument/Option | Description |
|-----------------|-------------|
| `selectWithinHours` (required) | Only sync bindings updated within this many hours |
| `--outdatedOnly` / `-o` | Only sync bindings where `syncedAt < updatedAt` |

**Automatic cron schedules** (registered for fulfillment mode):

| Schedule | Command | Purpose |
|----------|---------|---------|
| `*/10 * * * *` | `fulfillment:sync 12 -o` | Sync recently changed bindings every 10 minutes |
| `15 * * * *` | `fulfillment:sync 24` | Full sync of bindings updated in last 24 hours |
| `25 5 * * 6` | `fulfillment:sync 720` | Weekly deep sync (last 30 days) |

## Key Classes & Interfaces

### Shipping

#### `ShippingContainer`

**Namespace:** `SalesRender\Plugin\Core\Logistic\Components\Actions\Shipping\ShippingContainer`

Static container for shipping-related actions. Must be configured in `bootstrap.php` for delivery plugins.

| Method | Description |
|--------|-------------|
| `static config(ShippingCancelAction, RemoveOrdersAction)` | Register cancel and remove actions |
| `static getShippingCancelAction(): ShippingCancelAction` | Get the cancel action (throws `ShippingContainerException` if not configured) |
| `static getRemoveOrdersAction(): RemoveOrdersAction` | Get the remove action (throws `ShippingContainerException` if not configured) |

#### `ShippingCancelAction`

**Namespace:** `SalesRender\Plugin\Core\Logistic\Components\Actions\Shipping\ShippingCancelAction`

Abstract class extending `SpecialRequestAction`. The action name is `'shippingCancel'`. Override the `handle(array $body, ServerRequest $request, Response $response, array $args): Response` method.

#### `RemoveOrdersAction`

**Namespace:** `SalesRender\Plugin\Core\Logistic\Components\Actions\Shipping\RemoveOrdersAction`

Abstract class extending `SpecialRequestAction`. The action name is `'removeOrders'`. Override the `handle()` method.

#### `BatchShippingHandler`

**Namespace:** `SalesRender\Plugin\Core\Logistic\Components\BatchShippingHandler`

Abstract batch handler for shipping operations. Implements `BatchHandlerInterface` and uses `BatchLockTrait`.

Protected methods for interacting with SalesRender backend:

| Method | HTTP | Endpoint | Description |
|--------|------|----------|-------------|
| `createShipping($batch, $removeOnCancelFields)` | POST | `/CRM/plugin/logistic/shipping` | Create a new shipping, returns ID |
| `addOrders($batch, $shippingId, $orders)` | PATCH | `/CRM/plugin/logistic/shipping/{id}/orders` | Add orders to shipping |
| `markAsExported($batch, $shippingId, $ordersCount)` | POST | `/CRM/plugin/logistic/shipping/{id}/status/exported` | Mark as exported |
| `markAsFailed($batch, $shippingId)` | POST | `/CRM/plugin/logistic/shipping/{id}/status/failed` | Mark as failed |
| `addShippingAttachments($batch, $shippingId, ...$attachments)` | PATCH | `/CRM/plugin/logistic/shipping/{id}/attachments/add` | Add attachments |

### Fulfillment

#### `FulfillmentContainer`

**Namespace:** `SalesRender\Plugin\Core\Logistic\Components\Fulfillment\FulfillmentContainer`

Static container for fulfillment handlers. Must be configured in `bootstrap.php` for fulfillment plugins.

| Method | Description |
|--------|-------------|
| `static config(FulfillmentBindingHandlerInterface, FulfillmentSyncHandlerInterface, FulfillmentRemoveHandlerInterface)` | Register all three handlers |
| `static getBindingHandler()` | Get the binding handler |
| `static getSyncHandler()` | Get the sync handler |
| `static getRemoveHandler()` | Get the remove handler |

All getters throw `FulfillmentContainerException` if the handler is not configured.

#### `FulfillmentBindingHandlerInterface`

**Namespace:** `SalesRender\Plugin\Core\Logistic\Components\Fulfillment\FulfillmentBindingHandlerInterface`

| Method | Returns | Description |
|--------|---------|-------------|
| `__invoke(Settings $settings)` | `Binding` | Build product bindings from plugin settings |

Called when settings are saved (in fulfillment mode). The returned `Binding` object is automatically synced to the backend.

#### `FulfillmentSyncHandlerInterface`

**Namespace:** `SalesRender\Plugin\Core\Logistic\Components\Fulfillment\FulfillmentSyncHandlerInterface`

| Method | Returns | Description |
|--------|---------|-------------|
| `getGraphqlOrderFields()` | `array` | GraphQL field selection for orders (used to fetch order data) |
| `handle(array $graphqlOrder)` | `?string` | Process an order sync; return `null` on success or error message on failure |

#### `FulfillmentRemoveHandlerInterface`

**Namespace:** `SalesRender\Plugin\Core\Logistic\Components\Fulfillment\FulfillmentRemoveHandlerInterface`

| Method | Returns | Description |
|--------|---------|-------------|
| `handle(string $orderId)` | `?string` | Remove an order from the fulfillment system; return `null` on success or error message on failure |

#### `SyncAction`

**Namespace:** `SalesRender\Plugin\Core\Logistic\Components\Actions\Fulfillment\SyncAction`

Special request action for fulfillment order sync. Action name is `'ffSync'`. Automatically fetches the order via GraphQL, calls the `FulfillmentSyncHandlerInterface`, and sends the result to the backend at `/CRM/plugin/logistic/fulfillment/sync`.

#### `BatchFulfillmentHandler`

**Namespace:** `SalesRender\Plugin\Core\Logistic\Components\BatchFulfillmentHandler`

Abstract batch handler for fulfillment operations. Implements `BatchHandlerInterface` and uses `BatchLockTrait`.

| Method | Returns | Description |
|--------|---------|-------------|
| `updateLogistic(Batch $batch, string $orderId, Waybill $waybill)` | `ResponseInterface` | Send logistic update for an order (PUT to `/CRM/plugin/logistic/fulfillment`) |

### Waybill

#### `WaybillContainer`

**Namespace:** `SalesRender\Plugin\Core\Logistic\Components\Waybill\WaybillContainer`

Static container for the waybill form and handler.

| Method | Description |
|--------|-------------|
| `static config(callable $form, WaybillHandlerInterface $handler)` | Register form factory and handler |
| `static getForm(array $context = []): Form` | Get the form (throws `WaybillContainerException` if not configured) |
| `static getHandler(): WaybillHandlerInterface` | Get the handler |

#### `WaybillHandlerInterface`

**Namespace:** `SalesRender\Plugin\Core\Logistic\Components\Waybill\WaybillHandlerInterface`

| Method | Returns | Description |
|--------|---------|-------------|
| `__invoke(Form $form, FormData $data)` | `WaybillResponse` | Process waybill form data and return the response |

#### `WaybillHandlerAction`

**Namespace:** `SalesRender\Plugin\Core\Logistic\Components\Waybill\WaybillHandlerAction`

Internal action class implementing `ActionInterface`. Handles the HTTP endpoint for waybill submission:

1. Gets the form from `WaybillContainer`
2. Validates form data
3. Calls the `WaybillHandlerInterface`
4. Signs the logistic data with JWT
5. Returns the response with `logistic` (JWT token), `address`, `waybill`, and `status`

#### `WaybillResponse`

**Namespace:** `SalesRender\Plugin\Core\Logistic\Components\Waybill\Response\WaybillResponse`

Value object returned by `WaybillHandlerInterface`.

| Property | Type | Description |
|----------|------|-------------|
| `$logistic` | `Logistic` | Logistic data (waybill + status) |
| `$address` | `?WaybillAddress` | Optional updated delivery address |

#### `WaybillAddress`

**Namespace:** `SalesRender\Plugin\Core\Logistic\Components\Waybill\Response\WaybillAddress`

Delivery address associated with a waybill. Implements `JsonSerializable`.

| Property | Type | Description |
|----------|------|-------------|
| `$field` | `string` | The order field name for this address |
| `$address` | `Address` | The address object |

### Track

#### `Track`

**Namespace:** `SalesRender\Plugin\Core\Logistic\Components\Track\Track`

Database model for tracking shipments. Extends `Model` and implements `PluginModelInterface`. Stored in the `tracks` table.

| Property/Method | Type | Description |
|-----------------|------|-------------|
| `$track` | `string` | Tracking number |
| `$shippingId` | `string` | Associated shipping ID |
| `$createdAt` | `int` | Creation timestamp |
| `$nextTrackingAt` | `?int` | Next scheduled tracking check |
| `$lastTrackedAt` | `?int` | Last tracking check timestamp |
| `$statuses` | `LogisticStatus[]` | Array of tracking statuses |
| `$stoppedAt` | `?int` | When tracking was stopped |
| `$waybill` | `Waybill` | Associated waybill |
| `addStatus(LogisticStatus)` | `void` | Add a single status |
| `setStatuses(LogisticStatus[])` | `void` | Set/merge all statuses |
| `setLastTrackedAt()` | `void` | Mark as just tracked |
| `setNextTrackingAt(int $minutes)` | `void` | Schedule next tracking |
| `setStoppedAt()` | `void` | Stop tracking this shipment |
| `static findForTracking(string $segments, int $limit)` | `Track[]` | Find tracks ready for checking |
| `static findByTrack(string $track)` | `Track[]` | Find tracks by tracking number |

The `MAX_TRACKING_TIME` constant is 5 months (150 days). Tracks older than this are not selected for tracking.

Status merging logic (`mergeStatuses`) handles deduplication and automatically maps statuses after `RETURNED` to `RETURNING_TO_SENDER` (when status sorting is enabled).

When new statuses are detected, the `Track` automatically creates a notification (special request) to the SalesRender backend at `/CRM/plugin/logistic/status/{class}`.

#### `TrackGetStatusesAction`

**Namespace:** `SalesRender\Plugin\Core\Logistic\Components\Actions\Track\TrackGetStatusesAction`

HTTP action for the `GET /protected/track/statuses/{trackNumber}` endpoint. Finds the track by number, resolves and sorts statuses, and returns them as JSON.

### Binding

#### `Binding`

**Namespace:** `SalesRender\Plugin\Core\Logistic\Components\Binding\Binding`

Database model for product bindings (SKU mappings). Extends `Model` and implements `SinglePluginModelInterface`. Stored in the `bindings` table.

| Method | Returns | Description |
|--------|---------|-------------|
| `getPairs()` | `BindingPair[]` | Get all binding pairs |
| `getPairBySku(int $itemId, int $variation)` | `?BindingPair` | Find pair by SalesRender SKU |
| `getPairByExternalId(string $externalId)` | `?BindingPair` | Find pair by external product ID |
| `setPair(BindingPair $pair)` | `void` | Add or update a binding pair |
| `deletePair(BindingPair $pair)` | `void` | Remove a binding pair |
| `clearAllPairs()` | `void` | Remove all pairs |
| `sync()` | `void` | Sync bindings to SalesRender backend (PUT to `/CRM/plugin/logistic/fulfillment/stock`) |
| `static find()` | `Binding` | Find or create the binding for current plugin reference |

The `sync()` method signs the stock data with a JWT token and queues a `SpecialRequestTask`.

#### `BindingPair`

**Namespace:** `SalesRender\Plugin\Core\Logistic\Components\Binding\BindingPair`

A single SKU-to-external-product mapping with stock balances. Implements `JsonSerializable`.

| Method | Returns | Description |
|--------|---------|-------------|
| `__construct(int $itemId, int $variation, string $externalId, array $balances)` | -- | Create a binding pair |
| `getItemId()` | `int` | SalesRender item ID |
| `getVariation()` | `int` | SalesRender variation |
| `getExternalId()` | `string` | External product identifier (defaults to `{itemId}_{variation}` if empty) |
| `getBalanceByLabel(string $label)` | `?int` | Get balance for a specific label |
| `getBalances()` | `array` | Get all balances as associative array |

#### `FulfillmentSyncCommand`

**Namespace:** `SalesRender\Plugin\Core\Logistic\Components\Commands\FulfillmentSyncCommand`

Console command `fulfillment:sync` that iterates over all `Binding` records matching the criteria and calls `Binding::sync()` on each. Uses mutex locking to prevent concurrent execution.

### Helpers & Services

#### `LogisticHelper`

**Namespace:** `SalesRender\Plugin\Core\Logistic\Helpers\LogisticHelper`

Utility class for logistic mode detection and configuration.

| Method | Returns | Description |
|--------|---------|-------------|
| `static config(bool $sortTrackStatuses = true)` | `void` | Configure track status sorting behavior |
| `static isFulfillment()` | `bool` | Check if current plugin is in fulfillment mode |
| `static isSortTrackStatuses()` | `bool` | Check if track status sorting is enabled |

#### `OrderFetcherIterator`

**Namespace:** `SalesRender\Plugin\Core\Logistic\Components\OrderFetcherIterator`

Iterator for fetching orders via the SalesRender GraphQL API. Extends `ApiFetcherIterator`. Uses the `ordersFetcher` GraphQL query.

#### `BatchLockTrait`

**Namespace:** `SalesRender\Plugin\Core\Logistic\Components\Traits\BatchLockTrait`

Trait providing order locking functionality for batch handlers.

| Method | Returns | Description |
|--------|---------|-------------|
| `lockOrder(int $timeout, int $orderId, Batch $batch)` | `bool` | Lock an order via GraphQL mutation for the specified timeout (seconds) |

#### `LogisticStatusesResolverService`

**Namespace:** `SalesRender\Plugin\Core\Logistic\Services\LogisticStatusesResolverService`

Service for sorting and resolving logistic statuses.

| Method | Returns | Description |
|--------|---------|-------------|
| `__construct(Track $track)` | -- | Initialize with a track |
| `sort()` | `LogisticStatus[]` | Sort statuses according to `LogisticStatus::values()` order, then by timestamp |
| `getLastStatusForNotify()` | `?LogisticStatus` | Get the latest status that has not been notified yet |

### Exceptions

| Exception | Thrown By | Description |
|-----------|----------|-------------|
| `ShippingContainerException` | `ShippingContainer` | Container not configured |
| `FulfillmentContainerException` | `FulfillmentContainer` | Container not configured |
| `FulfillmentSyncException` | `SyncAction` | Plugin not registered |
| `WaybillContainerException` | `WaybillContainer` | Form or handler not configured |
| `BindingSyncException` | `Binding::sync()` | Plugin not registered |
| `TrackException` | `Track::createNotification()` | Plugin not registered |

## Special Requests

| Sender | HTTP Method | Backend Endpoint | Description |
|--------|-------------|------------------|-------------|
| `BatchShippingHandler` (various) | POST/PATCH/POST | `/CRM/plugin/logistic/shipping/...` | Shipping lifecycle operations |
| `BatchFulfillmentHandler` | PUT | `/CRM/plugin/logistic/fulfillment` | Fulfillment logistic updates |
| `SyncAction` | PUT | `/CRM/plugin/logistic/fulfillment/sync` | Individual order sync results |
| `Binding::sync()` | PUT | `/CRM/plugin/logistic/fulfillment/stock` | Stock/balance data |
| `Track::createNotification()` | PATCH | `/CRM/plugin/logistic/status/{class}` | Tracking status notifications |

## Example Plugin

A complete working example is available at [`salesrender/plugin-logistic-example`](https://github.com/SalesRender/plugin-logistic-example).

The example demonstrates a **shipping** plugin with:

- **`BatchShippingHandler`** -- full batch shipping workflow: creates shipping, iterates orders with `OrderFetcherIterator`, locks orders, adds orders to shipping, marks as exported/failed
- **`WaybillHandler`** -- creates waybills with track numbers, pricing, delivery terms, delivery type, and COD support
- **`WaybillForm`** -- complete waybill form with delivery fields (track, price, terms, type, COD) and address fields (postcode, region, city, address, country, coordinates)
- **`Batch_1`** -- batch form with sender selection and delivery type override
- **`CancelAction`** and **`RemoveOrdersAction`** -- stub implementations of shipping cancel and order removal
- **`SettingsForm`** -- settings form with login/password and multiple sender configurations

## Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| [`salesrender/plugin-core`](https://github.com/SalesRender/plugin-core) | ^0.4.0 | Base plugin framework |
| [`salesrender/plugin-core-geocoder`](https://github.com/SalesRender/plugin-core-geocoder) | ^0.3.0 | Geocoding support |
| [`salesrender/plugin-component-logistic`](https://github.com/SalesRender/plugin-component-logistic) | ^2.0.0 | Logistic data models (Waybill, LogisticStatus, Logistic, etc.) |
| [`salesrender/plugin-component-purpose`](https://github.com/SalesRender/plugin-component-purpose) | ^2.0 | Plugin type definitions (`LogisticPluginClass`) |
| `xakepehok/array-to-uuid-helper` | ^0.1.0 | UUID generation from arrays (for binding change detection) |

## See Also

- [salesrender/plugin-core](https://github.com/SalesRender/plugin-core) -- Base plugin framework
- [salesrender/plugin-core-geocoder](https://github.com/SalesRender/plugin-core-geocoder) -- Geocoder plugin core
- [salesrender/plugin-logistic-example](https://github.com/SalesRender/plugin-logistic-example) -- Complete logistic plugin example
- [salesrender/plugin-core-pbx](https://github.com/SalesRender/plugin-core-pbx) -- PBX plugin core
