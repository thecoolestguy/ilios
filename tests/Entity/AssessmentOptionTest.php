<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\AssessmentOption;
use Mockery as m;

/**
 * Tests for Entity AssessmentOption
 * @group model
 */
class AssessmentOptionTest extends EntityBase
{
    /**
     * @var AssessmentOption
     */
    protected $object;

    /**
     * Instantiate a AssessmentOption object
     */
    protected function setUp(): void
    {
        $this->object = new AssessmentOption();
    }


    public function testNotBlankValidation()
    {
        $notBlank = [
            'name'
        ];
        $this->validateNotBlanks($notBlank);

        $this->object->setName('Smorgasbord');
        $this->validate(0);
    }

    /**
     * @covers \App\Entity\AssessmentOption::__construct
     */
    public function testConstructor()
    {
        $this->assertEmpty($this->object->getSessionTypes());
    }

    /**
     * @covers \App\Entity\AssessmentOption::setName
     * @covers \App\Entity\AssessmentOption::getName
     */
    public function testSetName()
    {
        $this->basicSetTest('name', 'string');
    }

    /**
     * @covers \App\Entity\AssessmentOption::addSessionType
     */
    public function testAddSessionType()
    {
        $this->entityCollectionAddTest('sessionType', 'SessionType');
    }

    /**
     * @covers \App\Entity\AssessmentOption::removeSessionType
     */
    public function testRemoveSessionType()
    {
        $this->entityCollectionRemoveTest('sessionType', 'SessionType');
    }

    /**
     * @covers \App\Entity\AssessmentOption::getSessionTypes
     */
    public function testGetSessionTypes()
    {
        $this->entityCollectionSetTest('sessionType', 'SessionType');
    }
}
