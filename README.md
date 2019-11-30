## Контейнер для json-а на основе схемы

Библиотека для преобразования массива данных в дерево объектов.
Создано для облегчения реализаций json-api.

### Возможности:
- ООП-интерфейс для данных
- Автоматическая обработка вложенных структур с поддержкой объектов

### Требовния для использования

- PHP 7.1 и выше

### Установка

```bash
$ composer require aktuba/json-mapper
```

### Пример использования:

```php
<?php declare(strict_types=1);

use aktuba\JsonMapper\JsonMapper;

require __DIR__.'/../vendor/autoload.php';

$jsonData = <<<JSON
{
	"users": [
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
	],
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

}

$data = new Data(json_decode($jsonData, true));
var_dump($data);
```

Больше примеров в `examples`
