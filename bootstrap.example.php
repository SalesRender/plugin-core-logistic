<?php
/**
 * Created for plugin-core-logistic
 * Date: 30.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

use Medoo\Medoo;
use SalesRender\Plugin\Components\Batch\BatchContainer;
use SalesRender\Plugin\Components\Db\Components\Connector;
use SalesRender\Plugin\Components\Form\Autocomplete\AutocompleteRegistry;
use SalesRender\Plugin\Components\Form\Form;
use SalesRender\Plugin\Components\Form\TableView\TablePreviewRegistry;
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
use SalesRender\Plugin\Core\Logistic\Components\BatchShippingHandler;
use SalesRender\Plugin\Core\Logistic\Components\Waybill\WaybillContainer;
use SalesRender\Plugin\Core\Logistic\Components\Waybill\WaybillHandlerInterface;
use XAKEPEHOK\Path\Path;

# 1. Configure DB (for SQLite *.db file and parent directory should be writable)
Connector::config(new Medoo([
    'database_type' => 'sqlite',
    'database_file' => Path::root()->down('db/database.db')
]));

# 2. Set plugin default language
Translator::config('ru_RU');

# 3. Set permitted file extensions (* for any ext) and max sizes (in bytes). Pass empty array for disable file uploading
UploadersContainer::addDefaultUploader(new LocalUploadAction([
    'jpg' => 100 * 1024,       //Max 100 KB for *.jpg file
    'zip' => 10 * 1024 * 1024, //Max 10 MB for *.zip archive
]));

# 4. Configure info about plugin
Info::config(
    new PluginType(PluginType::MACROS),
    fn() => Translator::get('info', 'Plugin name'),
    fn() => Translator::get('info', 'Plugin markdown description'),
    [
        "class" => LogisticPluginClass::CLASS_DELIVERY,
        "entity" => PluginEntity::ENTITY_UNSPECIFIED,
        "country" => "RU",
        "codename" => "DPD",
    ],
    new Developer(
        'Your (company) name',
        'support.for.plugin@example.com',
        'example.com',
    )
);

# 5. Configure settings form
Settings::setForm(fn(array $context) => new Form($context));

# 6. Configure form autocompletes (or remove this block if dont used)
AutocompleteRegistry::config(function (string $name) {
//    switch ($name) {
//        case 'status': return new StatusAutocomplete();
//        case 'user': return new UserAutocomplete();
//        default: return null;
//    }
});

# 7. Configure form autocompletes (or remove this block if dont used)
TablePreviewRegistry::config(function (string $name) {
//    switch ($name) {
//        case 'excel': return new ExcelTablePreview();
//        case 'calc': return new CalcTablePreview();
//        default: return null;
//    }
});

# 8. Configure batch forms and handler (or remove this block if dont used)
BatchContainer::config(
    function (int $number, array $context) {
//    switch ($number) {
//        case 1: return new Form($context);
//        case 2: return new Form($context);
//        case 3: return new Form($context);
//        default: return null;
//    }
    },
    new BatchShippingHandler()
);

# 9. Configure waybill form and handler
WaybillContainer::config(
    fn(array $context) => new WaybillForm($context),
    new WaybillHandlerInterface()
);

# 10. Configure shipping cancel action (optional)
ShippingContainer::config(
    new ShippingCancelAction(),
    new RemoveOrdersAction(),
);