<?php

declare(strict_types=1);

namespace App\Tests\Endpoints;

use App\Tests\ReadWriteEndpointTest;

/**
 * LearnerGroup API endpoint Test.
 * @group api_2
 */
class LearnerGroupTest extends ReadWriteEndpointTest
{
    protected string $testName =  'learnerGroups';

    /**
     * @inheritdoc
     */
    protected function getFixtures()
    {
        return [
            'App\Tests\Fixture\LoadLearnerGroupData',
            'App\Tests\Fixture\LoadCohortData',
            'App\Tests\Fixture\LoadIlmSessionData',
            'App\Tests\Fixture\LoadOfferingData',
            'App\Tests\Fixture\LoadUserData',
            'App\Tests\Fixture\LoadVocabularyData',
            'App\Tests\Fixture\LoadTermData',
        ];
    }

    /**
     * @inheritDoc
     */
    public function putsToTest()
    {
        return [
            'title' => ['title', $this->getFaker()->text(60)],
            'location' => ['location', $this->getFaker()->text(100)],
            'url' => ['url', $this->getFaker()->url(100)],
            'needsAccommodation' => ['needsAccommodation', true],
            'cohort' => ['cohort', 3],
            'parent' => ['parent', 2],
            'ancestor' => ['ancestor', '3'],
            'removeParent' => ['parent', null],
            'children' => ['children', [1], $skipped = true],
            'ilmSessions' => ['ilmSessions', [2]],
            'offerings' => ['offerings', [2]],
            'instructorGroups' => ['instructorGroups', [1, 2]],
            'users' => ['users', [1]],
            'instructors' => ['instructors', [1, 2]],
            'descendants' => ['descendants', [2, 3]],
        ];
    }

    /**
     * @inheritDoc
     */
    public function readOnlyPropertiesToTest()
    {
        return [
            'id' => ['id', 1, 99],
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
            'title' => [[2], ['title' => 'third learner group']],
            'location' => [[3], ['location' => 'fourth location']],
            'url' => [[0, 3], ['url' => 'https://iliosproject.org']],
            'needsAccommodation' => [[1], ['needsAccommodation' => true]],
            'doesNotNeedAccommodation' => [[0, 2, 3, 4], ['needsAccommodation' => false]],
            'cohort' => [[1], ['cohort' => 2]],
            'parent' => [[3], ['parent' => 1]],
            'ancestor' => [[3], ['ancestor' => '3']],
            'noParent' => [[0, 1, 2, 4], ['parent' => 'null']],
            'children' => [[0], ['children' => [4]], $skipped = true],
            'ilmSessions' => [[0, 2], ['ilmSessions' => [1]], $skipped = true],
            'offerings' => [[1, 4], ['offerings' => [2]], $skipped = true],
            'instructorGroups' => [[0], ['instructorGroups' => [1]], $skipped = true],
            'users' => [[0, 4], ['users' => [5]], $skipped = true],
            'instructors' => [[0, 2], ['instructors' => [1]], $skipped = true],
            'cohorts' => [[1], ['cohorts' => [2]]],
        ];
    }

    public function testPostLearnerGroupIlmSession()
    {
        $dataLoader = $this->getDataLoader();
        $data = $dataLoader->create();
        $postData = $data;
        $this->relatedPostDataTest($data, $postData, 'learnerGroups', 'ilmSessions');
    }

    public function testPostLearnerGroupOfferings()
    {
        $dataLoader = $this->getDataLoader();
        $data = $dataLoader->create();
        $postData = $data;
        $this->relatedPostDataTest($data, $postData, 'learnerGroups', 'offerings');
    }
}
