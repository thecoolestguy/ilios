<?php

declare(strict_types=1);

namespace App\Tests\Endpoints;

use App\Tests\ReadWriteEndpointTest;

/**
 * Report API endpoint Test.
 * @group api_4
 * @group time-sensitive
 */
class ReportTest extends ReadWriteEndpointTest
{
    protected string $testName =  'reports';

    /**
     * @inheritdoc
     */
    protected function getFixtures()
    {
        return [
            'App\Tests\Fixture\LoadReportData',
            'App\Tests\Fixture\LoadUserData'
        ];
    }

    /**
     * @inheritDoc
     */
    public function putsToTest()
    {
        return [
            'title' => ['title', $this->getFaker()->text(25)],
            'school' => ['school', 3],
            'subject' => ['subject', $this->getFaker()->text(5)],
            'prepositionalObject' => ['prepositionalObject', $this->getFaker()->text(32)],
            'prepositionalObjectTableRowId' => ['prepositionalObjectTableRowId', '9'],
            'prepositionalObjectTableRowIdString' => ['prepositionalObjectTableRowId', 'DC123'],
        ];
    }

    /**
     * @inheritDoc
     */
    public function readOnlyPropertiesToTest()
    {
        return [
            'id' => ['id', 1, 99],
            'createdAt' => ['createdAt', 1, 99],
        ];
    }

    /**
     * @inheritDoc
     */
    public function filtersToTest()
    {
        return [
            'id' => [[0], ['id' => 1]],
            'ids' => [[1, 2], ['id' => [2, 3]]],
            'title' => [[1], ['title' => 'second report']],
            'school' => [[2], ['school' => 1]],
            'subject' => [[2], ['subject' => 'subject3']],
            'prepositionalObject' => [[2], ['prepositionalObject' => 'object3']],
            'prepositionalObjectTableRowId' => [[1], ['prepositionalObjectTableRowId' => 14]],
            'prepositionalObjectTableRowIdString' => [[1], ['prepositionalObjectTableRowId' => '14']],
            'user' => [[0, 1, 2], ['user' => 2]],
        ];
    }

    protected function getTimeStampFields()
    {
        return ['createdAt'];
    }
}
