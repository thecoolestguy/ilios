<?php

declare(strict_types=1);

namespace App\Tests\DataLoader;

use App\Service\EntityRepositoryLookup;
use App\Service\EntityMetadata;
use DateTime;
use Exception;
use Faker\Factory as FakerFactory;
use ReflectionClass;

/**
 * Abstract utilities for loading data
 */
abstract class AbstractDataLoader implements DataLoaderInterface
{
    protected $data;

    protected $faker;

    /**
     * @var EntityMetadata
     */
    protected $entityMetadata;

    /**
     * @var EntityRepositoryLookup
     */
    protected $entityManagerLookup;

    public function __construct(EntityMetadata $entityMetadata, EntityRepositoryLookup $entityManagerLookup)
    {
        $this->faker = FakerFactory::create();
        $this->faker->seed(1234);
        $this->entityMetadata = $entityMetadata;
        $this->entityManagerLookup = $entityManagerLookup;
    }

    /**
     * Create test data
     * @return array
     */
    abstract protected function getData();

    protected function setup()
    {
        if (!empty($this->data)) {
            return;
        }

        $this->data = $this->getData();
    }

    public function getOne()
    {
        $this->setup();
        return array_values($this->data)[0];
    }

    public function getAll()
    {
        $this->setup();
        return $this->data;
    }

    /**
     * Get a formatted data from a string
     * @param string $when
     * @return string
     */
    public function getFormattedDate($when)
    {
        $dt = new DateTime($when);
        return $dt->format('c');
    }

    abstract public function create();

    abstract public function createInvalid();

    public function createJsonApi(array $arr): object
    {
        $item = $this->buildJsonApiObject($arr, $this->getDtoClass());
        return json_decode(json_encode(['data' => $item]), false);
    }

    /**
     * @inheritdoc
     */
    public function createMany($count)
    {
        $data = [];
        for ($i = 0; $i < $count; $i++) {
            $arr = $this->create();
            $arr['id'] = $arr['id'] + $i;
            $data[] = $arr;
        }

        return $data;
    }

    public function createBulkJsonApi(array $arr): object
    {
        $class = $this->getDtoClass();
        $builder = \Closure::fromCallable([$this, 'buildJsonApiObject']);
        $data = array_map(function (array $item) use ($builder, $class) {
            return $builder($item, $class);
        }, $arr);

        return json_decode(json_encode(['data' => $data]), false);
    }

    /**
     * Build a single JSON API version using the annotations on the DTO
     */
    protected function buildJsonApiObject(array $arr, string $dtoClass): array
    {
        $reflection = new ReflectionClass($dtoClass);
        $exposedProperties = $this->entityMetadata->extractExposedProperties($reflection);
        $attributes = [];
        foreach ($exposedProperties as $property) {
            if (array_key_exists($property->name, $arr)) {
                $attributes[$property->name] = $arr[$property->name];
            }
        }

        $relatedProperties = $this->entityMetadata->extractRelated($reflection);
        $type = $this->entityMetadata->extractType($reflection);
        $idProperty = $this->entityMetadata->extractId($reflection);
        $id = $attributes[$idProperty];

        $relationships = [];
        foreach ($relatedProperties as $attributeName => $relationshipType) {
            if (array_key_exists($attributeName, $attributes)) {
                $value = $attributes[$attributeName];
                if (is_array($value)) {
                    $relationships[$attributeName]['data'] = [];
                    foreach ($value as $relId) {
                        $relationships[$attributeName]['data'][] = [
                            'type' => $relationshipType,
                            'id' => $relId,
                        ];
                    }
                } elseif (is_null($value)) {
                    $relationships[$attributeName]['data'] = null;
                } else {
                    $relationships[$attributeName]['data'] = [
                        'type' => $relationshipType,
                        'id' => $value,
                    ];
                }
            }

            unset($attributes[$attributeName]);
        }
        unset($attributes[$idProperty]);

        return [
            'id' => $id,
            'type' => $type,
            'attributes' => $attributes,
            'relationships' => $relationships
        ];
    }
}
