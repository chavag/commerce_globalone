<?php

namespace Drupal\commerce_globalone\Integrations;

use Drupal\Component\Utility\Html;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;

class GlobalonePostPaymentMethod extends GlobalonePost {
  
  public $_paymentMethod;
  public $_operation;

  public function __construct($terminal, PaymentMethodInterface $payment_method, $payment_details, $operation) {
    $this->_terminal = $terminal;
    $this->mode = $terminal['mode'];
    $this->_paymentMethod = $payment_method;
    $this->_operation = $operation;
    $this->_postDateTime = date('d-m-Y:H:i:s').':000';
    $this->_params = $this->prepareParams($payment_details);
  }

  public function getPaymentMethod() {
  	return $this->_paymentMethod;
  }

  public function prepareParams($payment_details) {
    $operation = $this->_operation;
    $function = 'prepare' . $operation . 'Params';
    return $this->{$function}($payment_details);
  }

  public function prepareCreateParams($payment_details) {
    $payment_method = $this->_paymentMethod;

    $params = [];
    $params['XMLEnclosureTag'] = 'SECURECARDREGISTRATION';
    $params['MERCHANTREF'] = uniqid();

    $params['CARDNUMBER'] = $payment_details['number'];

    //@TODO: add CARDHOLDERNAME param
    $params['CARDHOLDERNAME'] = $payment_details['owner'];

    $params['MONTH'] = $payment_details['expiration']['month'];
    $params['YEAR'] = $payment_details['expiration']['year'];
    
    //@TODO: add cvv param
    $params['CVV'] = $payment_details['security_code'];

    $params['CARDTYPE'] = $payment_details['type'];

    return $params;
  }

  public function handleResponse() {
    $approved = parent::handleResponse();
    $response = $this->_resp;
    if (!isset($response['CARDREFERENCE']) || !$response['STATUS']) {
      $message = !empty($response['ERRORSTRING']) ? Html::escape($response['ERRORSTRING']) :  t('something went completlly wrong.');
      $message = t('Globalone : ') . $message;
      drupal_set_message($message, 'error');
      return FALSE;
    } 

    drupal_set_message(t('Globalone : The action successed.'));
    $this->_paymentMethod->setRemoteId($response['MERCHANTREF']);
    $this->_paymentMethod->card_reference = $response['CARDREFERENCE']; 
    return TRUE;   
  }
}