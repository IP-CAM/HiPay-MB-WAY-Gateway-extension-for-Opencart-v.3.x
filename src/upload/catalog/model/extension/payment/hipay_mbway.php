<?php

class ModelExtensionPaymentHipayMbway extends Model {

    public function getMethod($address, $total) {
        $this->load->language('extension/payment/hipay_mbway');

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int) $this->config->get('payment_hipay_mbway_geo_zone_id') . "' AND country_id = '" . (int) $address['country_id'] . "' AND (zone_id = '" . (int) $address['zone_id'] . "' OR zone_id = '0')");
        $status = false;

        if ($this->session->data['currency'] != "EUR") {
            $status = false;
        } elseif ($this->config->get('payment_hipay_mbway_total_min') != "" && $this->config->get('payment_hipay_mbway_total_min') > $total) {
            $status = false;
        } elseif ($this->config->get('payment_hipay_mbway_total_max') != "" && $this->config->get('payment_hipay_mbway_total_max') < $total) {
            $status = false;
        } elseif (!$this->config->get('payment_hipay_mbway_geo_zone_id')) {
            $status = true;
        } elseif ($query->num_rows) {
            $status = true;
        }

        $method_data = array();

        if ($status) {
            $method_data = array('code' => 'hipay_mbway', 'title' => $this->language->get('text_title'), 'terms' => '', 'sort_order' => $this->config->get('payment_hipay_mbway_sort_order')
            );
        }

        return $method_data;
    }

    public function addMbwayReference($order_info, $result) {
        if ($this->config->get('payment_hipay_mbway_sandbox')) {
            $sandbox = 1;
        } else {
            $sandbox = 0;
        }
        $this->db->query("INSERT INTO `" . DB_PREFIX . "hipay_mbway` SET `order_id` = '" . (int) $order_info['order_id'] . "', `reference` = '" . $this->db->escape($result->reference) . "', `date_added` = now(), `date_modified` = now(), `processed` = '0', `status` = '" . $this->db->escape($result->status) . "', `sandbox` = '" . $this->db->escape($sandbox) . "', `entity` = '" . $this->db->escape($result->entity) . "', `total` = '" . $this->db->escape($result->amount) . "'");
        return $this->db->getLastId();
    }

    public function getMbwayReference($order_id) {
        $query = $this->db->query("SELECT order_id, reference, entity, date_added, total, sandbox, processed, status FROM `" . DB_PREFIX . "hipay_mbway` WHERE `order_id` = '" . $order_id . "' LIMIT 1");
        return $query;
    }

    public function updateProcessMbwayReference($order_id, $status) {
        $this->db->query("UPDATE `" . DB_PREFIX . "hipay_mbway` SET `processed` = '1',`status` = '" . $status . "' WHERE `order_id` = '" . $order_id . "' LIMIT 1");
    }

    public function logger($message) {
        if ($this->config->get('payment_hipay_mbway_debug')) {
            error_log(date('Y-m-d H:i:s') . " " . $message . "\n", 3, DIR_LOGS . 'hipay_mbway.log');
        }
    }

}
