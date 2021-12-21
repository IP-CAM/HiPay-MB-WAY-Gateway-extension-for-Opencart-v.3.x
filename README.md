# HiPay MB WAY Gateway extension for Opencart 3

## API credentials

HiPay API production or sandbox account credentials for each currency:
   - username
   - password
   - entity
   - category id

## Setup
    
  - Sandbox: enable or disable sandbox/test account
  - Entity: MB WAY Entity enabled for your account
  - Username and Password: credentials for HiPay MB WAY API 
  - Category id
  - Minimum and maximum amount to activate the payment method
  - Debug: enable to log payment info 
  - Order status for pending, cancelled, failed, expired, refunded and paid transactions.
  - Geo zone: zones where the payment method is activated
  - Status: enable or disable the extension
  - Sort order: payment method checkout order
  
## Show MB WAY reference on success page
Edit file ***catalog/controller/checkout/success.php*** and find 

    $this->cart->clear();

After that line add

		if ($this->session->data['payment_method']['code'] == 'hipay_mbway'){
			$this->load->language('extension/payment/hipay_mbway');
			$this->load->model('extension/payment/hipay_mbway');
			$data['hipay_mbway_reference'] = $this->model_extension_payment_hipay_mbway->getMbwayReference($this->session->data['order_id']);

			if (isset($data['hipay_mbway_reference']->row["reference"] )) {
				$data['mbway_reference_value'] = $data['hipay_mbway_reference']->row["reference"];	
				$data['mbway_amount_value'] = $data['hipay_mbway_reference']->row["total"];	
					
				$mbwayReference = $this->load->view('extension/payment/hipay_mbway_reference', $data);
			}
		}

Then find

    $data['continue'] = $this->url->link('common/home');

and before that line add

    if (isset($mbwayReference)){
    	$data['text_message'] .= $mbwayReference;
    }



    
## Requirements
  - SOAP extension

Version 1.0.0.0
