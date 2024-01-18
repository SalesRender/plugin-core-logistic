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
    private int $balance;

    public function __construct(
        int $itemId,
        int $variation,
        string $externalId,
        int $balance
    )
    {
        $this->itemId = $itemId;
        $this->variation = $variation;
        $this->externalId = trim($externalId);
        $this->balance = $balance;
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
        if (empty($externalId)) {
            return implode("_", [$this->itemId, $this->variation]);
        }
        return $this->externalId;
    }

    public function getBalance(): int
    {
        return $this->balance;
    }

    public function jsonSerialize(): array
    {
        $data = [
            'itemId' => $this->itemId,
            'variation' => $this->variation,
            'balance' => $this->balance,
        ];

        if (!empty($this->externalId)) {
            $data['externalId'] = $this->externalId;
        }

        return $data;
    }
}