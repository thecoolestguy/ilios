<?php

declare(strict_types=1);

namespace App\Traits;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Entity\LearnerGroupInterface;

/**
 * Interface LearnerGroupsEntityInterface
 */
interface LearnerGroupsEntityInterface
{
    /**
     * @param Collection $learnerGroups
     */
    public function setLearnerGroups(Collection $learnerGroups);

    /**
     * @param LearnerGroupInterface $learnerGroup
     */
    public function addLearnerGroup(LearnerGroupInterface $learnerGroup);

    /**
     * @param LearnerGroupInterface $learnerGroup
     */
    public function removeLearnerGroup(LearnerGroupInterface $learnerGroup);

    /**
    * @return LearnerGroupInterface[]|ArrayCollection
    */
    public function getLearnerGroups();
}
