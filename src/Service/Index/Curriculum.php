<?php

declare(strict_types=1);

namespace App\Service\Index;

use App\Classes\OpenSearchBase;
use App\Classes\IndexableCourse;
use App\Repository\CourseRepository;
use App\Service\Config;
use DateTime;
use Exception;
use OpenSearch\Client;
use stdClass;

class Curriculum extends OpenSearchBase
{
    public const string INDEX = 'ilios-curriculum';
    public const string SESSION_ID_PREFIX = 'session_';

    public function __construct(
        private readonly CourseRepository $courseRepository,
        Config $config,
        ?Client $client = null
    ) {
        parent::__construct($config, $client);
    }

    public function search(string $query, bool $onlySuggest): array
    {
        if (!$this->enabled) {
            throw new Exception("Search is not configured, isEnabled() should be called before calling this method");
        }

        $suggestFields = [
            'courseTitle',
            'courseTerms',
            'courseMeshDescriptorIds',
            'courseMeshDescriptorNames',
            'courseLearningMaterialTitles',
            'sessionTitle',
            'sessionType',
            'sessionTerms',
            'sessionMeshDescriptorIds',
            'sessionMeshDescriptorNames',
            'sessionLearningMaterialTitles',
        ];
        $suggest = array_reduce($suggestFields, function ($carry, $field) use ($query) {
            $carry[$field] = [
                'prefix' => $query,
                'completion' => [
                    'field' => "{$field}.cmp",
                    'skip_duplicates' => true,
                ],
            ];

            return $carry;
        }, []);

        $params = [
            'index' => self::INDEX,
            'body' => [
                'suggest' => $suggest,
            ],
        ];

        if (!$onlySuggest) {
            $params['body']['_source'] = [
                'courseId',
                'courseTitle',
                'courseYear',
                'sessionId',
                'sessionTitle',
                'school',
            ];
            $params['body']['collapse'] = [
                'field' => 'courseId',
                'inner_hits' => [
                    'name' => 'sessions',
                    'size' => 5,
                    'sort' => ['_score'],
                ],
            ];
            $params['body']['sort'] = '_score';
            $params['body']['size'] = 25;

            //the closer a course is to this year the higher it will be ranked
            $params['body']['query']['function_score'] = [
                'query' => $this->buildCurriculumSearch($query),
                'functions' => [
                    [
                        'gauss' => [
                            'courseYear.year' => [
                                'origin' => (new DateTime())->format("Y"),
                                'scale' => 2, //starts taking points off when two years before or after today
                                'decay' => 0.8, //multiplies score by this, 80% for every 'scale' away from today
                            ],
                        ],
                    ],
                ],
                'score_mode' => 'multiply',
            ];
        }

        $results = $this->doSearch($params);

        return $this->parseCurriculumSearchResults($results);
    }

    public function index(array $courseIds, DateTime $requestCreatedAt): bool
    {
        if (!$this->enabled) {
            throw new Exception("Search is not configured, isEnabled() should be called before calling this method");
        }
        array_walk($courseIds, 'intval');

        $skipCourseIds = $this->findSkippableCourseIds($courseIds, $requestCreatedAt);
        $idsToIndex = array_filter(
            $courseIds,
            fn(int $courseId) => !in_array($courseId, $skipCourseIds)
        );

        $coursesToIndex = $this->courseRepository->getCourseIndexesFor(array_values($idsToIndex));

        $input = array_reduce($coursesToIndex, function (array $carry, IndexableCourse $item) {
            $sessions = $item->createIndexObjects();
            $sessionsWithMaterials = $this->attachLearningMaterialsToSession($sessions);
            return array_merge($carry, $sessionsWithMaterials);
        }, []);

        return $this->doBulkIndex(self::INDEX, $input);
    }

    protected function findSkippableCourseIds(array $ids, DateTime $stamp): array
    {
        if (!$this->enabled) {
            return [];
        }
        $params = [
            'index' => self::INDEX,
            'body' => [
                'query' => [
                    'bool' => [
                        'filter' => [
                            [
                                'range' => [
                                    'ingestTime' => [
                                        'gte' => $stamp->format('c'),
                                    ],
                                ],
                            ],
                            [
                                'terms' => [
                                    'courseId' => $ids,
                                ],
                            ],
                        ],
                    ],
                ],
                'aggs' => [
                    'courseId' => [
                        'terms' => [
                            'field' => 'courseId',
                            'size' => 10000,
                        ],
                    ],
                ],
                'size' => 0,
            ],
        ];
        $results = $this->doSearch($params);
        $courseIds =  array_column($results['aggregations']['courseId']['buckets'], 'key');
        $coursesModifiedSinceStamp = array_map('intval', $courseIds);

        return array_intersect($ids, $coursesModifiedSinceStamp);
    }

    public function deleteCourse(int $id): bool
    {
        $result = $this->doDeleteByQuery([
            'index' => self::INDEX,
            'body' => [
                'query' => [
                    'term' => ['courseId' => $id],
                ],
            ],
        ]);

        return !count($result['failures']);
    }

    public function deleteSession(int $id): bool
    {
        $result = $this->doDelete([
            'index' => self::INDEX,
            'id' => self::SESSION_ID_PREFIX . $id,
        ]);

        return $result['result'] === 'deleted';
    }

    /**
     * Attach learning materials to a list of sessions
     */
    protected function attachLearningMaterialsToSession(array $sessions): array
    {
        $courseIds = array_column($sessions, 'courseFileLearningMaterialIds');
        $sessionIds = array_column($sessions, 'sessionFileLearningMaterialIds');
        $learningMaterialIds = array_values(array_unique(array_merge([], ...$courseIds, ...$sessionIds)));
        $materialsById = $this->getMaterialContentsByIds($learningMaterialIds);

        return array_map(function (array $session) use ($materialsById) {
            foreach ($session['sessionFileLearningMaterialIds'] as $id) {
                if (array_key_exists($id, $materialsById)) {
                    foreach ($materialsById[$id] as $value) {
                        $session['sessionLearningMaterialAttachments'][] = $value;
                    }
                }
            }
            unset($session['sessionFileLearningMaterialIds']);
            foreach ($session['courseFileLearningMaterialIds'] as $id) {
                if (array_key_exists($id, $materialsById)) {
                    foreach ($materialsById[$id] as $value) {
                        $session['courseLearningMaterialAttachments'][] = $value;
                    }
                }
            }
            unset($session['courseFileLearningMaterialIds']);

            return $session;
        }, $sessions);
    }

    /**
     * Fetch all the materials contents by ID
     */
    protected function getMaterialContentsByIds(array $learningMaterialIds): array
    {
        sort($learningMaterialIds);
        $materialsById = [];
        if (!empty($learningMaterialIds)) {
            $query = [
                'index' => LearningMaterials::INDEX,
                'body' => [
                    'query' => [
                        'terms' => [
                            'learningMaterialId' => $learningMaterialIds,
                        ],
                    ],
                ],
            ];
            ["count" => $count ] = $this->doCount($query);
            $query['body']['_source'] = [
                'id',
                'learningMaterialId',
                'contents',
            ];
            $query['body']['sort'] = ['learningMaterialId'];
            $query['body']['size'] = 25;
            while ($count > 0) {
                $results = $this->doSearch($query);
                foreach ($results['hits']['hits'] as ["_source" => $s, 'sort' => $sort]) {
                    $id = $s['learningMaterialId'];
                    $materialsById[$id][] = $s['contents'];
                    $query['body']['search_after'] = $sort;
                    $count--;
                }
            }
        }

        return $materialsById;
    }

    /**
     * Construct the query to search the curriculum
     */
    protected function buildCurriculumSearch(string $query): array
    {
        $mustFields = [
            'courseTitle',
            'courseTerms',
            'courseObjectives',
            'courseLearningMaterialTitles',
            'courseLearningMaterialDescriptions',
            'courseLearningMaterialCitation',
            'courseMeshDescriptorNames',
            'courseMeshDescriptorAnnotations',
            'courseLearningMaterialAttachments',
            'sessionTitle',
            'sessionDescription',
            'sessionTerms',
            'sessionObjectives',
            'sessionLearningMaterialTitles',
            'sessionLearningMaterialDescriptions',
            'sessionLearningMaterialCitation',
            'sessionMeshDescriptorNames',
            'sessionMeshDescriptorAnnotations',
            'sessionLearningMaterialAttachments',
        ];
        $keywordFields = [
            'courseYear',
            'courseMeshDescriptorIds',
            'sessionType',
            'sessionMeshDescriptorIds',
        ];

        $shouldFields = [
            'courseTitle',
            'courseTerms',
            'courseObjectives',
            'courseLearningMaterialTitles',
            'courseLearningMaterialDescriptions',
            'courseLearningMaterialAttachments',
            'sessionTitle',
            'sessionDescription',
            'sessionType',
            'sessionTerms',
            'sessionObjectives',
            'sessionLearningMaterialTitles',
            'sessionLearningMaterialDescriptions',
            'sessionLearningMaterialAttachments',
        ];

        $mustMatch = [];

        /**
         * Keyword index types cannot user the match_phrase_prefix query
         * So they have to be added using the match query
         */
        foreach ($keywordFields as $field) {
            $mustMatch[] = [ 'match' => [ $field => [
                'query' => $query,
                '_name' => $field,
            ] ] ];
        }

        $mustMatch = array_reduce(
            $mustFields,
            function (array $carry, string $field) use ($query) {
                $matches = array_map(function (string $type) use ($field, $query) {
                    $fullField = "{$field}.{$type}";
                    return [ 'match_phrase_prefix' => [ $fullField => ['query' => $query, '_name' => $fullField] ] ];
                }, ['english', 'french', 'spanish']);

                return array_merge($carry, $matches);
            },
            $mustMatch
        );


        /**
         * At least one of the mustMatch queries has to be a match
         * but we wrap it in a should block so they don't all have to match
         */
        $must = ['bool' => [
            'should' => $mustMatch,
        ]];

        /**
         * The should queries are designed to boost the total score of
         * results that match more closely than the MUST set above so when
         * users enter a complete word like move it will score higher than
         * than a partial match on movement
         */
        $should = array_map(function ($field) use ($query) {
            return [ 'match' => [ "{$field}" => [
                'query' => $query,
                '_name' => $field,
            ] ] ];
        }, $shouldFields);

        return [
            'bool' => [
                'must' => $must,
                'should' => $should,
            ],
        ];
    }

    protected function parseCurriculumSearchResults(array $results): array
    {
        $autocompleteSuggestions = array_reduce(
            $results['suggest'],
            function (array $carry, array $item) {
                $options = array_map(fn(array $arr) => $arr['text'], $item[0]['options']);

                return array_unique(array_merge($carry, $options));
            },
            []
        );

        $allHits = array_reduce($results['hits']['hits'], function (array $carry, array $item): array {
            $innerHits = $item['inner_hits']['sessions']['hits']['hits'];
            unset($item['inner_hits']);

            $carry[] = $item;
            return array_merge($carry, $innerHits);
        }, []);

        $mappedResults = array_map(function (array $arr) {
            $courseMatches = array_filter(
                $arr['matched_queries'],
                fn(string $match) => str_starts_with($match, 'course')
            );
            $sessionMatches = array_filter(
                $arr['matched_queries'],
                fn(string $match) => str_starts_with($match, 'session')
            );
            $rhett = $arr['_source'];
            $rhett['score'] = $arr['_score'];
            $rhett['courseMatches'] = $courseMatches;
            $rhett['sessionMatches'] = $sessionMatches;

            return $rhett;
        }, $allHits);

        $courses = array_reduce($mappedResults, function (array $carry, array $item) {
            $id = $item['courseId'];
            if (!array_key_exists($id, $carry)) {
                $carry[$id] = [
                    'id' => $id,
                    'title' => $item['courseTitle'],
                    'year' => $item['courseYear'],
                    'school' => $item['school'],
                    'bestScore' => 0,
                    'sessions' => [],
                    'matchedIn' => [],
                ];
            }
            $courseMatches = array_map(function (string $match) {
                $split = explode('.', $match);
                $field = strtolower(substr($split[0], strlen('course')));
                if (str_contains($field, 'meshdescriptor')) {
                    $field = 'meshdescriptors';
                }
                if (str_contains($field, 'learningmaterial')) {
                    $field = 'learningmaterials';
                }

                return $field;
            }, $item['courseMatches']);
            $sessionMatches = array_map(function (string $match) {
                $split = explode('.', $match);
                $field = strtolower(substr($split[0], strlen('session')));
                if (str_contains($field, 'meshdescriptor')) {
                    $field = 'meshdescriptors';
                }
                if (str_contains($field, 'learningmaterial')) {
                    $field = 'learningmaterials';
                }

                return $field;
            }, $item['sessionMatches']);
            $carry[$id]['matchedIn'] = array_values(array_unique(
                array_merge($courseMatches, $carry[$id]['matchedIn'])
            ));
            if ($item['score'] > $carry[$id]['bestScore']) {
                $carry[$id]['bestScore'] = $item['score'];
            }
            $carry[$id]['sessions'][] = [
                'id' => $item['sessionId'],
                'title' => $item['sessionTitle'],
                'score' => $item['score'],
                'matchedIn' => array_values(array_unique($sessionMatches)),
            ];

            return $carry;
        }, []);

        usort($courses, fn($a, $b) => $b['bestScore'] <=> $a['bestScore']);

        return [
            'autocomplete' => $autocompleteSuggestions,
            'courses' => $courses,
        ];
    }

    public function getAllCourseIds(): array
    {
        $params = [
            'index' => self::INDEX,
            'body' => [
                'query' => [
                    'match_all' => new stdClass(),
                ],
                'aggs' => [
                    'courseId' => [
                        'terms' => [
                            'field' => 'courseId',
                            'size' => 10000,
                        ],
                    ],
                ],
                'size' => 0,
            ],
        ];
        $results = $this->doSearch($params);
        $courseIds = array_column($results['aggregations']['courseId']['buckets'], 'key');

        return array_map('intval', $courseIds);
    }

    public function getAllSessionIds(): array
    {
        $query = [
            'index' => self::INDEX,
            'body' => [
                'aggs' => [
                    'sessionId' => [
                        'terms' => [
                            'field' => 'sessionId',
                            'size' => self::SIZE_LIMIT,
                        ],
                    ],
                ],
                'size' => 0,
            ],
        ];
        $gte = 0;
        $lt = self::SIZE_LIMIT;
        $sessionIds = [];
        do {
            $query['body']['query']['bool']['filter'][0]['range']['sessionId']['gte'] = $gte;
            $query['body']['query']['bool']['filter'][0]['range']['sessionId']['lt'] = $lt;
            $results = $this->doSearch($query);
            foreach ($results['aggregations']['sessionId']['buckets'] as ['key' => $key]) {
                $sessionIds[] = (int) $key;
            }
            $gte = $lt;
            $lt += self::SIZE_LIMIT;
        } while ($results['hits']['total']['value']);

        return $sessionIds;
    }

    public static function getMapping(): array
    {
        $txtTypeField = [
            'type' => 'text',
            'analyzer' => 'standard',
            'fields' => [
                'english' => [
                    'type' => 'text',
                    'analyzer' => 'english',
                ],
                'french' => [
                    'type' => 'text',
                    'analyzer' => 'french',
                ],
                'spanish' => [
                    'type' => 'text',
                    'analyzer' => 'spanish',
                ],
            ],
        ];
        $txtTypeFieldWithCompletion = $txtTypeField;
        $txtTypeFieldWithCompletion['fields']['cmp'] = ['type' => 'completion'];

        return [
            'settings' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 1,
                'default_pipeline' => 'curriculum',
            ],
            'mappings' => [
                '_meta' => [
                    'version' => '1',
                ],
                'properties' => [
                    'courseId' => [
                        'type' => 'integer',
                    ],
                    'school' => [
                        'type' => 'keyword',
                        'fields' => [
                            'cmp' => [
                                'type' => 'completion',
                            ],
                        ],
                    ],
                    'courseYear' => [
                        'type' => 'keyword',
                        'fields' => [
                            'year' => [
                                'type' => 'integer',
                            ],
                        ],
                    ],
                    'courseTitle' => $txtTypeFieldWithCompletion,
                    'courseTerms' => $txtTypeFieldWithCompletion,
                    'courseObjectives'  => $txtTypeField,
                    'courseLearningMaterialTitles'  => $txtTypeFieldWithCompletion,
                    'courseLearningMaterialDescriptions'  => $txtTypeField,
                    'courseLearningMaterialCitation'  => $txtTypeField,
                    'courseLearningMaterialAttachments'  => $txtTypeField,
                    'courseMeshDescriptorIds' => [
                        'type' => 'keyword',
                        'fields' => [
                            'cmp' => [
                                'type' => 'completion',
                                // we have to override the analyzer here because the default strips
                                // out numbers and mesh ids are mostly numbers
                                'analyzer' => 'standard',
                            ],
                        ],
                    ],
                    'courseMeshDescriptorNames' => $txtTypeFieldWithCompletion,
                    'courseMeshDescriptorAnnotations' => $txtTypeField,
                    'sessionId' => [
                        'type' => 'integer',
                    ],
                    'sessionTitle' => $txtTypeFieldWithCompletion,
                    'sessionDescription' => $txtTypeField,
                    'sessionType' => [
                        'type' => 'keyword',
                        'fields' => [
                            'cmp' => [
                                'type' => 'completion',
                            ],
                        ],
                    ],
                    'sessionTerms' => $txtTypeFieldWithCompletion,
                    'sessionObjectives'  => $txtTypeField,
                    'sessionLearningMaterialTitles'  => $txtTypeFieldWithCompletion,
                    'sessionLearningMaterialDescriptions'  => $txtTypeField,
                    'sessionLearningMaterialCitation'  => $txtTypeField,
                    'sessionLearningMaterialAttachments'  => $txtTypeField,
                    'sessionMeshDescriptorIds' => [
                        'type' => 'keyword',
                        'fields' => [
                            'cmp' => [
                                'type' => 'completion',
                                // we have to override the analyzer here because the default strips
                                // out numbers and mesh ids are mostly numbers
                                'analyzer' => 'standard',
                            ],
                        ],
                    ],
                    'sessionMeshDescriptorNames' => $txtTypeFieldWithCompletion,
                    'sessionMeshDescriptorAnnotations' => $txtTypeField,
                    'ingestTime' => [
                        'type' => 'date',
                        'format' => 'date_optional_time||basic_date_time_no_millis||epoch_second||epoch_millis',
                    ],
                ],
            ],
        ];
    }

    public static function getPipeline(): array
    {
        return [
            'id' => 'curriculum',
            'body' => [
                'description' => 'Curriculum Data',
                'processors' => [
                    [
                        'set' => [
                            'field' => '_source.ingestTime',
                            'value' => '{{_ingest.timestamp}}',
                        ],
                    ],
                ],
            ],
        ];
    }
}
