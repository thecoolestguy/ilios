<?php

declare(strict_types=1);

namespace App\Tests\RelationshipVoter;

use App\Classes\SessionUserInterface;
use App\RelationshipVoter\AbstractVoter;
use App\RelationshipVoter\CurriculumInventoryExport as Voter;
use App\Service\PermissionChecker;
use App\Entity\CurriculumInventoryReport;
use App\Entity\CurriculumInventoryExport;
use App\Entity\School;
use App\Service\Config;
use Mockery as m;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class CurriculumInventoryExportTest extends AbstractBase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->permissionChecker = m::mock(PermissionChecker::class);
        $this->voter = new Voter($this->permissionChecker);
    }

    public function testAllowsRootFullAccess()
    {
        $report = m::mock(CurriculumInventoryReport::class);
        $report->shouldReceive('getExport')->andReturn(null);
        $export = m::mock(CurriculumInventoryExport::class);
        $export->shouldReceive('getReport')->andReturn($report);
        $this->checkRootEntityAccess(
            $export,
            [AbstractVoter::VIEW, AbstractVoter::CREATE]
        );
    }

    public function testCanView()
    {
        $token = $this->createMockTokenWithNonRootSessionUser();
        $entity = m::mock(CurriculumInventoryExport::class);
        $response = $this->voter->vote($token, $entity, [AbstractVoter::VIEW]);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $response, "View allowed");
    }

    public function testCanCreate()
    {
        $token = $this->createMockTokenWithNonRootSessionUser();
        $entity = m::mock(CurriculumInventoryExport::class);
        $report = m::mock(CurriculumInventoryReport::class);
        $report->shouldReceive('getExport')->andReturn(null);
        $report->shouldReceive('getId')->andReturn(1);
        $entity->shouldReceive('getReport')->andReturn($report);
        $school = m::mock(School::class);
        $school->shouldReceive('getId')->andReturn(1);
        $report->shouldReceive('getSchool')->andReturn($school);
        $this->permissionChecker->shouldReceive('canUpdateCurriculumInventoryReport')->andReturn(true);
        $response = $this->voter->vote($token, $entity, [AbstractVoter::CREATE]);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $response, "Create allowed");
    }

    public function testCanNotCreate()
    {
        $token = $this->createMockTokenWithNonRootSessionUser();
        $entity = m::mock(CurriculumInventoryExport::class);
        $report = m::mock(CurriculumInventoryReport::class);
        $report->shouldReceive('getId')->andReturn(1);
        $report->shouldReceive('getExport')->andReturn(null);
        $entity->shouldReceive('getReport')->andReturn($report);
        $school = m::mock(School::class);
        $school->shouldReceive('getId')->andReturn(1);
        $report->shouldReceive('getSchool')->andReturn($school);
        $this->permissionChecker->shouldReceive('canUpdateCurriculumInventoryReport')->andReturn(false);
        $response = $this->voter->vote($token, $entity, [AbstractVoter::CREATE]);
        $this->assertEquals(VoterInterface::ACCESS_DENIED, $response, "Create denied");
    }

    public function testRootCannotCreateExportOnFinalizedReport()
    {
        $token = $this->createMockTokenWithRootSessionUser();
        $report = m::mock(CurriculumInventoryReport::class);
        $report->shouldReceive('getExport')->andReturn(m::mock(CurriculumInventoryExport::class));
        $entity = m::mock(CurriculumInventoryExport::class);
        $entity->shouldReceive('getReport')->andReturn($report);
        $response = $this->voter->vote($token, $entity, [ AbstractVoter::CREATE ]);
        $this->assertEquals(VoterInterface::ACCESS_DENIED, $response, "Create allowed");
    }

    public function testCannotCreateExportOnFinalizedReport()
    {
        $token = $this->createMockTokenWithNonRootSessionUser();
        $report = m::mock(CurriculumInventoryReport::class);
        $report->shouldReceive('getExport')->andReturn(m::mock(CurriculumInventoryExport::class));
        $entity = m::mock(CurriculumInventoryExport::class);
        $entity->shouldReceive('getReport')->andReturn($report);
        $response = $this->voter->vote($token, $entity, [ AbstractVoter::CREATE ]);
        $this->assertEquals(VoterInterface::ACCESS_DENIED, $response, "Create allowed");
    }
}
