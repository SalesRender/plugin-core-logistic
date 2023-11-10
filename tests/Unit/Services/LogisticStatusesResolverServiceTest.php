<?php

namespace SalesRender\Services;

use Mockery;
use SalesRender\Helpers\LogisticTestCase;
use SalesRender\Plugin\Components\Logistic\Exceptions\LogisticStatusTooLongException;
use SalesRender\Plugin\Components\Logistic\LogisticStatus;
use SalesRender\Plugin\Core\Logistic\Components\Track\Exception\TrackException;
use SalesRender\Plugin\Core\Logistic\Components\Track\Track;
use SalesRender\Plugin\Core\Logistic\Services\LogisticStatusesResolverService;
use XAKEPEHOK\EnumHelper\Exception\OutOfEnumException;

class LogisticStatusesResolverServiceTest extends LogisticTestCase
{
    private Track $track;

    protected function setUp(): void
    {
        $this->track = Mockery::mock(Track::class)->makePartial();
        $this->track->shouldAllowMockingProtectedMethods()->shouldReceive('createNotification')->andReturnNull();
    }

    public function getGetNextStatusForNotifyDataProvider(): array
    {
        return [
            [
                [],
                [],
                null,
            ],
            [
                [
                    new LogisticStatus(LogisticStatus::IN_TRANSIT),
                ],
                [],
                new LogisticStatus(LogisticStatus::IN_TRANSIT),
            ],
            [
                [
                    new LogisticStatus(LogisticStatus::IN_TRANSIT),
                ],
                [
                    new LogisticStatus(LogisticStatus::IN_TRANSIT),
                ],
                null,
            ],
            //проверка на вставку задним числом
            [
                [
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-02')),
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-03')),
                    new LogisticStatus(LogisticStatus::DELIVERED, 'DELIVERED', strtotime('2022-01-04')),
                ],
                [
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-03')),
                ],
                new LogisticStatus(LogisticStatus::DELIVERED, 'DELIVERED', strtotime('2022-01-04')),
            ],
            /**
             * Проверка на отправку @see LogisticStatus::RETURNING_TO_SENDER
             */
            [
                [
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-02')),
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-03')),
                    new LogisticStatus(LogisticStatus::ARRIVED, '', strtotime('2022-01-04')),
                    new LogisticStatus(LogisticStatus::RETURNED, 'RETURNED', strtotime('2022-01-04')),
                    new LogisticStatus(LogisticStatus::RETURNING_TO_SENDER, 'RETURNED IN_TRANSIT', strtotime('2022-01-06')),
                ],
                [
                    new LogisticStatus(LogisticStatus::ARRIVED, '', strtotime('2022-01-04')),
                    new LogisticStatus(LogisticStatus::RETURNED, 'RETURNED', strtotime('2022-01-04')),
                ],
                new LogisticStatus(LogisticStatus::RETURNING_TO_SENDER, 'RETURNED IN_TRANSIT', strtotime('2022-01-06')),
            ],
            [
                [
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-02')),
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-03')),
                    new LogisticStatus(LogisticStatus::ARRIVED, '', strtotime('2022-01-04')),
                    new LogisticStatus(LogisticStatus::RETURNED, 'RETURNED', strtotime('2022-01-04')),
                    new LogisticStatus(LogisticStatus::RETURNING_TO_SENDER, 'RETURNED IN_TRANSIT', strtotime('2022-01-06')),
                    new LogisticStatus(LogisticStatus::DELIVERED_TO_SENDER, 'DELIVERED_TO_SENDER IN_TRANSIT', strtotime('2022-01-06')),
                ],
                [
                    new LogisticStatus(LogisticStatus::ARRIVED, '', strtotime('2022-01-04')),
                    new LogisticStatus(LogisticStatus::RETURNED, 'RETURNED', strtotime('2022-01-04')),
                    new LogisticStatus(LogisticStatus::RETURNING_TO_SENDER, 'RETURNED IN_TRANSIT', strtotime('2022-01-06')),
                ],
                new LogisticStatus(LogisticStatus::DELIVERED_TO_SENDER, 'DELIVERED_TO_SENDER IN_TRANSIT', strtotime('2022-01-06')),
            ],
        ];
    }

    /**
     * @param LogisticStatus[] $statuses
     * @param LogisticStatus[] $sentStatuses
     * @param LogisticStatus|null $expected
     * @return void
     *
     * @throws LogisticStatusTooLongException
     * @throws OutOfEnumException
     * @throws TrackException
     * @dataProvider getGetNextStatusForNotifyDataProvider
     */
    public function testGetNextStatusForNotify(array $statuses, array $sentStatuses, ?LogisticStatus $expected): void
    {
        $this->track->setStatuses($statuses);
        foreach ($sentStatuses as $sentStatus) {
            $this->track->setNotified($sentStatus);
        }
        $service = new LogisticStatusesResolverService($this->track);
        $actual = $service->getLastStatusForNotify();

        if ($expected === null) {
            $this->assertNull($expected);
        } else {
            $this->assertEquals($expected, $actual);
        }
    }

    public function getStatusesForSortDataProvider(): array
    {
        return [
            [
                [
                    new LogisticStatus(LogisticStatus::DELIVERED_TO_SENDER, '', strtotime('2022-01-02')),
                    new LogisticStatus(LogisticStatus::UNREGISTERED, '', strtotime('2022-01-01')),
                ],
                [
                    new LogisticStatus(LogisticStatus::UNREGISTERED, '', strtotime('2022-01-01')),
                    new LogisticStatus(LogisticStatus::DELIVERED_TO_SENDER, '', strtotime('2022-01-02')),
                ],
            ],
            [
                [
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-03')),
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-02')),
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-02')),
                    new LogisticStatus(LogisticStatus::UNREGISTERED, '', strtotime('2022-01-04')),
                    new LogisticStatus(LogisticStatus::UNREGISTERED, '', strtotime('2022-01-01')),
                ],
                [
                    new LogisticStatus(LogisticStatus::UNREGISTERED, '', strtotime('2022-01-01')),
                    new LogisticStatus(LogisticStatus::UNREGISTERED, '', strtotime('2022-01-04')),
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-02')),
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-02')),
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-03')),
                ],
            ],
            [
                [
                    new LogisticStatus(LogisticStatus::UNREGISTERED, '', strtotime('2022-01-01')),
                    new LogisticStatus(LogisticStatus::REGISTERED, '', strtotime('2022-01-03')),
                    new LogisticStatus(LogisticStatus::CREATED, '', strtotime('2022-01-02')),
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-06')),
                    new LogisticStatus(LogisticStatus::ACCEPTED, '', strtotime('2022-01-04')),
                    new LogisticStatus(LogisticStatus::PACKED, '', strtotime('2022-01-05')),
                    new LogisticStatus(LogisticStatus::PENDING, '', strtotime('2022-01-09')),
                    new LogisticStatus(LogisticStatus::ARRIVED, '', strtotime('2022-01-07')),
                    new LogisticStatus(LogisticStatus::ON_DELIVERY, '', strtotime('2022-01-08')),
                    new LogisticStatus(LogisticStatus::PAID, '', strtotime('2022-01-11')),
                    new LogisticStatus(LogisticStatus::DELIVERED, '', strtotime('2022-01-10')),
                    new LogisticStatus(LogisticStatus::RETURNED, '', strtotime('2022-01-12')),
                    new LogisticStatus(LogisticStatus::DELIVERED_TO_SENDER, '', strtotime('2022-01-14')),
                    new LogisticStatus(LogisticStatus::RETURNING_TO_SENDER, '', strtotime('2022-01-13')),
                ],
                [
                    new LogisticStatus(LogisticStatus::UNREGISTERED, '', strtotime('2022-01-01')),
                    new LogisticStatus(LogisticStatus::CREATED, '', strtotime('2022-01-02')),
                    new LogisticStatus(LogisticStatus::REGISTERED, '', strtotime('2022-01-03')),
                    new LogisticStatus(LogisticStatus::ACCEPTED, '', strtotime('2022-01-04')),
                    new LogisticStatus(LogisticStatus::PACKED, '', strtotime('2022-01-05')),
                    new LogisticStatus(LogisticStatus::IN_TRANSIT, '', strtotime('2022-01-06')),
                    new LogisticStatus(LogisticStatus::ARRIVED, '', strtotime('2022-01-07')),
                    new LogisticStatus(LogisticStatus::ON_DELIVERY, '', strtotime('2022-01-08')),
                    new LogisticStatus(LogisticStatus::PENDING, '', strtotime('2022-01-09')),
                    new LogisticStatus(LogisticStatus::DELIVERED, '', strtotime('2022-01-10')),
                    new LogisticStatus(LogisticStatus::PAID, '', strtotime('2022-01-11')),
                    new LogisticStatus(LogisticStatus::RETURNED, '', strtotime('2022-01-12')),
                    new LogisticStatus(LogisticStatus::RETURNING_TO_SENDER, '', strtotime('2022-01-13')),
                    new LogisticStatus(LogisticStatus::DELIVERED_TO_SENDER, '', strtotime('2022-01-14')),
                ],
            ],
            [
                [],
                [],
            ],
        ];
    }

    /**
     * @param LogisticStatus[] $statuses
     * @param LogisticStatus[] $expected
     * @return void
     * @throws LogisticStatusTooLongException
     * @throws OutOfEnumException
     * @throws TrackException
     * @dataProvider getStatusesForSortDataProvider
     */
    public function testSort(array $statuses, array $expected): void
    {
        $this->track->setStatuses($statuses);
        $service = new LogisticStatusesResolverService($this->track);

        $this->assertEquals($expected, $service->sort());
    }

}