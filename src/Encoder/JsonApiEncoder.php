<?php

declare(strict_types=1);

namespace App\Encoder;

use App\Service\JsonApiDataShaper;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;

class JsonApiEncoder implements EncoderInterface, DecoderInterface
{
    use ContainerAwareTrait;

    protected const FORMAT = 'json-api';

    /**
     * @var JsonApiDataShaper
     */
    protected $dataShaper;

    public function __construct(JsonApiDataShaper $dataShaper)
    {
        $this->dataShaper = $dataShaper;
    }

    public function decode(string $data, string $format, array $context = [])
    {
        $obj = json_decode($data);
        $rhett = [];
        if (is_array($obj->data)) {
            foreach ($obj->data as $o) {
                $rhett[] = $this->dataShaper->flattenJsonApiData($o);
            }
        } else {
            $rhett[] = $this->dataShaper->flattenJsonApiData($obj->data);
        }

        return $rhett;
    }

    public function supportsDecoding(string $format)
    {
        return self::FORMAT === $format;
    }

    public function encode($data, string $format, array $context = [])
    {
        $shaped = $this->dataShaper->shapeData($data, $context['sideLoadFields']);

        if (array_key_exists('singleItem', $context) && $context['singleItem']) {
            $data = $shaped['data'];
            $item = $data[0];
            $shaped['data'] = $item;
        }

        return json_encode($shaped);
    }

    public function supportsEncoding(string $format)
    {
        return self::FORMAT === $format;
    }
}
