<?php

namespace Drupal\commerce_globalone\Integrations;

use Drupal\Component\Utility\Html;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;

class GlobalonePostPaymentMethod extends GlobalonePost {
  
  public $_paymentMethod;
  public $_operation;

  public function __construct($terminal, PaymentMethodInterface $payment_method, $operation) {
    $this->_terminal = $terminal;
    $this->mode = $terminal['mode'];
    $this->_paymentMethod = $payment_method;
    $this->_operation = $operation;
    $this->_postDateTime = date('d-m-Y:H:i:s').':000';
    $this->_params = $this->prepareParams($payment_method, $operation);
  }

  public function getPaymentMethod() {
  	return $this->_paymentMethod;
  }

  public function prepareParams($payment_method, $operation) {
    $function = 'prepare' . $operation . 'Params';
    return $this->{$function}($payment_method);
  }

  public function prepareCreateParams($payment_method) {
    $params = [];
    $params['XMLEnclosureTag'] = 'SECURECARDREGISTRATION';
    $params['MERCHANTREF'] = uniqid();
    
    $params['CARDNUMBER'] = $payment_method->card_number->value;

    //@TODO: add CARDHOLDERNAME param
    $params['CARDHOLDERNAME'] = $payment_method->card_owner->value;

    $params['MONTH'] = $payment_method->card_exp_month->value;
    $params['YEAR'] = $payment_method->card_exp_year->value;
    
    //@TODO: add cvv param
    $params['CVV'] = $payment_method->card_cvv->value;

    $params['CARDTYPE'] = $payment_method->card_type->value;

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