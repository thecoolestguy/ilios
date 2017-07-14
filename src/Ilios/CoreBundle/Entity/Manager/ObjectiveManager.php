<?php

namespace Ilios\CoreBundle\Entity\Manager;

/**
 * Class ObjectiveManager
 * @package Ilios\CoreBundle\Entity\Manager
 */

class ObjectiveManager extends DTOManager
{
    /**
     * @return int
     */
    public function getTotalObjectiveCount()
    {
        return $this->em->createQuery('SELECT COUNT(o.id) FROM IliosCoreBundle:Objective o')->getSingleScalarResult();
    }
}
