<?php

namespace Drupal\commerce_globalone\Integrations\GlobalonePost;

use Drupal\commerce_globalone\Integrations\GlobaloneFormat;

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
      watchdog('commerce_globalone', 'GlobalONE request to @url: !param', array('@url' => $this->_terminal['url'], '!param' => '<pre>' . check_plain(print_r($XML, TRUE)) . '</pre>'), WATCHDOG_DEBUG);
    } 

    $this->_xml = $XML;

    $resp = $this->curlXmlRequest();
    
    if ($this->_logResponse) {
      watchdog('commerce_globalone', 'GlobalONE response: !param', array('!param' => '<pre>' . check_plain(print_r($resp, TRUE)) . '</pre>'), WATCHDOG_DEBUG);
    }
    $resp['STATUS'] = $this->controlResponseHash($resp);
    return $resp;

  }

  public function curlXmlRequest() {
    $xml = $this->_xml;
    $ch = curl_init($this->_terminal['url');
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, "$xml");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);

    // Log any errors to the watchdog.
    if ($error = curl_error($ch)) {
      watchdog('commerce_globalone', 'cURL error: @error', array('@error' => $error), WATCHDOG_ERROR);
      return FALSE;
    }
    curl_close($ch);
    return $this->XMLToArray($result);
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
    $stringToHash .= $params['AMOUNT'];
    // If multi-currency we should add currency to hash.
    if ($this->_terminal['currency'] == 'MCP') {
      $stringToHash .= $params['CURRENCY'];
    }
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
