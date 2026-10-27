<?php

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\ReportsTable;
use App\Test\Fixture\DevelopersFixture;
use App\Test\Fixture\IncidentsFixture;
use App\Test\Fixture\NotificationsFixture;
use App\Test\Fixture\ReportsFixture;
use Cake\ORM\Query;
use Cake\ORM\ResultSet;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

use function count;

class ReportsTableTest extends TestCase
{
    protected ReportsTable $Reports;

    public function getFixtures(): array
    {
        return [
            NotificationsFixture::class,
            DevelopersFixture::class,
            ReportsFixture::class,
            IncidentsFixture::class,
        ];
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->Reports = TableRegistry::getTableLocator()->get('Reports');
    }

    public function testGetIncidents(): void
    {
        $incidents = $this->Reports->getIncidents(4);
        $this->assertInstanceOf('Cake\ORM\Query', $incidents);
        $result = $incidents->enableHydration(false)->toArray();
        $this->assertEquals(count($result), 2);

        //$this->Reports->saveField("related_to", null); TODO: fix related to issue
        //$incidents = $this->Report->getIncidents();
        //$this->assertEquals(count($incidents), 2);
    }

    public function testGetRelatedReports(): void
    {
        $reports = $this->Reports->getRelatedReports(2)->all();
        $this->assertCount(0, $reports);

        $this->Reports->updateAll(['related_to' => 4], ['id' => 5]);
        $reports = $this->Reports->getRelatedReports(4)->all();
        $this->assertCount(1, $reports);
    }

    /**
     * Test for getIncidentsWithDescription
     */
    public function testGetIncidentsWithDescription(): void
    {
        $incidents = $this->Reports->getIncidentsWithDescription(4);
        $this->assertInstanceOf('Cake\ORM\Query', $incidents);
        $result = $incidents->enableHydration(false)->toArray();
        $this->assertEquals(count($result), 1);
    }

    public function testGetIncidentsWithDifferentStacktrace(): void
    {
        $incidents = $this->Reports->getIncidentsWithDifferentStacktrace(4);
        $this->assertInstanceOf('Cake\ORM\Query', $incidents);
        $result = $incidents->enableHydration(false)->toArray();
        $this->assertEquals(count($result), 1);
    }

    // TODO: will do after related to fix
    //    public function testRemoveFromRelatedGroup() {
    //        $this->Report->read(null, 1);
    //        $this->Report->removeFromRelatedGroup();
    //        $incidents = $this->Report->getIncidents();
    //        $this->assertEquals(count($incidents), 1);
    //    }

    // TODO: will do after realted to fix
    //public function testAddToRelatedGroup() {
    //    $this->Report->read(null, 2);
    //    $this->Report->addToRelatedGroup(4);
    //
    //    $this->Report->read(null, 2);
    //    $incidents = $this->Report->getIncidents();
    //    $this->assertEquals(count($incidents), 3);
    //
    //    $this->Report->saveField("related_to", null);
    //    $this->Report->addToRelatedGroup(1);
    //    $this->Report->read(null, 2);
    //    $incidents = $this->Report->getIncidents();
    //    $this->assertEquals(count($incidents), 3);
    //}

    /**
     * Test for getRelatedByField
     */
    public function testGetRelatedByField(): void
    {
        $result = $this->Reports->getRelatedByField('php_version', 10, false, 1);
        $this->assertInstanceOf(Query::class, $result);
        $result = $result->enableHydration(false)->toArray();
        $expected = [
            [
                'php_version' => '5.5',
                'count' => '1',
            ],
        ];
        $this->assertEquals($expected, $result);
        $result = $this->Reports->getRelatedByField('php_version', 1, false, 4);
        $this->assertInstanceOf(Query::class, $result);
        $result = $result->enableHydration(false)->toArray();
        $expected = [
            [
                'php_version' => '5.3',
                'count' => '2',
            ],
        ];
        $this->assertEquals($expected, $result);
        $result = $this->Reports->getRelatedByField(
            'php_version',
            10,
            false,
            1,
            '2013-08-29 18:10:01',
        );
        $this->assertInstanceOf(Query::class, $result);
        $result = $result->enableHydration(false)->toArray();
        $expected = [
            [
                'php_version' => '5.5',
                'count' => '1',
            ],
        ];
        $this->assertEquals($expected, $result);

        $result = $this->Reports->getRelatedByField('php_version', 10, false, null);
        $this->assertInstanceOf(Query::class, $result);
        $result = $result->enableHydration(false)->toArray();
        $expected = [
            [
                'php_version' => '5.5',
                'count' => '1',
            ],
            [
                'php_version' => '5.3',
                'count' => '4',
            ],
        ];
        $this->assertEquals($expected, $result);
        $result = $this->Reports->getRelatedByField('php_version', 10, true, 1);
        $this->assertInstanceOf(ResultSet::class, $result[0]);
        $result[0] = $result[0]->toArray();
        $result[0][0] = $result[0][0]->toArray();
        $expected = [
            [
                [
                    'php_version' => '5.5',
                    'count' => '1',
                ],
            ],
            1,
        ];
        $this->assertEquals($expected, $result);
    }
}
