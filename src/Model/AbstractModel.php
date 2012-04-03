<?php

namespace HumusBase\Model;

use Zend\Stdlib\ArrayUtils,
    Doctrine\Common\Collections\Collection,
    DateTime;

abstract class AbstractModel
{
    /**
     * Convert a model class to an array recursively
     *
     * @todo comment how to use this method
     * @todo add quickexample in phpdoc
     *
     * @param array $fields
     * @param bool $flastSingleKeys
     * @param array|bool $array (parameter only used during recursion)
     * @return array
     */
    public function toArray(array $fields = array(), $flatSingleKeys = false, $array = false)
    {
        $flatSingleKeys = (bool) $flatSingleKeys;
        $array = $array ?: $fields;
        foreach ($array as $key => $value) {
            foreach ($fields as $fieldName => $fieldValue) {
                if ($fieldName !== $key && $fieldName !== $value) {
                    continue;
                }
                if (!is_array($fieldValue)) {
                    unset($array[$fieldName]);
                    $fieldName = $fieldValue;
                    $fieldValue = array();
                } else {
                    unset($array[$fieldName]);
                }
                $key = static::fromCamelCase($fieldName);
                $getter = static::fieldToGetterMethod($key);
                if (is_callable(array($this, $getter))) {
                    $value = $this->$getter();
                } else if (property_exists(get_called_class(), $key)) {
                    $value = $this->{$key};
                } else {
                    continue;
                }
                if (is_object($value)) {
                    if ($value instanceof Collection) {
                        foreach($value as $collectionValue) {
                            if (is_callable(array($collectionValue, 'toArray'))) {
                                $array[$key][] = $collectionValue->toArray($fieldValue, $flatSingleKeys);
                            }
                        }
                    } else if (is_callable(array($value, 'toArray'))) {
                        $array[$key] = $value->toArray($fieldValue, $flatSingleKeys);
                    } else if ($value instanceof DateTime) {
                        $array[$key] = $value->format('Y-m-d H:i:s');
                    } else {
                        $array[$key] = $value;
                    }
                } else if (is_array($value) && count($value) > 0) {
                    $array[$key] = $this->toArray($fieldValue, $flatSingleKeys, $value);
                } else if ($value !== NULL && !is_array($value)) {
                    $array[$key] = $value;
                }
            }
        }
        if ($flatSingleKeys) {
            foreach($fields as $field => $value) {
                if (!(isset($array[$field]) && is_array($value))) {
                    continue;
                }
                $array[$field] = $this->flatSingleKeys($array[$field]);
            }
        }
        return $array;
    }

    /**
     * @param $data
     * @return array
     */
    protected function flatSingleKeys($data)
    {
        $result = array();
        if ($data === NULL) {
            return $data;
        }
        if (ArrayUtils::isHashTable($data)) {
            if (1 === count($data)) {
                $result = array_shift($data);
            } else {
                foreach ($data as $key => $value) {
                    if (is_array($value)) {
                        $result[$key] = $this->flatSingleKeys($value);
                    } else {
                        $result[$key] = $value;
                    }
                }
            }
        } else {
            foreach ($data as $value) {
                if (is_array($value) && 1 === count($value)) {
                    $result[] = array_shift($value);
                } elseif (is_scalar($value)) {
                    $result[] = $value;
                }
            }
        }
        return $result;
    }

    /**
     * Convert field to getter method
     *
     * @param $name
     * @return string
     */
    public static function fieldToGetterMethod($name)
    {
        return 'get' . static::toCamelCase($name);
    }

    /**
     * Convert to camel case
     *
     * @param $name
     * @return string
     */
    public static function toCamelCase($name)
    {
        return implode('',array_map('ucfirst', explode('_',$name)));
    }

    /**
     * Convert from camel case
     *
     * @param $name
     * @return string
     */
    public static function fromCamelCase($name)
    {
        return trim(preg_replace_callback('/([A-Z])/', function($c){ return '_'.strtolower($c[1]); }, $name),'_');
    }
}
