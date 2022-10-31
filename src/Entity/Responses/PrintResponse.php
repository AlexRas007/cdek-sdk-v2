<?php

/**
 * Copyright (c) Antistress.Store® 2021. All rights reserved.
 * See LICENSE.md for license details.
 *
 * @author Sergey Gusev
 */

namespace AntistressStore\CdekSDK2\Entity\Responses;

class PrintResponse extends EntityResponse
{
    /**
     * Получить параметр - Список заказов.
     *
     * @return OrderResponse[]
	 */
    public function getOrderUuid()
    {
        $orders = [];
        if (isset($this->entity['orders'])) {
            foreach ($this->entity['orders'] as $order) {
                if (isset($order['order_uuid'])) {
                    $orders[] = OrderResponse::withOrderUuid($order['order_uuid']);
                }
                if (isset($order['cdek_number'])) {
                    $orders[] = OrderResponse::withCdekNumber($order['cdek_number']);
                }
            }
        }

        return $orders;
    }

    /**
     * Ссылка на скачивание файла. Содержится в ответе только в статусе "Сформирован".
     *
     * @return string|null
     */
    public function getUrl()
    {
        if (isset($this->entity['url'])) {
            return $this->entity['url'];
        }

		return null;
    }

    /**
     * Получить параметр - Число копий на листе.
     *
     * @return int|null
     */
    public function getCopyCount()
    {
        if (isset($this->entity['copy_count'])) {
            return $this->entity['copy_count'];
        }

		return null;
    }

    /**
     * Получить параметр - Язык печатной формы в кодировке ISO - 639-3. По умолчанию - RUS.
     *
     * @return string|null
     */
    public function getLang()
    {
        if (isset($this->entity['lang'])) {
            return $this->entity['lang'];
        }

		return null;
    }

    /**
     * Получить параметр - Формат печати.
     *
     * @return string|null
     */
    public function getFormat()
    {
        if (isset($this->entity['format'])) {
            return $this->entity['format'];
        }

		return null;
    }

    /**
     * Получить параметр - Статус файла.
     *
     * @return StatusesResponse[]|null
     */
    public function getStatuses()
    {
        if (isset($this->entity['statuses'])) {
            foreach ($this->entity['statuses'] as $status) {
                $statuses[] = new StatusesResponse($status);
            }

            return $statuses;
        }

		return null;
    }
}
