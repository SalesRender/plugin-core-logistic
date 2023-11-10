<?php

namespace SalesRender\Components\Track;

use Medoo\Medoo;
use SalesRender\Helpers\LogisticTestCase;
use SalesRender\Plugin\Components\Db\Components\Connector;
use SalesRender\Plugin\Components\Db\Components\PluginReference;
use SalesRender\Plugin\Core\Logistic\Components\Track\Track;

class FindForTrackingTest extends LogisticTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        Connector::config(new Medoo([
            'database_type' => 'sqlite',
            'database_file' => __DIR__ . '/../../../testDB.db'
        ]));

        Connector::setReference(new PluginReference(1, 'logistic', 1));
    }

    public function testFindForTracking()
    {
        $tracks = Track::findForTracking();
        $this->assertCount(3, $tracks);
        $this->assertEquals([1, 2, 3], array_keys($tracks));
        foreach ($tracks as $trackId => $track) {
            /** @var Track $track */
            switch ($trackId) {
                case 1:
                    $expected = 'track1';
                    break;
                case 2:
                    $expected = 'track2';
                    break;
                case 3:
                    $expected = 'track3';
                    break;
                default:
                    $expected = '';
            }
            $this->assertEquals($expected, $track->getTrack());
        }
    }

    public function testFindForTrackingSegment()
    {
        $tracks = Track::findForTracking('1,2');
        self::assertCount(2, $tracks);
        self::assertEquals([2, 3], array_keys($tracks));
        foreach ($tracks as $trackId => $track) {
            /** @var Track $track */
            switch ($trackId) {
                case 2:
                    $expected = 'track2';
                    break;
                case 3:
                    $expected = 'track3';
                    break;
                default:
                    $expected = '';
            }
            $this->assertEquals($expected, $track->getTrack());
        }
    }

    public function testFindForTrackingLimit()
    {
        $tracks = Track::findForTracking('', 1);
        self::assertCount(1, $tracks);
    }

    public function testFindForTrackingWithoutScope()
    {
        $tracks = Track::findForTrackingWithoutScope();
        self::assertCount(8, $tracks);
    }
}