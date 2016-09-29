<?php

namespace Drupal\commerce_globalone\Integrations;

use Drupal\Component\Utility\Html;

class GlobalonePost {

  public $_paymentURL;
  public $_paymentParams;
  public $_xml;
  public $_terminal;
  public $_postHash;
  public $_postDateTime;
  public $_normalizedPaymentParams;
  public $_normalizedPaymentReponse;
  public $mode;
  public $_logRequest = FALSE;
  public $_logResponse = FALSE;

  public function __construct($terminal,$paymentParams) {
    $this->_terminal = $terminal;
    $this->mode = $terminal['mode'];
    $this->_paymentParams = $paymentParams;
    $this->_postDateTime = date('d-m-Y:H:i:s').':000';
  }

  public function sendPayment() {
    
    $format = new GlobaloneFormat($this->_paymentParams,$this->_terminal,$this->createHash(),$this->_postDateTime);
    $this->_normalizedPaymentParams = $format->getNormalizedPaymentParams();
    $XML = $format->getXML();

    if ($this->_logRequest) {
      \Drupal::logger('commerce_globalone')->debug('GlobalONE request to @url: !param', array('@url' => $this->_terminal['url'], '!param' => '<pre>' . Html::escape(print_r($XML, TRUE)) . '</pre>'));
    } 

    $this->_xml = $XML;

    $resp = $this->curlXmlRequest($format);
    
    if ($this->_logResponse) {
      \Drupal::logger('commerce_globalone')->debug('GlobalONE response: !param', array('!param' => '<pre>' . Html::escape(print_r($resp, TRUE)) . '</pre>'));  
    }
    $resp['STATUS'] = $this->controlResponseHash($resp);
    return $resp;

  }

  public function curlXmlRequest(GlobaloneFormat $format) {
    $xml = $this->_xml;
    $ch = curl_init($this->_terminal['url']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, "$xml");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);

    // Log any errors to the Drupal logger.
    if ($error = curl_error($ch)) {
      \Drupal::logger('commerce_globalone')->error('cURL error: @error', array('@error' => $error));  
      return FALSE;
    }
    curl_close($ch);
    return $format->XMLToArray($result);
  }

  public function logRequest() {
    $this->_logRequest = TRUE;
  }

  public function logResponse() {
    $this->_logResponse = TRUE;
  }

  public function getXML(){
    return $this->_xml;
  }

  public function createHash() {

    $params = $this->_paymentParams;

    $stringToHash = '';
    $stringToHash .= $this->_terminal['terminal_id'];
    $stringToHash .= $params['ORDERID'];
    // If multi-currency we should add currency to hash.
    if ($this->_terminal['currency'] == 'MCP') {
      $stringToHash .= $params['CURRENCY'];
    }
    $stringToHash .= $params['AMOUNT'];
    $stringToHash .= $this->_postDateTime;
    $stringToHash .= $this->_terminal['secret'];
    $this->_postHash = md5($stringToHash);
    return md5($stringToHash);
  }


  public function buildResponseHash() {
    $reponse = $this->_normalizedPaymentReponse;
    $payment = $this->_paymentParams;
    $stringToHash = '';
    $stringToHash .= $this->_terminal['terminal_id'];
    $stringToHash .= $reponse['UNIQUEREF'];
    $stringToHash .= $payment['AMOUNT'];
    $stringToHash .= $reponse['DATETIME'];
    $stringToHash .= $reponse['RESPONSECODE'];
    $stringToHash .= $reponse['RESPONSETEXT'];
    $stringToHash .= $this->_terminal['secret'];
    $this->_responseHash = md5($stringToHash);
    return md5($stringToHash);
  }


  public function controlResponseHash($responseHash) {
    if(isset($responseHash['ERRORSTRING'])) {
      return false;
    }
    else {
    $this->_normalizedPaymentReponse=$responseHash;
    return ($this->buildResponseHash() == $responseHash['HASH']);
    }
  }

  public function getNormalizedPaymentReponse(){
    return $this->_normalizedPaymentReponse;
  }
  public function getNormalizedPaymentParams(){
    return $this->_normalizedPaymentParams;
  }

}
