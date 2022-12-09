<?php

/**
 * Copyright (c) Antistress.Store® 2021. All rights reserved.
 * See LICENSE.md for license details.
 *
 * @author Sergey Gusev
 */

namespace AntistressStore\CdekSDK2;

use AntistressStore\CdekSDK2\Entity\Requests\Agreement;
use AntistressStore\CdekSDK2\Entity\Requests\Barcode;
use AntistressStore\CdekSDK2\Entity\Requests\Check;
use AntistressStore\CdekSDK2\Entity\Requests\DeliveryPoints;
use AntistressStore\CdekSDK2\Entity\Requests\Intakes;
use AntistressStore\CdekSDK2\Entity\Requests\Invoice;
use AntistressStore\CdekSDK2\Entity\Requests\Location;
use AntistressStore\CdekSDK2\Entity\Requests\Order;
use AntistressStore\CdekSDK2\Entity\Requests\Tariff;
use AntistressStore\CdekSDK2\Entity\Requests\Webhooks;
use AntistressStore\CdekSDK2\Entity\Responses\AgreementResponse;
use AntistressStore\CdekSDK2\Entity\Responses\CheckResponse;
use AntistressStore\CdekSDK2\Entity\Responses\CitiesResponse;
use AntistressStore\CdekSDK2\Entity\Responses\DeliveryPointsResponse;
use AntistressStore\CdekSDK2\Entity\Responses\EntityResponse;
use AntistressStore\CdekSDK2\Entity\Responses\IntakesResponse;
use AntistressStore\CdekSDK2\Entity\Responses\OrderResponse;
use AntistressStore\CdekSDK2\Entity\Responses\PaymentResponse;
use AntistressStore\CdekSDK2\Entity\Responses\PrintResponse;
use AntistressStore\CdekSDK2\Entity\Responses\RegionsResponse;
use AntistressStore\CdekSDK2\Entity\Responses\TariffListResponse;
use AntistressStore\CdekSDK2\Entity\Responses\TariffResponse;
use AntistressStore\CdekSDK2\Exceptions\CdekV2AuthException;
use AntistressStore\CdekSDK2\Exceptions\CdekV2RequestException;
use GuzzleHttp\Client as GuzzleClient;
use Psr\Http\Message\StreamInterface;

/**
 * Class CdekClientV2 - клиент взаимодействия с api cdek 2.0.
 */
final class CdekClientV2
{
    /**
     * Аккаунт сервиса интеграции.
     *
     * @var string
     */
    private $account;

    /**
     * Тип аккаунта.
     *
     * @var string
     */
    private $account_type;

    /**
     * Секретный пароль сервиса интеграции.
     *
     * @var string
     */
    private $secure;

    /**
     * Authorization: Bearer Токен.
     *
     * @var string
     */
    private $token;

    /**
     * Настройки массив сохранения.
     *
     * @var array
     */
    private $memory;

    /**
     * Коллбэк сохранения токэна.
     *
     * @var callable
     */
    private $memory_save_fu;

    /** @var int */
    private $expire = 0;

    /** @var GuzzleClient */
    private $http;

    /**
     * Конструктор клиента Guzzle.
     *
     * @param string $account - Логин Account в сервисе Интеграции
     * @param string|null $secure - Пароль Secure password в сервисе Интеграции
     * @param float|null $timeout - Настройка клиента задающая общий тайм-аут запроса в секундах. При использовании 0 ждать бесконечно долго (поведение по умолчанию)
     */
    public function __construct($account, $secure = null, $timeout = 5.0)
    {
        if ($account === 'TEST') {
            $this->http = new GuzzleClient([
                'base_uri' => Constants::API_URL_TEST,
                'timeout' => $timeout,
                'http_errors' => false,
            ]);
            $this->account = Constants::TEST_ACCOUNT;
            $this->secure = Constants::TEST_SECURE;
            $this->account_type = 'TEST';
        } else {
            $this->http = new GuzzleClient([
                'base_uri' => Constants::API_URL,
                'timeout' => $timeout,
                'http_errors' => false,
            ]);
            $this->account = $account;
            $this->secure = $secure;
            $this->account_type = 'COMBAT';
        }
    }

    /**
     * Выполняет вызов к API.
     *
     * @param string|null $type - Метод запроса
     * @param string|null $method - url path запроса
     * @param object|array|null $params - массив данных параметров запроса
     * @return array|StreamInterface
     * @throws CdekV2AuthException
     * @throws CdekV2RequestException
     */
    private function apiRequest($type, $method, $params = null)
    {
        // Авторизуемся или получаем данные из кэша\сессии
        if ($this->checkSavedToken() == false) {
            $this->authorize();
        }

        // Проверяем является ли запрос на файл pdf
        $is_pdf_file_request = strpos($method, '.pdf');

        if ($is_pdf_file_request !== false) {
            $headers['Accept'] = 'application/pdf';
        } else {
            $headers['Accept'] = 'application/json';
        }

        $headers['Authorization'] = 'Bearer ' . $this->token;

        if (!empty($params) && is_object($params)) {
            $params = $params->prepareRequest();
        }

        switch ($type) {
            case 'GET':
                $response = $this->http->get($method, ['query' => $params, 'headers' => $headers]);
                break;
            case 'DELETE':
                $response = $this->http->delete($method, ['headers' => $headers]);
                break;
            case 'POST':
                $response = $this->http->post($method, ['json' => $params, 'headers' => $headers]);
                break;
            case 'PATCH':
                $response = $this->http->patch($method, ['json' => $params, 'headers' => $headers]);
                break;
        }
        // Если запрос на файл pdf был успешным сразу отправляем его в ответ
        if ($is_pdf_file_request) {
            if ($response->getStatusCode() == 200) {
                if (strpos($response->getHeader('Content-Type')[0], 'application/pdf') !== false) {
                    return $response->getBody();
                }
            }
        }
        $json = $response->getBody()
            ->getContents();
        $apiResponse = json_decode($json, true);

        $this->checkErrors($type, $method, $response, $apiResponse);

        return $apiResponse;
    }

    /**
     * Авторизация клиента в сервисе Интеграции.
     *
     * @return bool
     * @throws CdekV2AuthException
     */
    private function authorize()
    {
        $param = [
            Constants::AUTH_KEY_TYPE => Constants::AUTH_PARAM_CREDENTIAL,
            Constants::AUTH_KEY_CLIENT_ID => $this->account,
            Constants::AUTH_KEY_SECRET => $this->secure,
        ];
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';

        $response = $this->http->post(
            Constants::OAUTH_URL,
            [
                'form_params' => $param,
                'headers' => $headers,
            ]
        );

        if ($response->getStatusCode() == 200) {
            $token_info = json_decode($response->getBody());
            $this->token = isset($token_info->access_token) ? $token_info->access_token : '';
            $this->expire = isset($token_info->expires_in) ? $token_info->expires_in : 0;
            $this->expire = (int) (time() + $this->expire - 10);
            if (!empty($this->memory_save_fu)) {
                $this->saveToken($this->memory_save_fu);
            }

            return true;
        }
        throw new CdekV2AuthException(
            "error_auth",
            'СДЭК: ' . Constants::AUTH_FAIL,
            Constants::AUTH_FAIL
        );
    }

    /**
     * Проверяет соответствует ли переданный
     * массив сохраненный данных авторизации требованиям
     *
     * @return CdekClientV2|false
     */
    private function checkSavedToken()
    {
        $check_memory = $this->getMemory();

        // Если не передан верный сохраненный массив данных для авторизации, функция возвратит false

        if (!isset($check_memory['account_type'])
            || empty($check_memory)
            || !isset($check_memory['expires_in'])
            || !isset($check_memory['access_token'])) {
            return false;
        }

        // Если не передан верный сохраненный массив данных для авторизации,
        // но тип аккаунта не тот, который был при прошлой сохраненной авторизации - функция возвратит false

        if ($check_memory['account_type'] !== $this->account_type) {
            return false;
        }

        return ($check_memory['expires_in'] > time() && !empty($check_memory['access_token']))
            ? $this->setToken($check_memory['access_token'])
            : false;
    }

    /**
     * Сохранить токен через колл бэк сохранения.
     *
     * @param callable $fu - колл бэк сохранения
     * @return mixed
     */
    private function saveToken(callable $fu)
    {
        return $fu([
            'cdekAuth' => [
                'expires_in' => $this->expire,
                'access_token' => $this->token,
                'account_type' => $this->account_type,
            ],
        ]);
    }

    /**
     * Установить параметр настройки сохранения.
     *
     * @param array|null $memory - массив настройки сохранения
     * @param callable $fu - колл бэк сохранения
     * @return self
     */
    public function setMemory($memory, callable $fu)
    {
        $this->memory = $memory;
        $this->memory_save_fu = $fu;

        return $this;
    }

    /**
     * Проверяет передан ли сохраненный массив данных авторизации.
     *
     * @return array|null
     */
    private function getMemory()
    {
        return $this->memory;
    }

    /**
     * @return string
     */
    private function getToken()
    {
        if (empty($this->token)) {
            throw new \InvalidArgumentException('Не передан API-токен!');
        }

        return $this->token;
    }

    /**
     * Устанавливает токен из данных авторизации сервера
     * или из переданной памяти.
     *
     * @return self
     */
    private function setToken($token)
    {
        $this->token = $token;

        return $this;
    }

    /**
     * Проверка ответа на ошибки.
     *
     * @param mixed $type
     * @param mixed $method
     * @param mixed $response
     * @param mixed $apiResponse
     * @return bool
     * @throws CdekV2RequestException
     */
    private function checkErrors($type, $method, $response, $apiResponse)
    {
        if (empty($apiResponse)) {
            throw new CdekV2RequestException(
                "empty_response",
                'СДЭК: пришел пустой ответ',
                'От API CDEK при вызове метода ' . $method . ' пришел пустой ответ',
                $response->getStatusCode()
            );
        }
        if (
            ($response->getStatusCode() > 202 && isset($apiResponse['requests'][0]['errors']))
            || ($type !== 'GET' && isset($apiResponse['requests'][0]['state']) && $apiResponse['requests'][0]['state'] === 'INVALID')
        ) {
            $message = CdekV2RequestException::getTranslation(
                $apiResponse['requests'][0]['errors'][0]['code'],
                $apiResponse['requests'][0]['errors'][0]['message']
            );
            $messageFull = CdekV2RequestException::getTranslation(
                $apiResponse['requests'][0]['errors'][0]['code'],
                $apiResponse['requests'][0]['errors'][0]['message'],
            true
            );
            throw new CdekV2RequestException(
                $apiResponse['requests'][0]['errors'][0]['code'],
                'СДЭК: ' . $message,
                'От API CDEK при вызове метода ' . $method . ' получена ошибка: ' . $messageFull,
                $response->getStatusCode()
            );
        }
        if (
            ($type !== 'GET' && $response->getStatusCode() == 200 && isset($apiResponse['errors']))
            || ($type !== 'GET' && isset($apiResponse['state']) && $apiResponse['state'] === 'INVALID')
            || ($response->getStatusCode() !== 200 && isset($apiResponse['errors']))
        ) {
            $message = CdekV2RequestException::getTranslation(
                $apiResponse['errors'][0]['code'],
                $apiResponse['errors'][0]['message']
            );
            $messageFull = CdekV2RequestException::getTranslation(
                $apiResponse['errors'][0]['code'],
                $apiResponse['errors'][0]['message'],
            true
            );
            throw new CdekV2RequestException(
                $apiResponse['errors'][0]['code'],
                'СДЭК: ' . $message,
                'От API CDEK при вызове метода ' . $method . ' получена ошибка: ' . $messageFull,
                $response->getStatusCode()
            );
        }
        if ($response->getStatusCode() > 202 && !isset($apiResponse['requests'][0]['errors'])) {
            throw new CdekV2RequestException(
                "error_response",
                'Неверный код ответа СДЭК: ' . $response->getStatusCode(),
                'Неверный код ответа от сервера CDEK при вызове метода ' . $method . ': ' . $response->getStatusCode(),
                $response->getStatusCode()
            );
        }

        return false;
    }

    /**
     * Получение списка регионов.
     *
     * @param Location|null $filter
     * @return RegionsResponse[]
     * @throws CdekV2AuthException
     * @throws CdekV2RequestException
     */
    public function getRegions($filter = null)
    {
        $params = (!empty($filter)) ? $filter->regions() : [];

        $resp = [];
        $response = $this->apiRequest('GET', Constants::REGIONS_URL, $params);

        foreach ($response as $key => $value) {
            $resp[] = new RegionsResponse($value);
        }

        return $resp;
    }

    /**
     * Получение списка городов.
     *
     * @param Location|null $filter
     * @return CitiesResponse[]
     * @throws CdekV2AuthException
     * @throws CdekV2RequestException
     */
    public function getCities($filter = null)
    {
        $params = (!empty($filter)) ? $filter->cities() : [];

        $resp = [];
        $response = $this->apiRequest('GET', Constants::CITIES_URL, $params);
        foreach ($response as $key => $value) {
            $resp[] = new CitiesResponse($value);
        }

        return $resp;
    }

    /**
     * Получение списка ПВЗ СДЭК.
     *
     * @param DeliveryPoints|null $filter
     * @return DeliveryPointsResponse[]
     * @throws CdekV2AuthException
     * @throws CdekV2RequestException
     */
    public function getDeliveryPoints($filter = null)
    {
        $resp = [];
        $response = $this->apiRequest('GET', Constants::DELIVERY_POINTS_URL, $filter);
        foreach ($response as $key => $value) {
            $resp[] = new DeliveryPointsResponse($value);
        }

        return $resp;
    }

    /**
     * Расчет стоимости и сроков доставки по коду тарифа.
     *
     * @param Tariff $tariff - Объект класса Tariff установки запроса для тарифа
     * @return TariffResponse Ответ
     * @throws CdekV2AuthException
     * @throws CdekV2RequestException
     */
    public function calculateTariff(Tariff $tariff)
    {
        if ($tariff->getTariffCode()) {
            return new TariffResponse($this->apiRequest('POST', Constants::CALC_TARIFF_URL, $tariff));
        }
        throw new \InvalidArgumentException('Не установлен обязательный параметр: tariff_code');
    }

    /**
     * Метод используется для расчета стоимости и сроков доставки по всем доступным тарифам.
     *
     * @param Tariff $tariff - Объект класса Tariff установки запроса для тарифа
     * @return TariffListResponse[] Ответ
     * @throws CdekV2AuthException
     * @throws CdekV2RequestException
     */
    public function calculateTariffList(Tariff $tariff)
    {
        $response = $this->apiRequest('POST', Constants::CALC_TARIFFLIST_URL, $tariff);

        $resp = [];
        foreach ($response['tariff_codes'] as $key => $value) {
            $resp[] = new TariffListResponse($value);
        }

        return $resp;
    }

    /**
     * Создание заказа.
     *
     * @param Order $order - Параметры заказа
     * @return EntityResponse
     * @throws CdekV2AuthException
     * @throws CdekV2RequestException
     */
    public function createOrder(Order $order)
    {
        return new EntityResponse($this->apiRequest('POST', Constants::ORDERS_URL, $order));
    }

    /**
     * Позволяет удалить заказ по uuid.
     *
     * @param string $uuid - Идентификатор сущности, связанной с заказом
     * @return bool
     * @throws CdekV2AuthException
     * @throws CdekV2RequestException
     */
    public function deleteOrder($uuid)
    {
        $request = new EntityResponse($this->apiRequest('DELETE', Constants::ORDERS_URL . '/' . $uuid));

        return $request->getRequests()[0]->getState() === 'INVALID';
    }

    /**
     * Регистрация отказа.
     *
     * @param string $order_uuid - Идентификатор заказа в ИС СДЭК, по которому необходимо зарегистрировать отказ
     * @return EntityResponse
     * @throws CdekV2AuthException
     * @throws CdekV2RequestException
     */
    public function cancelOrder($order_uuid)
    {
        return new EntityResponse(
            $this->apiRequest('POST', Constants::ORDERS_URL . '/' . $order_uuid . '/' . 'refusal')
        );
    }

    /**
     * Обновление заказа.
     *
     * @param Order $order - Параметры заказа
     * @return EntityResponse
     * @throws CdekV2AuthException
     * @throws CdekV2RequestException
     */
    public function updateOrder(Order $order)
    {
        return new EntityResponse($this->apiRequest('PATCH', Constants::ORDERS_URL, $order));
    }

    /**
     * Полная информация о заказе по трек номеру.
     *
     * @param string $cdek_number - Номер заказа(накладной) СДЭК
     * @return OrderResponse
     * @throws CdekV2AuthException
     * @throws CdekV2RequestException
     */
    public function getOrderInfoByCdekNumber($cdek_number)
    {
        return new OrderResponse($this->apiRequest('GET', Constants::ORDERS_URL, ['cdek_number' => $cdek_number]));
    }

    /**
     * Полная информация о заказе по ID заказа в магазине.
     *
     * @param string $im_number - Номер заказа
     * @return OrderResponse
     * @throws CdekV2AuthException
     * @throws CdekV2RequestException
     */
    public function getOrderInfoByImNumber($im_number)
    {
        return new OrderResponse($this->apiRequest('GET', Constants::ORDERS_URL, ['im_number' => $im_number]));
    }

    /**
     * Полная информация о заказе по ID заказа в магазине.
     *
     * @param string $uuid - Идентификатор сущности, связанной с заказом
     * @return OrderResponse
     * @throws CdekV2AuthException
     * @throws CdekV2RequestException
     */
    public function getOrderInfoByUuid($uuid)
    {
        return new OrderResponse($this->apiRequest('GET', Constants::ORDERS_URL . '/' . $uuid));
    }

    /**
     * Запрос на формирование ШК-места к заказу.
     *
     * @param Barcode $barcode
     * @return EntityResponse
     * @throws CdekV2AuthException
     * @throws CdekV2RequestException
     */
    public function setBarcode(Barcode $barcode)
    {
        return new EntityResponse($this->apiRequest('POST', Constants::BARCODES_URL, $barcode));
    }

    /**
     * Получение сущности ШК к заказу.
     *
     * @param string $uuid - Идентификатор сущности ШК
     * @return PrintResponse
     * @throws CdekV2AuthException
     * @throws CdekV2RequestException
     */
    public function getBarcode($uuid)
    {
        return new PrintResponse($this->apiRequest('GET', Constants::BARCODES_URL . '/' . $uuid), true);
    }

    /**
     * Получение Pdf ШК-места к заказу.
     *
     * @param string $uuid - Идентификатор сущности ШК
     * @return StreamInterface
     * @throws CdekV2AuthException
     * @throws CdekV2RequestException
     */
    public function getBarcodePdf($uuid)
    {
        return $this->apiRequest('GET', Constants::BARCODES_URL . '/' . $uuid . '.pdf');
    }

    /**
     * Запрос на формирование накладной к заказу.
     *
     * @param Invoice $invoice
     * @return EntityResponse
     * @throws CdekV2AuthException
     * @throws CdekV2RequestException
     */
    public function setInvoice(Invoice $invoice)
    {
        return new EntityResponse($this->apiRequest('POST', Constants::INVOICE_URL, $invoice));
    }

    /**
     * Получение сущности накладной к заказу.
     *
     * @param string $uuid
     * @return PrintResponse
     * @throws CdekV2AuthException
     * @throws CdekV2RequestException
     */
    public function getInvoice($uuid)
    {
        return new PrintResponse($this->apiRequest('GET', Constants::INVOICE_URL . '/' . $uuid), true);
    }

    /**
     * Получение Pdf накладной к заказу.
     *
     * @param string $uuid
     * @return StreamInterface
     * @throws CdekV2AuthException
     * @throws CdekV2RequestException
     */
    public function getInvoicePdf($uuid)
    {
        return $this->apiRequest('GET', Constants::INVOICE_URL . '/' . $uuid . '.pdf');
    }

    /**
     * Создание договоренностей для курьера.
     *
     * @param Agreement $agreement
     * @return EntityResponse
     * @throws CdekV2AuthException
     * @throws CdekV2RequestException
     */
    public function createAgreement(Agreement $agreement)
    {
        return new EntityResponse($this->apiRequest('POST', Constants::COURIER_AGREEMENTS_URL, $agreement));
    }

    /**
     * Получение договоренностей для курьера.
     *
     * @param string $uuid
     * @return AgreementResponse
     * @throws CdekV2AuthException
     * @throws CdekV2RequestException
     */
    public function getAgreement($uuid)
    {
        return new AgreementResponse($this->apiRequest('GET', Constants::COURIER_AGREEMENTS_URL . '/' . $uuid));
    }

    /**
     * Создание заявки на вызов курьера.
     *
     * @param Intakes $intakes
     * @return EntityResponse
     * @throws CdekV2AuthException
     * @throws CdekV2RequestException
     */
    public function createIntakes(Intakes $intakes)
    {
        return new EntityResponse($this->apiRequest('POST', Constants::INTAKES_URL, $intakes));
    }

    /**
     * Информация о заявке на вызов курьера.
     *
     * @param string $uuid
     * @return IntakesResponse
     * @throws CdekV2AuthException
     * @throws CdekV2RequestException
     */
    public function getIntakes($uuid)
    {
        return new IntakesResponse($this->apiRequest('GET', Constants::INTAKES_URL . '/' . $uuid));
    }

    /**
     * Удаление заявки на вызов курьера.
     *
     * @param string $uuid
     * @return bool
     * @throws CdekV2AuthException
     * @throws CdekV2RequestException
     */
    public function deleteIntakes($uuid)
    {
        $this->apiRequest('DELETE', Constants::INTAKES_URL . '/' . $uuid);

        return false;
    }

    /**
     * Запрос на получение информации о переводе наложенного платежа.
     *
     * @param string $date - Дата, за которую необходимо вернуть список заказов, по которым был переведен наложенный платеж, пример: '2021-03-25'
     * @return PaymentResponse
     * @throws CdekV2AuthException
     * @throws CdekV2RequestException
     */
    public function getPayments($date)
    {
        return new PaymentResponse($this->apiRequest('GET', 'payment', ['date' => $date]));
    }

    /**
     * Метод используется для получения информации о чеке по заказу или за выбранный день.
     *
     * @param Check $check - данные о заказах по которым нужно получить чеки
     * @return CheckResponse
     * @throws CdekV2AuthException
     * @throws CdekV2RequestException
     */
    public function getChecks(Check $check)
    {
        return new CheckResponse($this->apiRequest('GET', 'check', $check));
    }

    /**
     * Добавление нового слушателя webhook.
     *
     * @param Webhooks $webhooks - настройки вебхуков
     * @return EntityResponse
     * @throws CdekV2AuthException
     * @throws CdekV2RequestException
     */
    public function setWebhooks(Webhooks $webhooks)
    {
        return new EntityResponse($this->apiRequest('POST', Constants::WEBHOOKS_URL, $webhooks));
    }

    /**
     * Информация о слушателях webhook.
     *
     * @return EntityResponse
     * @throws CdekV2AuthException
     * @throws CdekV2RequestException
     */
    public function getWebhooks()
    {
        return new EntityResponse($this->apiRequest('GET', Constants::WEBHOOKS_URL));
    }

    /**
     * Информация о слушателе webhook.
     *
     * @param string $uuid
     * @return EntityResponse
     * @throws CdekV2AuthException
     * @throws CdekV2RequestException
     */
    public function getWebhook($uuid)
    {
        return new EntityResponse($this->apiRequest('GET', Constants::WEBHOOKS_URL . '/' . $uuid));
    }

    /**
     * Удаление слушателя webhook.
     *
     * @param string $uuid
     * @return EntityResponse
     * @throws CdekV2AuthException
     * @throws CdekV2RequestException
     */
    public function deleteWebhooks($uuid)
    {
        return new EntityResponse($this->apiRequest('DELETE', Constants::WEBHOOKS_URL . '/' . $uuid));
    }
}
