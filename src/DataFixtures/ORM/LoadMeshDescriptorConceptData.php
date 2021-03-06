<?php

declare(strict_types=1);

namespace App\DataFixtures\ORM;

use App\Repository\MeshDescriptorRepository;
use App\Service\DataimportFileLocator;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

/**
 * Class LoadMeshDescriptorConceptData
 */
class LoadMeshDescriptorConceptData extends AbstractMeshFixture implements DependentFixtureInterface
{
    public function __construct(
        MeshDescriptorRepository $meshDescriptorRepository,
        DataimportFileLocator $dataimportFileLocator
    ) {
        parent::__construct(
            $meshDescriptorRepository,
            $dataimportFileLocator,
            'mesh_descriptor_x_concept.csv',
            'MeshDescriptorConcept'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getDependencies()
    {
        return [
          'App\DataFixtures\ORM\LoadMeshDescriptorData',
          'App\DataFixtures\ORM\LoadMeshConceptData',
        ];
    }
}
