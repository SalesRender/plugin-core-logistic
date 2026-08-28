<?php

namespace SalesRender\Components\Track;

use Lcobucci\JWT\Parser;
use Medoo\Medoo;
use ReflectionClass;
use ReflectionProperty;
use SalesRender\Helpers\LogisticTestCase;
use SalesRender\Plugin\Components\Access\Registration\Registration;
use SalesRender\Plugin\Components\Db\Components\Connector;
use SalesRender\Plugin\Components\Db\Components\PluginReference;
use SalesRender\Plugin\Components\Info\Developer;
use SalesRender\Plugin\Components\Info\Info;
use SalesRender\Plugin\Components\Info\PluginType;
use SalesRender\Plugin\Components\Logistic\LogisticStatus;
use SalesRender\Plugin\Components\Logistic\Waybill\Waybill;
use SalesRender\Plugin\Core\Logistic\Components\Track\Track;
use SalesRender\Plugin\Core\Logistic\Helpers\LogisticHelper;

class TrackIndexTest extends LogisticTestCase
{
    private const REFERENCE = ['companyId' => '1', 'alias' => 'logistic', 'id' => '1'];

    private Track $track;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        Connector::config(new Medoo([
            'database_type' => 'sqlite',
            'database_file' => __DIR__ . '/../../../testDB.db'
        ]));
        Connector::setReference(new PluginReference(
            self::REFERENCE['companyId'],
            self::REFERENCE['alias'],
            self::REFERENCE['id']
        ));

        self::createTableIfNotExists(Registration::tableName(), implode(', ', [
            'id VARCHAR(255) NOT NULL PRIMARY KEY',
            'companyId INT NOT NULL',
            'pluginAlias VARCHAR(255) NOT NULL',
            'pluginId INT NOT NULL',
            'registeredAt INT NOT NULL',
            'HPT VARCHAR(512) NOT NULL',
            'country CHAR(2) NOT NULL',
            'currency CHAR(3) NOT NULL',
            'clusterUri VARCHAR(512) NOT NULL',
        ]));

        self::createTableIfNotExists('SpecialRequestTask', implode(', ', [
            'id VARCHAR(255) NOT NULL PRIMARY KEY',
            'companyId VARCHAR(255)',
            'pluginAlias VARCHAR(255)',
            'pluginId VARCHAR(255)',
            'createdAt INT NOT NULL',
            'attemptLastTime INT',
            'attemptNumber INT NOT NULL',
            'attemptLimit INT NOT NULL',
            'attemptInterval INT NOT NULL',
            'attemptLog VARCHAR(500)',
            'request MEDIUMTEXT NOT NULL',
            'httpTimeout INT NOT NULL',
        ]));

        $_ENV['LV_PLUGIN_SELF_URI'] = 'https://plugin.example.com';

        Info::config(
            new PluginType(PluginType::LOGISTIC),
            'Test plugin',
            'Test plugin description',
            ['class' => 'delivery'],
            new Developer('Test', 'test@example.com', 'example.com')
        );

        self::saveRegistration();
    }

    public static function tearDownAfterClass(): void
    {
        Connector::db()->exec('DROP TABLE IF EXISTS `' . Registration::tableName() . '`');
        Connector::db()->exec('DROP TABLE IF EXISTS `SpecialRequestTask`');
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        LogisticHelper::config(true);

        $this->track = new Track(
            new PluginReference(self::REFERENCE['companyId'], self::REFERENCE['alias'], self::REFERENCE['id']),
            new Waybill(new \SalesRender\Plugin\Components\Logistic\Waybill\Track('123456'), 100),
            'shipping-index-test',
            'order-index-test-' . uniqid()
        );
        $this->track->save();
    }

    protected function tearDown(): void
    {
        $this->track->delete();
        Connector::db()->delete('SpecialRequestTask', [
            'companyId' => self::REFERENCE['companyId'],
            'pluginAlias' => self::REFERENCE['alias'],
            'pluginId' => self::REFERENCE['id'],
        ]);
        parent::tearDown();
    }

    public function testWithIndexDoesNotChangeHash(): void
    {
        $status = new LogisticStatus(LogisticStatus::DELIVERED, 'delivered', strtotime('-1 hour'));
        $indexed = $status->withIndex(7);

        $this->assertNull($status->getIndex());
        $this->assertSame(7, $indexed->getIndex());
        $this->assertSame($status->getHash(), $indexed->getHash());
    }

    public function testNotificationPayloadContainsIndex(): void
    {
        $this->track->addStatus(new LogisticStatus(LogisticStatus::REGISTERED, 'registered', strtotime('-2 hours')));

        $statuses = $this->track->getStatuses();
        $this->assertCount(1, $statuses);
        $this->assertSame(1, $statuses[0]->getIndex());
        $this->assertSame([$statuses[0]->getHash()], $this->track->getNotificationsHashes());

        $bodies = $this->getNotificationBodies();
        $this->assertCount(1, $bodies);

        $body = $bodies[0];
        $this->assertSame(1, $body['status']['index']);
        $this->assertSame(LogisticStatus::REGISTERED, $body['status']['code']);

        $last = $body['statuses'][count($body['statuses']) - 1];
        $this->assertSame(1, $last['index']);
    }

    public function testIndexIsMonotonicAcrossNotifications(): void
    {
        $this->track->addStatus(new LogisticStatus(LogisticStatus::REGISTERED, 'registered', strtotime('-3 hours')));
        $this->track->addStatus(new LogisticStatus(LogisticStatus::IN_TRANSIT, 'in transit', strtotime('-2 hours')));

        $statuses = $this->track->getStatuses();
        $this->assertSame([1, 2], array_map(fn(LogisticStatus $status) => $status->getIndex(), $statuses));
        $this->assertCount(2, $this->track->getNotificationsHashes());

        $bodies = $this->getNotificationBodies();
        $this->assertCount(2, $bodies);

        $this->assertSame(1, $bodies[0]['status']['index']);
        $this->assertSame(2, $bodies[1]['status']['index']);

        $indexesByCode = [];
        foreach ($bodies[1]['statuses'] as $status) {
            $indexesByCode[$status['code'] . ':' . $status['text']] = $status['index'];
        }
        $this->assertSame(1, $indexesByCode[LogisticStatus::REGISTERED . ':registered']);
        $this->assertSame(2, $indexesByCode[LogisticStatus::IN_TRANSIT . ':in transit']);
    }

    public function testIndexSurvivesReload(): void
    {
        $this->track->addStatus(new LogisticStatus(LogisticStatus::REGISTERED, 'registered', strtotime('-2 hours')));
        $this->track->addStatus(new LogisticStatus(LogisticStatus::IN_TRANSIT, 'in transit', strtotime('-1 hour')));
        $this->track->save();

        Track::freeUpMemory();
        /** @var Track $reloaded */
        $reloaded = Track::findById($this->track->getId());
        $this->assertNotNull($reloaded);

        $statuses = $reloaded->getStatuses();
        $this->assertSame([1, 2], array_map(fn(LogisticStatus $status) => $status->getIndex(), $statuses));

        $hashes = array_map(fn(LogisticStatus $status) => $status->getHash(), $statuses);
        $this->assertSame($reloaded->getNotificationsHashes(), $hashes);
    }

    public function testStatusesWithoutIndexAreReadAsNull(): void
    {
        $this->track->addStatus(new LogisticStatus(LogisticStatus::REGISTERED, 'registered', strtotime('-2 hours')));

        $legacy = [];
        foreach ($this->track->getStatuses() as $status) {
            $legacy[] = [
                'timestamp' => $status->getTimestamp(),
                'code' => $status->getCode(),
                'text' => $status->getText(),
                'office' => $status->getOffice(),
            ];
        }
        Connector::db()->update('tracks', ['statuses' => json_encode($legacy)], [
            'companyId' => self::REFERENCE['companyId'],
            'pluginAlias' => self::REFERENCE['alias'],
            'pluginId' => self::REFERENCE['id'],
            'id' => $this->track->getId(),
        ]);

        Track::freeUpMemory();
        /** @var Track $reloaded */
        $reloaded = Track::findById($this->track->getId());
        $this->assertNotNull($reloaded);
        foreach ($reloaded->getStatuses() as $status) {
            $this->assertNull($status->getIndex());
        }
    }

    private function getNotificationBodies(): array
    {
        $rows = Connector::db()->select('SpecialRequestTask', ['request'], [
            'ORDER' => ['createdAt' => 'ASC'],
        ]);

        $bodies = [];
        foreach ($rows as $row) {
            $requestData = json_decode($row['request'], true);
            $body = json_decode(json_encode((new Parser())->parse($requestData['body'])->getClaim('body')), true);
            if ($body['orderId'] === $this->track->getId()) {
                $bodies[] = $body;
            }
        }

        return $bodies;
    }

    private static function createTableIfNotExists(string $table, string $columns): void
    {
        Connector::db()->exec("CREATE TABLE IF NOT EXISTS `{$table}` ({$columns})");
    }

    private static function saveRegistration(): void
    {
        $registration = (new ReflectionClass(Registration::class))->newInstanceWithoutConstructor();

        foreach ([
            'registeredAt' => time(),
            'HPT' => 'test-hpt-secret',
            'clusterUri' => 'https://cluster.example.com',
            'country' => 'RU',
            'currency' => 'RUB',
        ] as $property => $value) {
            $reflectionProperty = new ReflectionProperty(Registration::class, $property);
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($registration, $value);
        }

        $registration->save();
    }
}
