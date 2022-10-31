<?php

/**
 * Copyright (c) Antistress.Store® 2021. All rights reserved.
 * See LICENSE.md for license details.
 *
 * @author Sergey Gusev
 */

namespace AntistressStore\CdekSDK2\Entity\Responses;

use AntistressStore\CdekSDK2\Constants;

class Source
{
	/**
	 * Формирует объект класса из ответа.
	 *
	 * @param array|null $properties
	 * @param bool $processedEntity В каком формате ожидать 'entity', true если 'entity' не требует обработки
	 */
    public function __construct($properties = null, $processedEntity = false)
    {
        if ($properties !== null) {
            if (!$processedEntity && isset($properties['entity']) && count($properties['entity']) > 1) {
				$properties = $properties['entity'];
			}

            foreach ($properties as $key => $value) {
                if (!property_exists($this, $key)) {
                    continue;
                }

                if (array_key_exists($key, Constants::SDK_CLASSES)) {
                    $class_name = '\\AntistressStore\\CdekSDK2\\Entity\\Responses\\' .
						Constants::SDK_CLASSES[$key] . 'Response';
					/** @var Source $class_name */
                    $this->{$key} = $class_name::create($value);
                } elseif (array_key_exists($key, Constants::SDK_ARRAY_RESPONSE_CLASSES)) {
                    foreach ($value as $v) {
                        $class_name = '\\AntistressStore\\CdekSDK2\\Entity\\Responses\\' .
							Constants::SDK_ARRAY_RESPONSE_CLASSES[$key] . 'Response';
						/** @var Source $class_name */
                        $this->{$key}[] = $class_name::create($v);
                    }
                } else {
                    $this->{$key} = $value;
                }
            }
        }
    }

    public static function create(array $properties)
    {
        return new static($properties);
    }
}
