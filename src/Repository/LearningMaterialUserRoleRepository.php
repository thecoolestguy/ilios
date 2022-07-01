<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\DTOCacheManager;
use App\Traits\ImportableEntityRepository;
use App\Traits\ManagerRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\AbstractQuery;
use App\Entity\LearningMaterialUserRole;
use App\Entity\DTO\LearningMaterialUserRoleDTO;
use Doctrine\Persistence\ManagerRegistry;

class LearningMaterialUserRoleRepository extends ServiceEntityRepository implements
    DTORepositoryInterface,
    RepositoryInterface,
    DataImportRepositoryInterface
{
    use ManagerRepository;
    use ImportableEntityRepository;

    public function __construct(
        ManagerRegistry $registry,
        protected DTOCacheManager $cacheManager,
    ) {
        parent::__construct($registry, LearningMaterialUserRole::class);
    }

    public function hydrateDTOsFromIds(array $ids): array
    {
        $qb = $this->_em->createQueryBuilder()->select('x')
            ->distinct()->from(LearningMaterialUserRole::class, 'x');
        $qb->where($qb->expr()->in('x.id', ':ids'));
        $qb->setParameter(':ids', $ids);

        /** @var LearningMaterialUserRoleDTO[] $learningMaterialUserRoleDTOs */
        $learningMaterialUserRoleDTOs = [];
        foreach ($qb->getQuery()->getResult(AbstractQuery::HYDRATE_ARRAY) as $arr) {
            $learningMaterialUserRoleDTOs[$arr['id']] = new LearningMaterialUserRoleDTO(
                $arr['id'],
                $arr['title']
            );
        }

        return array_values($learningMaterialUserRoleDTOs);
    }


    protected function attachCriteriaToQueryBuilder(
        QueryBuilder $qb,
        array $criteria,
        ?array $orderBy,
        ?int $limit,
        ?int $offset
    ): void {
        $this->attachClosingCriteriaToQueryBuilder($qb, $criteria, $orderBy, $limit, $offset);
    }

    public function import(array $data, string $type, array $referenceMap): array
    {
        // `learning_material_user_role_id`,`title`
        $entity = new LearningMaterialUserRole();
        $entity->setId($data[0]);
        $entity->setTitle($data[1]);
        $this->importEntity($entity);
        $referenceMap[$type . $entity->getId()] = $entity;
        return $referenceMap;
    }
}
