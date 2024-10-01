<?php

namespace SalesRender\Plugin\Core\Logistic\Services;

use SalesRender\Plugin\Components\Logistic\LogisticStatus;
use SalesRender\Plugin\Core\Logistic\Components\Track\Track;
use SalesRender\Plugin\Core\Logistic\Helpers\LogisticHelper;

class LogisticStatusesResolverService
{
    private Track $track;

    public function __construct(Track $track)
    {
        $this->track = $track;
    }

    /**
     * Данный метод необходим для того, чтобы найти статус для нотификации, если мы нашли актуальный статус, то его отправляем
     * иначе не нужно отправлять нотификацию на backend lv2
     * @return LogisticStatus|null
     */
    public function getLastStatusForNotify(): ?LogisticStatus
    {
        $statuses = $this->sort();
        foreach ($statuses as $i => $status) {
            if ($this->isSentNotify($status)) {
                continue;
            }

            //проверяем есть ли статусы дальше, если есть значит почта проставила статус задним числом и его мы не отправляем
            $nextStatusKey = $i + 1;
            if (array_key_exists($nextStatusKey, $statuses)) {
                continue;
            }

            return $status;
        }
        return null;
    }


    /**
     * Данный метод сортирует статусы согласно порядку в @return LogisticStatus[]
     * @see LogisticStatus::values()
     * если у нас есть несколько статусов под одним кодом, то мы дополнительно их сортируем по времени от меньшего к большему
     */
    public function sort(): array
    {
        if (LogisticHelper::isSortTrackStatuses() === false) {
            return $this->track->getStatuses();
        }

        $result = [];
        foreach (LogisticStatus::values() as $code) {
            $codeStatuses = array_filter($this->track->getStatuses(), fn(LogisticStatus $status) => $status->getCode() === $code);
            usort($codeStatuses, function (LogisticStatus $status_1, LogisticStatus $status_2) {
                if ($status_1->getTimestamp() === $status_2->getTimestamp()) {
                    return 0;
                }
                return ($status_1->getTimestamp() < $status_2->getTimestamp()) ? -1 : 1;
            });
            $result = array_merge($result, $codeStatuses);
        }
        return $result;
    }

    private function isSentNotify(LogisticStatus $status): bool
    {
        return in_array($status->getHash(), $this->track->getNotificationsHashes());
    }

}