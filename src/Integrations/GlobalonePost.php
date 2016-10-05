<?php

namespace Drupal\commerce_globalone\Integrations;

use Drupal\Component\Utility\Html;

class GlobalonePost {

  public $_URL;
  public $_params;
  public $_xml;
  public $_terminal;
  public $_postHash;
  public $_postDateTime;
  public $_normalizedParams;
  public $_normalizedReponse;
  public $mode;
  public $_logRequest = FALSE;
  public $_logResponse = FALSE;

  public function __construct($terminal,$params) {
    $this->_terminal = $terminal;
    $this->mode = $terminal['mode'];
    $this->_params = $params;
    $this->_postDateTime = date('d-m-Y:H:i:s').':000';
  }

  public function sendRequest() {
    
    $format = new GlobaloneFormat($this->_params,$this->_terminal,$this->createHash(),$this->_postDateTime);
    $this->_normalizedParams = $format->getNormalizedParams();
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

    $params = $this->_params;

    switch ($params['XMLEnclosureTag']) {
      case 'PAYMENT':
        $hash = array($this->_terminal['terminal_id'], $params['ORDERID']);
        // If multi-currency we should add currency to hash.
        if ($this->_terminal['currency'] == 'MCP') {
          $hash[] = $params['CURRENCY'];
        }
        $hash += array($params['AMOUNT'], $this->_postDateTime, $this->_terminal['secret']);
      break;
      case 'SECURECARDREGISTRATION':
      case 'SECURECARDUPDATE':
        // set all keys on parameters array, to avoid php errors 
        $fields = array('CARDNUMBER', 'CARDEXPIRY', 'CARDTYPE', 'CARDHOLDERNAME');
        foreach ($fields as $key) {
          if (!isset($params[$key])) {
            $params[$key] = '';
          }
        }

        $hash = array($this->_terminal['terminal_id'], $params['MERCHANTREF'], $this->_postDateTime,
          $params['CARDNUMBER'], $params['CARDEXPIRY'], $params['CARDTYPE'], 
          $params['CARDHOLDERNAME'], $this->_terminal['secret']);
      break;
      case 'SECURECARDREMOVAL':
        $hash = array($this->_terminal['terminal_id'], $params['MERCHANTREF'],
          $this->_postDateTime, $params['CARDREFERENCE'], $this->_terminal['secret']);
      break;
    }

    $stringToHash = implode('', $hash); 
    $this->_postHash = md5($stringToHash);
    return md5($stringToHash);
  }

  public function buildResponseHash() {
    $reponse = $this->_normalizedReponse;
    $params = $this->_params;
    switch ($params['XMLEnclosureTag']) {
      case 'PAYMENT':
        $hash = array($this->_terminal['terminal_id'], $params['UNIQUEREF'],
          $params['AMOUNT'], $reponse['DATETIME'], $reponse['RESPONSECODE'],
          $reponse['RESPONSETEXT'], $this->_terminal['secret']);
      break;
      case 'SECURECARDREGISTRATION':
      case 'SECURECARDUPDATE':
        $hash = array($this->_terminal['terminal_id'], $params['MERCHANTREF'], 
          $reponse['CARDREFERENCE'], $reponse['DATETIME'], $this->_terminal['secret']);
      break;
      case 'SECURECARDREMOVAL':
        $hash = array($this->_terminal['terminal_id'], $params['MERCHANTREF'],
          $reponse['DATETIME'], $this->_terminal['secret']);
      break;
    }

    $stringToHash = implode('', $hash); 

    $this->_responseHash = md5($stringToHash);
    return md5($stringToHash);
  }

  public function controlResponseHash($responseHash) {
    if(isset($responseHash['ERRORSTRING'])) {
      return false;
    }
    else {
    $this->_normalizedReponse=$responseHash;
    return ($this->buildResponseHash() == $responseHash['HASH']);
    }
  }

  public function getNormalizedReponse(){
    return $this->_normalizedReponse;
  }
  public function getNormalizedParams(){
    return $this->_normalizedParams;
  }

}
