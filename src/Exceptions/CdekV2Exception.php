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
     * @var string
     */
    protected $messageExtended;

    /**
     * Сконструировать исключение. Примечание. Сообщение НЕ является двоично-безопасным.
     *
     * @param string $codeMessage [optional] Код сообщения об исключении, которое нужно выбросить
     * @param string $message [optional] Сообщение об исключении, которое нужно выбросить
     * @param string $messageExtended [optional] Расширенное сообщение об исключении, которое нужно выбросить
     * @param int $code [optional] Код исключения
     */
    public function __construct($codeMessage = "", $message = "", $messageExtended = "", $code = 0)
    {
        parent::__construct($message, $code);

        $this->codeMessage = $codeMessage;
        $this->messageExtended = $messageExtended;
    }

    /**
     * Возвращает код сообщения об исключении, которое нужно выбросить.
     *
     * @return string Код сообщения об исключении в виде строки
     */
    final public function getCodeMessage()
    {
        return $this->codeMessage;
    }

    /**
     * Возвращает расширенное сообщение об исключении, которое нужно выбросить.
     *
     * @return string Расширенное сообщение об исключении в виде строки
     */
    final public function getMessageExtended()
    {
        return $this->messageExtended;
    }

    /**
     * @param string $code
     * @param string|null $message
     * @return string
     */
    public static function getTranslation($code, $message = null)
    {
        if (array_key_exists($code, Constants::ERRORS)) {
            return Constants::ERRORS[$code] . '. ' . $message;
        }

        return $message;
    }
}
