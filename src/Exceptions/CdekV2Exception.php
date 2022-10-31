<?php

/**
 * Copyright (c) Antistress.Store® 2021. All rights reserved.
 * See LICENSE.md for license details.
 *
 * @author Sergey Gusev
 */

namespace AntistressStore\CdekSDK2\Exceptions;

use AntistressStore\CdekSDK2\Constants;

class CdekV2Exception extends \Exception
{
	/**
	 * @var string
	 */
	protected $codeMessage;

	/**
	 * Сконструировать исключение. Примечание. Сообщение НЕ является двоично-безопасным.
	 *
	 * @param string $codeMessage [optional] Код сообщения об исключении, которое нужно выбросить
	 * @param string $message [optional] Сообщение об исключении, которое нужно выбросить
	 * @param int $code [optional] Код исключения
	 */
	public function __construct($codeMessage = "", $message = "", $code = 0) {
		parent::__construct($message, $code);

		$this->codeMessage = $codeMessage;
	}

	/**
	 * Возвращает код сообщения об исключении, которое нужно выбросить.
	 *
	 * @return string Код сообщения об исключении в виде строки
	 */
	final public function getCodeMessage() {
		return $this->codeMessage;
	}

	/**
	 * @param string $code
	 * @param string $message
	 * @return string
	 */
    public static function getTranslation($code, $message)
    {
        if (array_key_exists($code, Constants::ERRORS)) {
            return Constants::ERRORS[$code] . '. ' . $message;
        }

        return $message;
    }
}
