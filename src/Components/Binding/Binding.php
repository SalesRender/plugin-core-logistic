<?php
/**
 * Created for plugin-core-logistic
 * Date: 16.01.2024
 * @author: Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Logistic\Components\Binding;

use SalesRender\Plugin\Components\Access\Registration\Registration;
use SalesRender\Plugin\Components\Db\Components\Connector;
use SalesRender\Plugin\Components\Db\Model;
use SalesRender\Plugin\Components\Db\SinglePluginModelInterface;
use SalesRender\Plugin\Components\SpecialRequestDispatcher\Components\SpecialRequest;
use SalesRender\Plugin\Components\SpecialRequestDispatcher\Models\SpecialRequestTask;
use SalesRender\Plugin\Core\Logistic\Components\Binding\Exception\BindingSyncException;
use XAKEPEHOK\ArrayToUuidHelper\ArrayToUuidHelper;
use XAKEPEHOK\Path\Path;

final class Binding extends Model implements SinglePluginModelInterface
{

    protected int $updatedAt;
    protected ?int $syncedAt = null;

    /** @var BindingPair[] */
    protected array $pairs = [];

    /** @var BindingPair[] */
    private array $indexByExternalId = [];

    public function __construct()
    {
        $this->updatedAt = time();
    }

    public function getUpdatedAt(): int
    {
        return $this->updatedAt;
    }

    public function getSyncedAt(): ?int
    {
        return $this->syncedAt;
    }

    /**
     * @return BindingPair[]
     */
    public function getPairs(): array
    {
        return $this->pairs;
    }

    public function getPairBySku(int $itemId, int $variation): ?BindingPair
    {
        $key = implode('_', [$itemId, $variation]);
        return $this->pairs[$key] ?? null;
    }

    public function getPairByExternalId(string $externalId): ?BindingPair
    {
        if (empty($this->indexByExternalId)) {
            foreach ($this->pairs as $pair) {
                $this->indexByExternalId[$pair->getExternalId()] = $pair;
            }
        }

        return $this->indexByExternalId[$externalId] ?? null;
    }

    public function setPair(BindingPair $pair): void
    {
        $key = $this->getKey($pair);
        if (isset($this->pairs[$key])) {
            $oldPair = $this->pairs[$key];
            if (ArrayToUuidHelper::generate($oldPair->getBalances()) !== ArrayToUuidHelper::generate($pair->getBalances())) {
                $this->updatedAt = time();
            }
        } else {
            $this->updatedAt = time();
        }

        $this->pairs[$this->getKey($pair)] = $pair;
        $this->indexByExternalId[$pair->getExternalId()] = $pair;
    }

    public function deletePair(BindingPair $pair): void
    {
        $key = $this->getKey($pair);
        if (isset($this->pairs[$key])) {
            $this->updatedAt = time();
        }

        unset($this->pairs[$key]);
        unset($this->indexByExternalId[$pair->getExternalId()]);
    }

    public function clearAllPairs(): void
    {
        $this->pairs = [];
    }

    public function sync(): void
    {
        $registration = Registration::find();
        if ($registration === null) {
            throw new BindingSyncException('Failed to sync balances. Plugin is not registered.');
        }

        $uri = (new Path($registration->getClusterUri()))
            ->down('companies')
            ->down(Connector::getReference()->getCompanyId())
            ->down('CRM/plugin/logistic/fulfillment/stock');

        $this->syncedAt = time();
        $this->save();

        $stock = [];
        foreach ($this->pairs as $pair) {
            $sku = implode('_', [$pair->getItemId(), $pair->getVariation()]);
            $stock[$sku] = $pair->getBalances();
        }

        $jwt = $registration->getSpecialRequestToken($stock, 24 * 60 * 60);

        $request = new SpecialRequest(
            'PUT',
            $uri,
            (string)$jwt,
            time() + 24 * 60 * 60,
            202,
            [421]
        );
        $task = new SpecialRequestTask($request, 60 * 24, 60, 60);
        $task->save();
    }

    /**
     * @return Model|self
     */
    public static function find(): Model
    {
        return self::findById(Connector::getReference()->getId()) ?? new self();
    }

    protected static function beforeWrite(array $data): array
    {
        $pairs = $data['pairs'];
        ksort($pairs);
        $data['pairs'] = json_encode($pairs);
        return $data;
    }

    protected static function afterRead(array $data): array
    {
        $pairs = json_decode($data['pairs'], true);
        $pairs = is_array($pairs) ? $pairs : [];

        $data['pairs'] = array_map(
            function (array $data) {
                return new BindingPair(
                    (int)$data['itemId'],
                    (int)$data['variation'],
                    $data['externalId'] ?? '',
                    $data['balances'],
                );
            },
            $pairs,
        );
        return $data;
    }

    private function getKey(BindingPair $pair): string
    {
        return implode('_', [$pair->getItemId(), $pair->getVariation()]);
    }

    public static function tableName(): string
    {
        return 'bindings';
    }

    public static function schema(): array
    {
        return [
            'updatedAt' => ['INT', 'NOT NULL'],
            'syncedAt' => ['INT', 'NULL'],
            'pairs' => ['TEXT'],
        ];
    }

}