<?php

namespace HipayMbway;

use HipayMbway\MbwayRequestResponse;

/**
 * Description of MbwayRequestRefundResponse
 *
 * @author hipay.pt
 */
class MbwayRequestRefundResponse {

    function __construct($response) {
        $this->ErrorCode = $response->ErrorCode;
        $this->Success = $response->Success;
        $this->ErrorDescription = $response->ErrorDescription;
    }

}
