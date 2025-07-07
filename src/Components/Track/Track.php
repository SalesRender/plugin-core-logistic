<?php

namespace SalesRender\Plugin\Core\Logistic\Components\Track;

use Exception;
use Medoo\Medoo;
use ReflectionException;
use SalesRender\Plugin\Components\Access\Registration\Registration;
use SalesRender\Plugin\Components\Db\Components\Connector;
use SalesRender\Plugin\Components\Db\Components\PluginReference;
use SalesRender\Plugin\Components\Db\Exceptions\DatabaseException;
use SalesRender\Plugin\Components\Db\Helpers\UuidHelper;
use SalesRender\Plugin\Components\Db\Model;
use SalesRender\Plugin\Components\Db\PluginModelInterface;
use SalesRender\Plugin\Components\Info\Info;
use SalesRender\Plugin\Components\Logistic\Exceptions\LogisticOfficePhoneException;
use SalesRender\Plugin\Components\Logistic\Exceptions\LogisticStatusTooLongException;
use SalesRender\Plugin\Components\Logistic\LogisticOffice;
use SalesRender\Plugin\Components\Logistic\LogisticStatus;
use SalesRender\Plugin\Components\Logistic\Waybill\Waybill;
use SalesRender\Plugin\Components\SpecialRequestDispatcher\Components\SpecialRequest;
use SalesRender\Plugin\Components\SpecialRequestDispatcher\Models\SpecialRequestTask;
use SalesRender\Plugin\Core\Logistic\Components\Track\Exception\TrackException;
use SalesRender\Plugin\Core\Logistic\Helpers\LogisticHelper;
use SalesRender\Plugin\Core\Logistic\Services\LogisticStatusesResolverService;
use XAKEPEHOK\EnumHelper\Exception\OutOfEnumException;
use XAKEPEHOK\Path\Path;

class Track extends Model implements PluginModelInterface
{

    const MAX_TRACKING_TIME = 24 * 60 * 60 * 30 * 5;
    protected string $companyId;

    protected string $pluginAlias;

    protected string $pluginId;


    protected string $track;

    protected string $shippingId;

    protected int $createdAt;

    protected ?int $nextTrackingAt = null;

    protected ?int $lastTrackedAt = null;

    protected array $statuses = [];

    protected array $notificationsHashes = [];

    protected ?int $notifiedAt = null;

    protected ?int $stoppedAt = null;

    protected string $segment;

    protected Waybill $waybill;

    public function __construct(PluginReference $pluginReference, Waybill $waybill, string $shippingId, string $orderId)
    {
        $this->companyId = $pluginReference->getCompanyId();
        $this->pluginAlias = $pluginReference->getAlias();
        $this->pluginId = $pluginReference->getId();

        $this->id = $orderId;
        $this->waybill = $waybill;
        $this->track = $waybill->getTrack();
        $this->shippingId = $shippingId;
        $this->createdAt = time();
        $this->segment = mb_substr(md5($waybill->getTrack()), -1);
    }

    public function getCompanyId(): string
    {
        return $this->companyId;
    }

    public function getPluginAlias(): string
    {
        return $this->pluginAlias;
    }

    public function getPluginId(): string
    {
        return $this->pluginId;
    }

    public function getTrack(): string
    {
        return $this->track;
    }

    public function getShippingId(): string
    {
        return $this->shippingId;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function getLastTrackedAt(): ?int
    {
        return $this->lastTrackedAt;
    }

    public function setLastTrackedAt(): void
    {
        $this->lastTrackedAt = time();
    }

    public function getNextTrackingAt(): ?int
    {
        return $this->nextTrackingAt;
    }

    public function setNextTrackingAt(int $minutes): void
    {
        $this->nextTrackingAt = time() + $minutes * 60;
    }

    /**
     * @return LogisticStatus[]
     */
    public function getStatuses(): array
    {
        return $this->statuses;
    }

    /**
     * @param LogisticStatus $status
     * @return void
     * @throws LogisticStatusTooLongException
     * @throws OutOfEnumException
     * @throws TrackException
     */
    public function addStatus(LogisticStatus $status): void
    {
        $filtered = self::mergeStatuses($this->statuses, [$status]);
        $status = end($filtered);
        if ($status !== false) {
            $this->statuses[] = $status;
            $this->statuses = self::removeDuplicateStatuses($this->statuses);

            $this->createNotification();
        }
    }

    /**
     * @param LogisticStatus[] $statuses
     * @return void
     * @throws LogisticStatusTooLongException
     * @throws OutOfEnumException
     * @throws TrackException
     */
    public function setStatuses(array $statuses): void
    {
        $oldStatusesCount = count($this->statuses);
        $this->statuses = self::mergeStatuses($this->statuses, $statuses);
        if ($oldStatusesCount != count($this->statuses)) {
            $this->createNotification();
        }
    }

    /**
     * Фильтрует новые статусы, которые пришли от конкретной реализации плагина логистики.
     * Находим hash от каждого нового статуса и сверяем их с hash уже существующих и оставляем только те, которые не совпали.
     *
     * @param array $current
     * @param array $new
     * @return array
     */
    public static function filterNewStatutes(array $current, array $new): array
    {
        $hashes = array_map(fn(LogisticStatus $status) => $status->getHash(), $current);
        return array_filter($new, fn(LogisticStatus $status) => !in_array($status->getHash(), $hashes));
    }

    /**
     * Данный метод больше необходим для простановки маппинга кода @see LogisticStatus::RETURNING_TO_SENDER.
     *
     * @param LogisticStatus[] $current
     * @param LogisticStatus[] $new
     * @return array
     * @throws LogisticStatusTooLongException
     * @throws OutOfEnumException
     */
    public static function mergeStatuses(array $current, array $new): array
    {
        $new = self::filterNewStatutes($current, $new);
        $current = array_merge($current, $new);

        if (LogisticHelper::isSortTrackStatuses() === false) {
            return self::removeDuplicateStatuses($current);
        }

        /**
         * Проверяем есть ли в статусах @see LogisticStatus::RETURNED
         * если его нет, то просто отдаем отфильтрованные статусы
         */
        $returnStatuses = array_filter($current, function (LogisticStatus $status) {
            return $status->getCode() === LogisticStatus::RETURNED;
        });
        if (empty($returnStatuses)) {
            return $current;
        }

        //сортируем статусы по времени
        usort($current, function (LogisticStatus $status_1, LogisticStatus $status_2) {
            if ($status_1->getTimestamp() === $status_2->getTimestamp()) {
                return 0;
            }
            return ($status_1->getTimestamp() < $status_2->getTimestamp()) ? -1 : 1;
        });

        /**
         * Находим статус возврата и все последующие после него искусственно делаем со статусом @see LogisticStatus::RETURNING_TO_SENDER
         * исключение есть только для статуса @see LogisticStatus::DELIVERED_TO_SENDER
         */
        $afterReturned = false;
        foreach ($current as &$status) {
            if ($afterReturned && $status->getCode() !== LogisticStatus::DELIVERED_TO_SENDER) {
                $status = new LogisticStatus(LogisticStatus::RETURNING_TO_SENDER, $status->getText(), $status->getTimestamp());
            }
            if ($status->getCode() === LogisticStatus::RETURNED) {
                $afterReturned = true;
            }
        }

        /**
         * удаляем повторяющиеся статусы, у которых hash могут совпасть после простановки @see LogisticStatus::RETURNING_TO_SENDER
         */
        return self::removeDuplicateStatuses($current);
    }

    private static function removeDuplicateStatuses(array $statuses): array
    {
        $result = [];
        foreach ($statuses as $item) {
            $result[$item->getHash()] = $item;
        }

        return array_values($result);
    }

    public function getNotificationsHashes(): array
    {
        return $this->notificationsHashes;
    }

    public function setNotified(LogisticStatus $status): void
    {
        $this->notificationsHashes[] = $status->getHash();
        $this->notifiedAt = time();
    }

    public function getNotifiedAt(): ?int
    {
        return $this->notifiedAt;
    }

    protected function createNotification(): void
    {
        $service = new LogisticStatusesResolverService($this);
        $lastStatus = $service->getLastStatusForNotify();
        if ($lastStatus === null) {
            return;
        }

        Connector::setReference(new PluginReference($this->getCompanyId(), $this->getPluginAlias(), $this->getPluginId()));
        $registration = Registration::find();
        if ($registration === null) {
            throw new TrackException('Failed to create notify. Plugin is not registered.');
        }
        $uri = (new Path($registration->getClusterUri()))
            ->down('companies')
            ->down(Connector::getReference()->getCompanyId())
            ->down('CRM/plugin/logistic/status')
            ->down(strtolower(Info::getInstance()->getExtra()['class'] ?? ''))
        ;

        $this->setNotified($lastStatus);

        $jwt = $registration->getSpecialRequestToken([
            'orderId' => $this->getId(),
            'shippingId' => $this->getShippingId(),
            'waybill' => $this->getWaybill()->jsonSerialize(),
            'statuses' => $service->sort(),
            'status' => $lastStatus->jsonSerialize(),
            'data' => null,
            'lockId' => UuidHelper::getUuid(),
        ], 24 * 60 * 60);

        $request = new SpecialRequest(
            'PATCH',
            $uri,
            (string)$jwt,
            time() + 24 * 60 * 60,
            202,
            [410], //410 - logistic removed from order
        );
        $task = new SpecialRequestTask($request);
        $task->save();
    }

    public function getStoppedAt(): ?int
    {
        return $this->stoppedAt;
    }

    public function setStoppedAt(): void
    {
        $this->stoppedAt = time();
    }

    public function getWaybill(): Waybill
    {
        return $this->waybill;
    }

    public function setWaybill(Waybill $waybill): void
    {
        $this->waybill = $waybill;
    }

    /**
     * @param string $segments allowed md5 chars separated by comma
     * @param int $limit
     * @return array
     * @throws Exception
     */
    public static function findForTracking(string $segments = '', int $limit = 3000): array
    {
        if (!empty($segments)) {
            $segments = self::filterSegments($segments);
        }

        $where = self::getTrackingWhereStatement();

        if (!empty($segments)) {
            $where['AND']['segment'] = $segments;
        }
        $where['LIMIT'] = $limit;
        $where['ORDER'] = 'lastTrackedAt';

        return self::findByCondition($where);
    }


    /**
     * WARNING! You should call this method only if you exactly know what do you do
     * @param string $segments allowed md5 chars separated by comma
     * @param int $limit
     * @return array
     * @throws Exception
     * @internal
     */
    public static function findForTrackingWithoutScope(string $segments = '', int $limit = 3000): array
    {
        if (!empty($segments)) {
            $segments = self::filterSegments($segments);
        }

        $where = self::getTrackingWhereStatement();

        if (!empty($segments)) {
            $where['AND']['segment'] = $segments;
        }
        $where['LIMIT'] = $limit;
        $where['ORDER'] = 'lastTrackedAt';

        return self::findByConditionWithoutScope($where);
    }

    /**
     * @param string $track
     * @return array
     * @throws DatabaseException
     * @throws ReflectionException
     */
    public static function findByTrack(string $track): array
    {
        $where = [
            'AND' => [
                'pluginId' => Connector::getReference()->getId(),
                'track' => $track,
            ]
        ];

        return self::findByCondition($where);
    }

    protected static function beforeWrite(array $data): array
    {
        $data = parent::beforeWrite($data);

        $data['statuses'] = json_encode($data['statuses']);
        $data['notificationsHashes'] = json_encode($data['notificationsHashes']);
        $data['waybill'] = json_encode($data['waybill']);
        return $data;
    }

    /**
     * @param array $data
     * @return array
     * @throws LogisticStatusTooLongException
     * @throws OutOfEnumException
     * @throws LogisticOfficePhoneException
     */
    protected static function afterRead(array $data): array
    {
        $data = parent::afterRead($data);

        $data['statuses'] = array_map(function (array $item) {
            return new LogisticStatus($item['code'], $item['text'], $item['timestamp'], LogisticOffice::createFromArray($item['office']));
        }, json_decode($data['statuses'], true));
        $data['notificationsHashes'] = json_decode($data['notificationsHashes'], true);
        $data['waybill'] = Waybill::createFromArray(json_decode($data['waybill'], true));
        return $data;
    }

    private static function filterSegments(string $segments): array
    {
        $segments = explode(',', $segments);
        return array_filter($segments, function ($value) {
            return preg_match('/^[a-fA-F0-9]$/', $value);
        });
    }

    private static function getTrackingWhereStatement(): array
    {
        return [
            'AND' => [
                'createdAt[>=]' => time() - self::MAX_TRACKING_TIME,
                'stoppedAt' => null,
                'OR #nextTrackingAt' => [
                    'nextTrackingAt' => null,
                    'nextTrackingAt[<=]' => time(),
                ],
                'OR #lastTrackedAt' => [
                    'lastTrackedAt' => null,
                    'lastTrackedAt[<=]' => time() - 60 * 60,
                ],
            ],
        ];
    }

    public static function tableName(): string
    {
        return 'tracks';
    }

    public static function schema(): array
    {
        return [
            'companyId' => ['INT', 'NOT NULL'],
            'pluginAlias' => ['VARCHAR(255)', 'NOT NULL'],
            'pluginId' => ['INT', 'NOT NULL'],
            'track' => ['VARCHAR(50)'],
            'shippingId' => ['VARCHAR(50)'],
            'createdAt' => ['INT', 'NOT NULL'],
            'nextTrackingAt' => ['INT', 'NULL', 'DEFAULT NULL'],
            'lastTrackedAt' => ['INT', 'NULL', 'DEFAULT NULL'],
            'statuses' => ['TEXT'],
            'notificationsHashes' => ['TEXT'],
            'notifiedAt' => ['INT'],
            'stoppedAt' => ['INT', 'NULL', 'DEFAULT NULL'],
            'waybill' => ['TEXT'],
            'segment' => ['CHAR(1)'],
        ];
    }

    public static function afterTableCreate(Medoo $db): void
    {
        $db->exec('CREATE INDEX `' . self::tableName() . '_toUpdate` on ' . self::tableName() . ' (`createdAt`, `nextTrackingAt`, `stoppedAt`, `segment`)');
        $db->exec('CREATE INDEX `' . self::tableName() . '_lastTrackedAt` on ' . self::tableName() . ' (`lastTrackedAt`)');
        $db->exec('CREATE INDEX `' . self::tableName() . '_track` on ' . self::tableName() . ' (`pluginId`, `track`)');
        DatabaseException::guard($db);
    }

}