<?php

namespace Drupal\commerce_globalone\Integrations;

use Drupal\Component\Utility\Html;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_price\Price;

class GlobalonePostPayment extends GlobalonePost {
  
  public $_payment;

  public function __construct($terminal, $payment) {
    $this->_terminal = $terminal;
    $this->mode = $terminal['mode'];
    $this->_params = $this->prepareParams($payment);
    $this->_postDateTime = date('d-m-Y:H:i:s').':000';
    $this->_payment = $payment;
  }

  public function getPayment() {
  	return $this->_payment;
  }

  public function prepareParams(PaymentInterface $payment) {
  	$payment_method = $payment->getPaymentMethod();

  	if ($payment_method->card_reference != '') {
  		$params = [];
	    $params['XMLEnclosureTag'] = 'PAYMENT';
	    $params['ORDERID'] = $payment->getOrderId();

	    $params['AMOUNT'] = number_format($payment->getAmount()->getNumber(), 2);
	    $params['CURRENCY'] = $payment->getAmount()->getCurrencyCode();
	    $params['CARDNUMBER'] = $payment_method->card_reference->value;
	    
	    //@TODO: add cvv param
	    $params['CVV'] = $payment_method->card_cvv->value;

	    $params['CARDTYPE'] = 'SECURECARD';
	    $params['DESCRIPTION'] = t('GlobalOne payment from drupal commerce 2');

	    return $params;
  	}

  	throw new \InvalidArgumentException(sprintf('card_reference cannot be null'));
  }

  public function handleResponse() {
  	$response = $this->_resp;
  	$approved = parent::handleResponse();
  	if (!isset($response['RESPONSECODE']) || !$response['STATUS']) {
      $message = !empty($response['ERRORSTRING']) ? Html::escape($response['ERRORSTRING']) :  t('something went completlly wrong.');
      $message = t('Globalone : ') . $message;
      drupal_set_message($message, 'error');
      return FALSE;
    } 

    switch ($response['RESPONSECODE']) {
      // Approved.
      case 'A':
        drupal_set_message(t('Globalone : The payment approved.'));
        $this->_payment->remote_id = $response['UNIQUEREF'];
	      $this->_payment->remote_status = $response['RESPONSETEXT'];  	
        return TRUE;
       break; 
        // Referred.
      case 'R':
        drupal_set_message(t('Globalone : The payment gateway referred authorisation.'), 'error');
        return FALSE;
      break;  
        // Declined or unknown.
      case 'D':
      default:
        drupal_set_message(t('Globalone : The payment failed with the response: @response.', array(
          '@response' => $response['RESPONSETEXT'],
        )), 'error');
      return FALSE;
      break;

    }
  }

}