<?php
/**
 * Created for plugin-core-logistic
 * Date: 17.01.2024
 * @author: Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Logistic\Components\Binding;

use JsonSerializable;

final class BindingPair implements JsonSerializable
{

    private int $itemId;
    private int $variation;
    private string $externalId;
    private array $balances;

    public function __construct(
        int    $itemId,
        int    $variation,
        string $externalId,
        array  $balances
    )
    {
        $this->itemId = $itemId;
        $this->variation = $variation;
        $this->externalId = trim($externalId);
        $this->balances = $balances;
    }

    public function getItemId(): int
    {
        return $this->itemId;
    }

    public function getVariation(): int
    {
        return $this->variation;
    }

    public function getExternalId(): string
    {
        if (empty($this->externalId)) {
            return implode("_", [$this->itemId, $this->variation]);
        }
        return $this->externalId;
    }

    public function getBalanceByLabel(string $label): ?int
    {
        return $this->balances[$label] ?? null;
    }

    public function getBalances(): array
    {
        return $this->balances;
    }

    public function jsonSerialize(): array
    {
        $data = [
            'itemId' => $this->itemId,
            'variation' => $this->variation,
            'balances' => $this->balances,
        ];

        if (!empty($this->externalId)) {
            $data['externalId'] = $this->externalId;
        }

        return $data;
    }
}