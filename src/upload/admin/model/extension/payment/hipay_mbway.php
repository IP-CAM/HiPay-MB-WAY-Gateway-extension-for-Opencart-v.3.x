<?php

class ModelExtensionPaymentHipayMbway extends Model {

    public function install() {
        $this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "hipay_mbway` (
			  `hipay_mbway_id` INT(11) NOT NULL AUTO_INCREMENT,
			  `order_id` INT(11) NOT NULL,
			  `reference` VARCHAR(20),
			  `entity` VARCHAR(10),
			  `date_added` DATETIME NOT NULL,
			  `date_modified` DATETIME NOT NULL,
			  `processed` TINYINT(1) DEFAULT 0,
			  `sandbox` TINYINT(1) DEFAULT 0,
			  `total` DECIMAL( 10, 2 ) NOT NULL,
			  `status` VARCHAR(5),
			  PRIMARY KEY (`hipay_mbway_id`)
			) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;");
    }

    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "hipay_mbway`;");
    }

    public function logger($message) {
        if ($this->config->get('payment_hipay_mbway_debug')) {
            error_log(date('Y-m-d H:i:s') . " " . $message . "\n", 3, DIR_LOGS . "hipay_mbway.log");
        }
    }

}
