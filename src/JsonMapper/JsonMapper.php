<?php declare(strict_types=1);

namespace aktuba\JsonMapper;

/**
 * Class JsonMapper
 * @package aktuba\JsonMapper
 */
abstract class JsonMapper
{

    /** @var array */
    protected const PROPERTIES = [];

    /** @var array */
    protected const ALIASES = [];

    /** @var array */
    protected const REQUIRED = [];

    /** @var string */
    private const STRING = 'string';

    /** @var string */
    private const INT = 'int';

    /** @var string */
    private const BOOL = 'bool';

    /** @var string */
    private const FLOAT = 'float';

    /** @var string */
    private const ARRAY = 'array';

    /** @var array */
    private const TYPE_CHECKERS = [
        self::STRING => 'is_string',
        self::INT => 'is_int',
        self::BOOL => 'is_bool',
        self::FLOAT => 'is_float',
        self::ARRAY => 'is_array',
    ];

    /** @var array */
    private $jsonData;

    /** @var string|null */
    private $collectionWrapper;

    /**
     * JsonMapper constructor.
     *
     * @param array $jsonData
     * @param string|null $collectionWrapper
     * @throws JsonMapperException
     */
    public function __construct(array $jsonData, ?string $collectionWrapper = null)
    {
        $this->collectionWrapper = $collectionWrapper;
        $jsonData = $this->formatJson($jsonData);
        $this->validateData($jsonData);
        $this->jsonData = $this->prepareData($jsonData);
    }

    /**
     * @param string $name
     * @return mixed
     * @throws JsonMapperException
     */
    public function __get(string $name)
    {
        if (!array_key_exists($name, $this->jsonData)) {
            throw new JsonMapperException("Property {$name} not found in" . static::class);
        }
        return $this->getProperty($name);
    }

    /**
     * @param string $name
     * @param $value
     * @throws JsonMapperException
     */
    public function __set(string $name, $value)
    {
        $this->setProperty($name, $value);
    }

    /**
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->jsonData);
    }

    /**
     * @param string $name
     */
    public function __unset(string $name)
    {
        unset($this->jsonData[$name]);
    }

    /**
     * @param string $name
     * @return mixed
     * @throws JsonMapperException
     */
    protected function getProperty(string $name)
    {
        $result = $this->jsonData[$name];

        if (null === $result && array_key_exists($name, static::PROPERTIES)) {
            [$type, $callback] = $this->getTypeAndCallback(static::PROPERTIES[$name]);
            $result = $this->getValueByType($type, $name, $result, $callback);
        }

        return $result;
    }

    /**
     * @param string $name
     * @param $value
     * @throws JsonMapperException
     */
    protected function setProperty(string $name, $value): void
    {
        if (array_key_exists($name, static::PROPERTIES)) {
            [$type, $callback] = $this->getTypeAndCallback(static::PROPERTIES[$name]);
            $value = $this->getValueByType($type, $name, $value, $callback);
        }
        $this->jsonData[$name] = $value;
    }

    /**
     * @param array $jsonData
     * @return array
     */
    protected function formatJson(array $jsonData): array
    {
        return $jsonData;
    }

    /**
     * @param array $jsonData
     * @throws JsonMapperException
     */
    protected function validateData(array $jsonData): void
    {
        $class = static::class;

        foreach (static::REQUIRED as $field) {
            $type = null;
            if (false !== mb_strpos($field, '|', 0)) {
                [$field, $type] = explode('|', $field, 2);
                if (!array_key_exists($type, static::TYPE_CHECKERS)) {
                    throw new JsonMapperException("Type {$type} not found in type checkers: {$class}");
                }
            }

            if (!array_key_exists($field, $jsonData)) {
                throw new JsonMapperException("Field {$field} not found in json: {$class}");
            }

            if (null !== $type && !call_user_func(static::TYPE_CHECKERS[$type], $jsonData[$field])) {
                throw new JsonMapperException("Field {$field} is not {$type} in json: {$class}");
            }
        }
    }

    /**
     * @param array $jsonData
     * @return array
     * @throws JsonMapperException
     */
    protected function prepareData(array $jsonData): array
    {
        $result = [];

        foreach (static::PROPERTIES as $property => $type) {
            $key = static::ALIASES[$property] ?? $property;
            $value = $jsonData[$key] ?? null;

            $result[$property] = null;
            if (null !== $value) {
                [$type, $callback] = $this->getTypeAndCallback($type);
                $result[$property] = $this->getValueByType($type, $property, $value, $callback);
            }
        }

        return $result;
    }

    /**
     * @param string $type
     * @return array
     */
    private function getTypeAndCallback(string $type): array
    {
        $callback = null;
        if ('[]' === mb_substr($type, mb_strlen($type) - 2, 2)) {
            $type = mb_substr($type, 0, -2);
            $callback = static function (array $data, string $class, ?string $collection = null) {
                $result = array_map(
                    static function ($item) use ($class, $collection) {
                        return new $class($item, $collection);
                    },
                    $data
                );

                if (null !== $collection) {
                    $result = new $collection($result);
                }

                return $result;
            };
        }
        return [$type, $callback];
    }

    /**
     * @param string $type
     * @param string $property
     * @param mixed $value
     * @param callable|null $callback
     * @return mixed
     * @throws JsonMapperException
     */
    private function getValueByType(string $type, string $property, $value, ?callable $callback = null)
    {
        $callbacks = [
            self::STRING => static function ($value) {
                return (string)$value;
            },
            self::INT => static function ($value) {
                return (int)$value;
            },
            self::FLOAT => static function ($value) {
                return (float)$value;
            },
            self::BOOL => static function ($value) {
                return (bool)$value;
            },
            self::ARRAY => static function ($value) {
                return (array)$value;
            },
        ];

        if (array_key_exists($type, $callbacks)) {
            if (null !== $callback) {
                $value = $callback(array_map($callbacks[$type], $value));
            } else {
                $value = $callbacks[$type]($value);
            }
        } else {
            $value = (array)$value;
            $class = $this->getFullClassName($type);

            if (null === $class) {
                throw new JsonMapperException("Class {$type} for field {$property} not found");
            }

            if (null !== $callback) {
                $value = $callback($value, $class, $this->collectionWrapper);
            } else {
                $value = new $class($value, $this->collectionWrapper);
            }
        }

        return $value;
    }

    /**
     * @param string $class
     * @return string|null
     */
    private function getFullClassName(string $class): ?string
    {
        if (0 !== mb_strpos($class, '\\', 0)) {
            $items = explode('\\', static::class);
            while (count($items) > 0) {
                $items = array_slice($items, 0, count($items) - 1);
                $fullName = '\\' . implode('\\', $items) . "\\{$class}";
                if (class_exists($fullName)) {
                    return $fullName;
                }
            }
        }

        return $class;
    }

}
