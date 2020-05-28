<?php

namespace App\Test\TestCase\Model\Table;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use function count;

class ReportsTableTest extends TestCase
{
    /**
     * Fixtures.
     *
     * @var array
     */
    public $fixtures = [
        'app.Notifications',
        'app.Developers',
        'app.Reports',
        'app.Incidents',
    ];

    public function setUp(): void
    {
        parent::setUp();
        $this->Reports = TableRegistry::getTableLocator()->get('Reports');
    }

    public function testGetIncidents(): void
    {
        $this->Reports->id = 4;
        $incidents = $this->Reports->getIncidents();
        $this->assertInstanceOf('Cake\ORM\Query', $incidents);
        $result = $incidents->hydrate(false)->toArray();
        $this->assertEquals(count($result), 2);

        //$this->Reports->saveField("related_to", null); TODO: fix related to issue
        //$incidents = $this->Report->getIncidents();
        //$this->assertEquals(count($incidents), 2);
    }

    //TODO: will do after related to fix
    //public function testGetRelatedReports() {
    //    $this->Reports->id = 2;
    //    $reports = $this->Reports->getRelatedReports();
    //    $this->assertEquals(count($reports), 0);
    //
    //    $this->Report->read(null, 4);
    //    $reports = $this->Report->getRelatedReports();
    //    $this->assertEquals(count($reports), 1);
    //}

    /**
     * Test for getIncidentsWithDescription
     */
    public function testGetIncidentsWithDescription(): void
    {
        $this->Reports->id = 4;
        $incidents = $this->Reports->getIncidentsWithDescription();
        $this->assertInstanceOf('Cake\ORM\Query', $incidents);
        $result = $incidents->hydrate(false)->toArray();
        $this->assertEquals(count($result), 1);
    }

    public function testGetIncidentsWithDifferentStacktrace(): void
    {
        $this->Reports->id = 4;
        $incidents = $this->Reports->getIncidentsWithDifferentStacktrace();
        $this->assertInstanceOf('Cake\ORM\Query', $incidents);
        $result = $incidents->hydrate(false)->toArray();
        $this->assertEquals(count($result), 1);
    }

    // TODO: will do after related to fix
    //    public function testRemoveFromRelatedGroup() {
    //        $this->Report->read(null, 1);
    //        $this->Report->removeFromRelatedGroup();
    //        $incidents = $this->Report->getIncidents();
    //        $this->assertEquals(count($incidents), 1);
    //    }

    /**
     * Test for getUrl
     */
    public function testGetUrl(): void
    {
        $this->Reports->id = 1;
        $this->assertStringEndsWith(
            '/reports/view/1',
            $this->Reports->getUrl()
        );
    }

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
        $this->Reports->id = 1;
        $result = $this->Reports->getRelatedByField('php_version');
        $this->assertInstanceOf('Cake\ORM\Query', $result);
        $result = $result->hydrate(false)->toArray();
        $expected = [
            [
                'php_version' => '5.5',
                'count' => '1',
            ],
        ];
        $this->assertEquals($expected, $result);
        $this->Reports->id = 4;
        $result = $this->Reports->getRelatedByField('php_version', 1);
        $this->assertInstanceOf('Cake\ORM\Query', $result);
        $result = $result->hydrate(false)->toArray();
        $expected = [
            [
                'php_version' => '5.3',
                'count' => '2',
            ],
        ];
        $this->assertEquals($expected, $result);
        $this->Reports->id = 1;
        $result = $this->Reports->getRelatedByField(
            'php_version',
            10,
            false,
            true,
            '2013-08-29 18:10:01'
        );
        $this->assertInstanceOf('Cake\ORM\Query', $result);
        $result = $result->hydrate(false)->toArray();
        $expected = [
            [
                'php_version' => '5.5',
                'count' => '1',
            ],
        ];
        $this->assertEquals($expected, $result);

        $result = $this->Reports->getRelatedByField('php_version', 10, false, false);
        $this->assertInstanceOf('Cake\ORM\Query', $result);
        $result = $result->hydrate(false)->toArray();
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
        $result = $this->Reports->getRelatedByField('php_version', 10, true);
        $this->assertInstanceOf('Cake\ORM\Query', $result[0]);
        $result[0] = $result[0]->hydrate(false)->toArray();
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
