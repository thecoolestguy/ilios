<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\MeshPreviousIndexing;
use Mockery as m;

/**
 * Tests for Entity MeshPreviousIndexing
 * @group model
 */
class MeshPreviousIndexingTest extends EntityBase
{
    /**
     * @var MeshPreviousIndexing
     */
    protected $object;

    /**
     * Instantiate a MeshPreviousIndexing object
     */
    protected function setUp(): void
    {
        $this->object = new MeshPreviousIndexing();
    }

    public function testNotBlankValidation()
    {
        $notBlank = [
            'previousIndexing'
        ];
        $this->validateNotBlanks($notBlank);

        $this->object->setPreviousIndexing('a big load of 
                stuff in here up to 65 K characters 
                too much for me to imagine');
        $this->validate(0);
    }
    /**
     * @covers \App\Entity\MeshPreviousIndexing::setPreviousIndexing
     * @covers \App\Entity\MeshPreviousIndexing::getPreviousIndexing
     */
    public function testSetPreviousIndexing()
    {
        $this->basicSetTest('previousIndexing', 'string');
    }

    /**
     * @covers \App\Entity\MeshPreviousIndexing::getDescriptor
     * @covers \App\Entity\MeshPreviousIndexing::setDescriptor
     */
    public function testSetDescriptor()
    {
        $this->entitySetTest('descriptor', "MeshDescriptor");
    }
}
