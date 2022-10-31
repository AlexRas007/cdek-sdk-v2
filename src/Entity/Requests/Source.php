<?php

/**
 * Copyright (c) Antistress.Store® 2021. All rights reserved.
 * See LICENSE.md for license details.
 *
 * @author Sergey Gusev
 */

namespace AntistressStore\CdekSDK2\Entity\Requests;

use JsonSerializable;

class Source implements JsonSerializable
{
	/**
	 * Формирует массив параметров для запроса.
	 * Удаляет пустые значения.
	 *
	 * @return array
	 */
	public function prepareRequest($object = null)
	{
		if ($object === null) {
			$object = $this;
		}

		$vars = get_object_vars($object);
		unset($vars['this']);

		$pattern = isset($object->pattern) ? array_keys($object->pattern) : [];

		$dynamic = [];
		foreach ($vars as $key => $var) {
			if (is_null($var) || (!empty($pattern) && !in_array($key, $pattern, true))) {
				continue;
			}

			$dynamic_val = self::prepareField($var);
			if ((!is_array($dynamic_val) && !is_null($dynamic_val)) || !empty($dynamic_val)) {
				$dynamic[$key] = $dynamic_val;
			}
		}

		return $dynamic;
	}

	/**
	 * Формирует массив параметров на основе одного поля.
	 * Удаляет пустые значения.
	 *
	 * @return mixed
	 */
	public static function prepareField($value) {
		if (is_object($value)) {
			return $value->prepareRequest();
		}

		if (is_array($value)) {
			$dynamic = [];
			foreach ($value as $key => $val) {
				$dynamic_val = self::prepareField($val);
				if ((!is_array($dynamic_val) && !is_null($dynamic_val)) || !empty($dynamic_val)) {
					$dynamic[$key] = $dynamic_val;
				}
			}

			return $dynamic;
		}

		return $value;
	}

	/**
	 * @return array
	 * @noinspection PhpLanguageLevelInspection
	 */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}
