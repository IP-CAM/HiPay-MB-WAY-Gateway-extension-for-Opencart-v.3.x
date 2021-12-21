<?php

namespace HipayMbway;

use HipayMbway\MbwayRequestResponse;

/**
 * Description of MbwayRequestRefundDetailsResponse
 *
 * @author hipay.pt
 */
class MbwayRequestRefundDetailsResponse {

    function __construct($response) {
        $this->ErrorCode = $response->ErrorCode;
        $this->Success = $response->Success;
        $this->ErrorDescription = $response->ErrorDescription;

    }

}
