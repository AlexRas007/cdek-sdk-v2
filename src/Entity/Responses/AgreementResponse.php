<?php

namespace AntistressStore\CdekSDK2\Entity\Responses;

use AntistressStore\CdekSDK2\Traits\AgreementTrait;
use AntistressStore\CdekSDK2\Traits\CommonTrait;

/**
 * Договоренности о доставке.
 */
class AgreementResponse extends Source
{
    use CommonTrait;
    use AgreementTrait;

    /**
     * Статусы.
     *
     * @var StatusesResponse[]
     */
    protected $statuses;

    /**
     * Get статусы.
     *
     * @return StatusesResponse[]
     */
    public function getStatuses()
    {
        return $this->statuses;
    }
}
