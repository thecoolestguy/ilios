<?php

declare(strict_types=1);

namespace App\Tests\Endpoints;

use App\Service\InflectorFactory;
use App\Tests\Fixture\LoadAamcPcrsData;
use App\Tests\Fixture\LoadCompetencyData;

/**
 * AamcPcrses API endpoint Test.
 * @group api_5
 */
class AamcPcrsTest extends AbstractReadWriteEndpoint
{
    protected string $testName =  'aamcPcrses';
    protected bool $enableDeleteTestsWithServiceToken = false;
    protected bool $enablePatchTestsWithServiceToken = false;
    protected bool $enablePostTestsWithServiceToken = false;
    protected bool $enablePutTestsWithServiceToken = false;

    protected function getFixtures(): array
    {
        return [
            LoadAamcPcrsData::class,
            LoadCompetencyData::class
        ];
    }

    /**
     * @inheritDoc
     */
    public function putsToTest(): array
    {
        return [
            'description' => ['description', 'lorem ipsum'],
            'competencies' => ['competencies', [3]],
            'id' => ['id', 'new-id', true],
        ];
    }

    /**
     * @inheritDoc
     */
    public function readOnlyPropertiesToTest(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function filtersToTest(): array
    {
        return [
            'id' => [[0], ['id' => 'aamc-pcrs-comp-c0101']],
            'description' => [[1], ['description' => 'second description']],
            'competencies' => [[0], ['competencies' => [1]]],
        ];
    }

    public function testPostTermAamcResourceType(): void
    {
        $jwt = $this->createJwtForRootUser($this->kernelBrowser);
        $dataLoader = $this->getDataLoader();
        $data = $dataLoader->create();
        $postData = $data;
        $this->relatedPostDataTest($data, $postData, $jwt, 'aamcPcrses', 'competencies');
    }

    /**
     * @group twice
     */
    public function testInflection(): void
    {
        $singular = 'aamcPcrs';
        $plural = 'aamcPcrses';
        $inflector = InflectorFactory::create();
        $inflectedPlural = $inflector->pluralize($singular);
        $inflectedSingular = $inflector->singularize($plural);

        $this->assertEquals($singular, $inflectedSingular, 'correctly singularized');
        $this->assertEquals($plural, $inflectedPlural, 'correctly pluralized');

        $unInflectedPlural = $inflector->pluralize($plural);
        $unInflectedSingular = $inflector->singularize($singular);

        $this->assertEquals($singular, $unInflectedSingular, 'correctly singularized');
        $this->assertEquals($plural, $unInflectedPlural, 'correctly pluralized');

        $camelPlural = $inflector->camelize('AamcPcrses');
        $this->assertSame($camelPlural, 'aamcPcrses');

        $camelSingular = $inflector->camelize('AamcPcrs');
        $this->assertSame($camelSingular, 'aamcPcrs');
    }

    /**
     * @group twice
     */
    public function testLowerCaseInflection(): void
    {
        $singular = 'aamcpcrs';
        $plural = 'aamcpcrses';
        $inflector = InflectorFactory::create();
        $inflectedPlural = $inflector->pluralize($singular);
        $inflectedSingular = $inflector->singularize($plural);

        $this->assertEquals($singular, $inflectedSingular, 'correctly singularized');
        $this->assertEquals($plural, $inflectedPlural, 'correctly pluralized');

        $unInflectedPlural = $inflector->pluralize($plural);
        $unInflectedSingular = $inflector->singularize($singular);

        $this->assertEquals($singular, $unInflectedSingular, 'correctly singularized');
        $this->assertEquals($plural, $unInflectedPlural, 'correctly pluralized');

        $camelPlural = $inflector->camelize('aamcpcrses');
        $this->assertSame($camelPlural, 'aamcpcrses');

        $camelSingular = $inflector->camelize('aamcpcrs');
        $this->assertSame($camelSingular, 'aamcpcrs');
    }

    public function testAccessDeniedWithServiceToken(): void
    {
        $jwt = $this->createJwtFromServiceTokenWithWriteAccessInAllSchools(
            $this->kernelBrowser,
            $this->fixtures
        );
        $data = $this->getDataLoader()->getOne();
        $this->canNot(
            $this->kernelBrowser,
            $jwt,
            'DELETE',
            $this->getUrl(
                $this->kernelBrowser,
                'app_api_aamcpcrses_delete',
                ['version' => $this->apiVersion, 'id' => $data['id']],
            ),
        );
        $this->canNot(
            $this->kernelBrowser,
            $jwt,
            'POST',
            $this->getUrl(
                $this->kernelBrowser,
                'app_api_aamcpcrses_post',
                ['version' => $this->apiVersion],
            ),
            json_encode([])
        );
        $this->canNotJsonApi(
            $this->kernelBrowser,
            $jwt,
            'POST',
            $this->getUrl(
                $this->kernelBrowser,
                'app_api_aamcpcrses_post',
                ['version' => $this->apiVersion],
            ),
            json_encode([])
        );
        $this->canNot(
            $this->kernelBrowser,
            $jwt,
            'PUT',
            $this->getUrl(
                $this->kernelBrowser,
                'app_api_aamcpcrses_put',
                ['version' => $this->apiVersion, 'id' => $data['id']],
            ),
            json_encode([])
        );
        $this->canNotJsonApi(
            $this->kernelBrowser,
            $jwt,
            'PATCH',
            $this->getUrl(
                $this->kernelBrowser,
                'app_api_aamcpcrses_patch',
                ['version' => $this->apiVersion, 'id' => $data['id']],
            ),
            json_encode([])
        );
    }
}
