<?php declare(strict_types=1);

use aktuba\JsonMapper\JsonMapper;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/JsonMapper/JsonMapper.php';

$jsonData = <<<JSON
{
	"users": {
		"user": [
			{
				"name": "John",
				"surname": "Smith",
				"age": 24
			},
			{
				"name": "Marry",
				"surname": "Cary",
				"age": 22
			}
		]
	},
	"meta": {
		"result": true,
		"version": "1.0",
		"took": "0.035"
	}
}
JSON;

class User extends JsonMapper
{

    protected const PROPERTIES = [
        'name' => 'string',
        'surname' => 'string',
        'age' => 'int',
    ];

}

class Meta extends JsonMapper
{

    protected const PROPERTIES = [
        'result' => 'bool',
        'version' => 'string',
        'took' => 'float',
    ];

}

class Data extends JsonMapper
{

    protected const PROPERTIES = [
        'users' => 'User[]',
        'meta' => 'Meta',
    ];

    protected function formatJson(array $jsonData): array
    {
        $jsonData['users'] = $jsonData['users']['user'] ?? [];
        return $jsonData;
    }

}

$data = new Data(json_decode($jsonData, true));
var_dump($data);
