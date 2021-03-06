<?php

declare(strict_types=1);

namespace App\Classes;

use App\Service\Index\Curriculum;

class IndexableSession
{
    /** @var int */
    public $courseId;

    /** @var int */
    public $sessionId;

    /** @var string */
    public $title;

    /** @var string */
    public $sessionType;

    /** @var string */
    public $description;

    /** @var array  */
    public $directors = [];

    /** @var array  */
    public $administrators = [];

    /** @var array  */
    public $terms = [];

    /** @var array  */
    public $objectives = [];

    /** @var array  */
    public $meshDescriptorIds = [];

    /** @var array  */
    public $meshDescriptorNames = [];

    /** @var array  */
    public $meshDescriptorAnnotations = [];

    /** @var array  */
    public $learningMaterialTitles = [];

    /** @var array  */
    public $learningMaterialDescriptions = [];

    /** @var array  */
    public $learningMaterialCitations = [];

    /** @var array  */
    public $fileLearningMaterialIds = [];

    public function createIndexObject()
    {
        return [
            'id' => Curriculum::SESSION_ID_PREFIX . $this->sessionId,
            'sessionId' => $this->sessionId,
            'sessionTitle' => $this->title,
            'sessionType' => $this->sessionType,
            'sessionDescription' => $this->description,
            'sessionAdministrators' => implode(' ', $this->administrators),
            'sessionObjectives' => implode(' ', $this->objectives),
            'sessionTerms' => implode(' ', $this->terms),
            'sessionMeshDescriptorIds' => array_values($this->meshDescriptorIds),
            'sessionMeshDescriptorNames' => array_values($this->meshDescriptorNames),
            'sessionMeshDescriptorAnnotations' => implode(' ', $this->meshDescriptorAnnotations),
            'sessionLearningMaterialTitles' => array_values($this->learningMaterialTitles),
            'sessionLearningMaterialDescriptions' => array_values($this->learningMaterialDescriptions),
            'sessionLearningMaterialCitations' => array_values($this->learningMaterialCitations),
            'sessionLearningMaterialAttachments' => [],
            'sessionFileLearningMaterialIds' => array_values($this->fileLearningMaterialIds),
        ];
    }
}
