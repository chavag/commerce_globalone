<?php

namespace Drupal\commerce_globalone\Integrations;

use Drupal\Component\Utility\Html;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;

class GlobalonePostPaymentMethod extends GlobalonePost {
  
  public $_payment_method;
  public $_operation;

  public function __construct($terminal, PaymentMethodInterface $payment_method, $operation) {
    $this->_terminal = $terminal;
    $this->mode = $terminal['mode'];
    $this->_params = $this->prepareParams($payment_method, $operation);
    $this->_postDateTime = date('d-m-Y:H:i:s').':000';
    $this->_payment_method = $payment_mothod;
    $this->_operation = $_operation;
  }

  public function getPaymentMethod() {
  	return $this->_payment_method;
  }

  public function prepareParams(PaymentMethodInterface $payment_method, $operation) {
    $function = 'prepare' . $operation . 'Params';
    return $this->{$function}($payment_method);
  }

  public function prepareCreateParams(PaymentMethodInterface $payment_method) {
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