<?php

include_once(dirname(__FILE__) . '/vendor/HipayMbway/autoload.php');

use HipayMbway\MbwayClient;
use HipayMbway\MbwayRequestTransaction;
use HipayMbway\MbwayRequestTransactionResponse;
use HipayMbway\MbwayRequestDetails;
use HipayMbway\MbwayRequestResponse;
use HipayMbway\MbwayRequestDetailsResponse;
use HipayMbway\MbwayPaymentDetailsResult;
use HipayMbway\MbwayNotification;

class ControllerExtensionPaymentHipayMbway extends Controller {

    public function index() {

        $this->load->language('extension/payment/hipay_mbway');

        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['text_loading'] = $this->language->get('text_loading');
        $data['text_title'] = $this->language->get('text_title');
        $data['continue'] = $this->url->link('checkout/success');

        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/hipay_mbway')) {
            return $this->load->view($this->config->get('config_template') . '/template/payment/hipay_mbway', $data);
        } else {
            return $this->load->view('extension/payment/hipay_mbway', $data);
        }
    }

    public function confirm() {

        $json = array();

        if ($this->session->data['payment_method']['code'] == 'hipay_mbway') {
            $this->load->language('extension/payment/hipay_mbway');
            $this->load->model('checkout/order');
            $this->load->model('extension/payment/hipay_mbway');

            $data = $this->model_checkout_order->getOrder($this->session->data['order_id']);

            $username = $this->config->get('payment_hipay_mbway_api_user');
            $password = $this->config->get('payment_hipay_mbway_api_password');

            if (isset($this->session->data['guest'])) {
                $customerEmail = $this->session->data['guest']['email'];
                $customerPhone = $this->session->data['guest']['telephone'];
            } else {
                $customerEmail = $this->customer->getEmail();
                $customerPhone = $this->customer->getTelephone();
            }

            $result = new \stdClass;

            $result->amount = number_format($data['total'], 2, ".", "");
            $merchantId = $data['order_id'];
            $category = $this->config->get('payment_hipay_mbway_api_category');
            $notificationUrl = $this->url->link('extension/payment/hipay_mbway/notification');
            $orderDescription = "#" . $data['order_id'];

            $result->entity = $this->config->get('payment_hipay_mbway_entity');

            $mbway = new MbwayClient($this->config->get('payment_hipay_mbway_sandbox'));
            $mbwayRequestTransaction = new MbwayRequestTransaction($username, $password, $result->amount, $customerPhone, $customerEmail, $merchantId, $category, $notificationUrl, $result->entity);
            $mbwayRequestTransaction->set_description($orderDescription);
            $mbwayRequestTransactionResult = new MbwayRequestTransactionResponse($mbway->createPayment($mbwayRequestTransaction)->CreatePaymentResult);

            /*
             * Check Transaction creation result
             */

            if ($mbwayRequestTransactionResult->get_Success() && $mbwayRequestTransactionResult->get_ErrorCode() == "0") {
                $result->reference = $mbwayRequestTransactionResult->get_MBWayPaymentOperationResult()->get_OperationId();
                $result->status = $mbwayRequestTransactionResult->get_MBWayPaymentOperationResult()->get_StatusCode();

                $this->model_extension_payment_hipay_mbway->addMbwayReference($data, $result);
                $orderDescription = $this->language->get('hipay_pending') . " - " . $this->language->get('mbway_payment_desc') . "\n" . $this->language->get('mbway_reference') . ": " . $result->reference . "\n" . $this->language->get('mbway_amount') . ": &euro; " . $result->amount;
                $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('payment_hipay_mbway_order_status_id_pending'), $orderDescription, true);
                $json['redirect'] = $this->url->link('checkout/success');
                $this->model_extension_payment_hipay_mbway->logger('xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
            } else {
                $errorCode = $mbwayRequestTransactionResult->get_MBWayPaymentOperationResult()->get_StatusCode();
                $errorDescription = $mbwayRequestTransactionResult->get_ErrorDescription();
                $this->model_extension_payment_hipay_mbway->logger('order:' . $this->session->data['order_id'] . " " . $errorCode . " " . $errorDescription);
                $json['error'] = $errorDescription;
                $json['redirect'] = $this->url->link('checkout/failure','code=' . $errorCode);
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function notification() {

		header('HTTP/1.1 402 Payment Required');
		$entityBody = file_get_contents('php://input');

        $this->load->language('extension/payment/hipay_mbway');
        $this->load->model('checkout/order');
        $this->load->model('extension/payment/hipay_mbway');

        $notification = new MbwayNotification($entityBody);
        if ($notification->get_isJson() === false) {
            $this->model_extension_payment_hipay_mbway->logger('invalid notification received.');
            die("Invalid notification received.");
        }

        $notification_cart_id = $notification->get_ClientExternalReference();
        $transactionId = $notification->get_OperationId();
        $transactionAmount = $notification->get_Amount();
        $transactionStatusCode = $notification->get_StatusCode();

        $this->load->model('checkout/order');
        $data = $this->model_checkout_order->getOrder($notification_cart_id);


        $result = $this->model_extension_payment_hipay_mbway->getMbwayReference($notification_cart_id);

        if ($result->row["reference"] != $transactionId) {
            $this->model_extension_payment_hipay_mbway->logger('transaction order reference does not match for order:' . $notification_cart_id);
            die("transaction order reference does not match.");
        }

        if ($transactionStatusCode == $result->row["status"]) {
            $this->model_extension_payment_hipay_mbway->logger('transaction status already updated for order:' . $notification_cart_id);
            die("transaction status already updated.");
        }

        switch ($transactionStatusCode) {
            case "c1":
                if ($this->config->get('payment_hipay_mbway_order_status_id_pending') === $data["order_status_id"]) {
                    $check = $this->checkTransaction($transactionId);
                    if ($check !== false && $check['detailStatusCode'] == $transactionStatusCode && $transactionId == $check['detailOperationId']) {
                        $this->model_checkout_order->addOrderHistory($notification_cart_id, $this->config->get('payment_hipay_mbway_order_status_id_paid'), $this->language->get('hipay_success'), true);
                        $this->model_extension_payment_hipay_mbway->updateProcessMbwayReference($notification_cart_id, $transactionStatusCode);
                        header('HTTP/1.1 200 OK');
                        print "MB WAY payment confirmed for transaction $transactionId." . PHP_EOL;
                    }
                }
                break;
            case "c3":
            case "c6":
            case "vp1":
                //do nothing
                print "Waiting capture notification for transaction $transactionId." . PHP_EOL;
                break;
            case "ap1":
                if ($this->config->get('payment_hipay_mbway_order_status_id_paid') === $data["order_status_id"]) {
                    $check = $this->checkTransaction($transactionId);
                    if ($check !== false && $check['detailStatusCode'] == $transactionStatusCode && $transactionId == $check['detailOperationId']) {
                        $this->model_checkout_order->addOrderHistory($notification_cart_id, $this->config->get('payment_hipay_mbway_order_status_id_refunded'), $this->language->get('hipay_refund'), true);
                        $this->model_extension_payment_hipay_mbway->updateProcessMbwayReference($notification_cart_id, $transactionStatusCode);
                        header('HTTP/1.1 200 OK');
                        print "Refunded transaction $transactionId." . PHP_EOL;
                    }
                }
                break;
            case "vp3":
            case "er1":
            case "er2":
                if ($this->config->get('payment_hipay_mbway_order_status_id_pending') === $data["order_status_id"]) {
                    $check = $this->checkTransaction($transactionId);
                    if ($check !== false && $check['detailStatusCode'] == $transactionStatusCode && $transactionId == $check['detailOperationId']) {
                        $error_message = $this->language->get('hipay_error_' . $transactionStatusCode);
                        $this->model_checkout_order->addOrderHistory($notification_cart_id, $this->config->get('payment_hipay_mbway_order_status_id_expired'), $error_message, true);
                        $this->model_extension_payment_hipay_mbway->updateProcessMbwayReference($notification_cart_id, $transactionStatusCode);
                        header('HTTP/1.1 200 OK');
                        print "MB WAY payment cancelled transaction $transactionId." . PHP_EOL;
                    }
                }
                break;

            case "c2":
            case "c4":
            case "c5":
            case "c7":
            case "c8":
            case "c9":
            case "vp2":

                if ($this->config->get('payment_hipay_mbway_order_status_id_pending') === $data["order_status_id"]) {
                    $check = $this->checkTransaction($transactionId);
                    if ($check !== false && $check['detailStatusCode'] == $transactionStatusCode && $transactionId == $check['detailOperationId']) {
                        $error_message = $this->language->get('hipay_cancelled');
                        $this->model_checkout_order->addOrderHistory($notification_cart_id, $this->config->get('payment_hipay_mbway_order_status_id_expired'), $error_message, true);
                        $this->model_extension_payment_hipay_mbway->updateProcessMbwayReference($notification_cart_id, $transactionStatusCode);
                        header('HTTP/1.1 200 OK');
                        print "MB WAY payment cancelled transaction $transactionId." . PHP_EOL;
                    }
                }
                break;
        }

        return true;
    }

    private function checkTransaction($transactionId) {

        $username = $this->config->get('payment_hipay_mbway_api_user');
        $password = $this->config->get('payment_hipay_mbway_api_password');
        $entity = $this->config->get('payment_hipay_mbway_entity');

        $mbway = new MbwayClient($this->config->get('payment_hipay_mbway_sandbox'));
        $mbwayRequestDetails = new MbwayRequestDetails($username, $password, $transactionId, $entity);
        $mbwayRequestDetailsResult = new MbwayRequestDetailsResponse($mbway->getPaymentDetails($mbwayRequestDetails)->GetPaymentDetailsResult);

        if ($mbwayRequestDetailsResult->get_ErrorCode() <> 0 || !$mbwayRequestDetailsResult->get_Success()) {
            return false;
        } else {
            $result = array();
            $result['detailStatusCode'] = $mbwayRequestDetailsResult->get_MBWayPaymentDetails()->get_StatusCode();
            $result['detailOperationId'] = $mbwayRequestDetailsResult->get_MBWayPaymentDetails()->get_OperationId();
            return $result;
        }
    }

}
