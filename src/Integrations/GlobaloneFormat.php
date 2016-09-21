<?php

namespace Drupal\commerce_globalone\Integrations\GlobaloneFormat;

class GlobaloneFormat{
  protected $_paymentParams;
  protected $_paymentResponse;
  protected $_normalizedPaymentReponse;
  protected $_normalizedPaymentParams;
  protected $_dateTime;
  protected $_terminal;
  protected $_postedHash;
  protected $_postDateTime;
  protected $_xml;
  protected $Pivotal_Config;

  protected function readFields() {
    return array(
      'XMLHeader' => array(
        'version' => '1.0',
        'encoding' => 'UTF-8',
      ),
      'XMLEnclosureTag' => 'PAYMENT',
      'Fields' => array(
        array(
          'name' => 'ORDERID',
          'type' => 'text',
          'maxlenght' => 12,
          'required' => TRUE,
          'form' => FALSE,
        ),
        array(
          'name' => 'TERMINALID',
          'type' => 'number',
          'required' => TRUE,
          'form' => FALSE,
        ),
        array(
          'name' => 'AMOUNT',
          'type' => 'number',
          'required' => TRUE,
          'form' => FALSE,
        ),
        array(
          'name' => 'DATETIME',
          'type' => 'datetime',
          'required' => TRUE,
          'format' => 'Y-m-dH:i:s:u',
          'form' => FALSE,
        ),
        array(
          'name' => 'CARDNUMBER',
          'type' => 'text',
          'minlenght' => 16,
          'maxlenght' => 16,
          'required' => TRUE,
          'form' => FALSE,
        ),
        array(
          'name' => 'CARDTYPE',
          'type' => 'text',
          'required' => TRUE,
          'form' => FALSE,
        ),
        array(
          'name' => 'CARDEXPIRY',
          'type' => 'number',
          'minlenght' => 4,
          'maxlenght' => 4,
          'required' => TRUE,
          'form' => FALSE,
        ),
        array(
          'name' => 'CARDHOLDERNAME',
          'type' => 'text',
          'required' => TRUE,
          'form' => FALSE,
        ),
        array(
          'name' => 'HASH',
          'type' => 'text',
          'required' => TRUE,
          'form' => FALSE,
        ),
        array(
          'name' => 'CURRENCY',
          'type' => 'text',
          'minlenght' => 3,
          'maxlenght' => 3,
          'required' => TRUE,
          'form' => FALSE,
        ),
        array(
          'name' => 'TERMINALTYPE',
          'type' => 'number',
          'minlenght' => 1,
          'maxlenght' => 1,
          'required' => TRUE,
          'form' => FALSE,
          'options' => array(
            '1' => 'MOTO (Mail Order/Telephone Order)',
            '2' => 'eCommerce',
          ),
        ),
        array(
          'name' => 'TRANSACTIONTYPE',
          'type' => 'number',
          'minlenght' => 3,
          'maxlenght' => 3,
          'required' => TRUE,
          'form' => FALSE,
          'options' => array(
            '4' => 'MOTO (Mail Order/Telephone Order)',
            '7' => 'eCommerce',
          ),
        ),
        array(
          'name' => 'CVV',
          'type' => 'number',
          'minlenght' => 3,
          'maxlenght' => 4,
          'required' => TRUE,
          'form' => TRUE,
          'options' => array(
            '1' => 'MOTO (Mail Order/Telephone Order)',
            '2' => 'eCommerce',
          ),
        ),
        array(
          'name' => 'EMAIL',
          'type' => 'text',
          'required' => FALSE,
          'form' => TRUE,
        ),
        array(
          'name' => 'ADDRESS1',
          'type' => 'text',
          'required' => FALSE,
          'form' => TRUE,
        ),
        array(
          'name' => 'ADDRESS2',
          'type' => 'text',
          'required' => FALSE,
          'form' => TRUE,
        ),
        array(
          'name' => 'CITY',
          'type' => 'text',
          'required' => FALSE,
          'form' => TRUE,
        ),
        array(
          'name' => 'COUNTRY',
          'type' => 'text',
          'required' => FALSE,
          'form' => TRUE,
        ),
        array(
          'name' => 'POSTCODE',
          'type' => 'text',
          'required' => FALSE,
          'form' => TRUE,
        ),
        array(
          'name' => 'DESCRIPTION',
          'type' => 'text',
          'required' => FALSE,
          'form' => FALSE,
        ),
      ),
    );
  }

  public function __construct($paymentParams,$terminal,$postHash,$postDateTime){
    $this->_paymentParams = $paymentParams;
    $this->_postHash = $postHash;
    $this->_terminal = $terminal;
    $this->_postDateTime = $postDateTime;
    $this->preparePaymentXML();
  }

  public function setPaymentParams($paymentParams){
    $this->_paymentParams = $paymentParams;
  }

  public function getPaymentParams(){
    return $this->_paymentParams;
  }
  public function setXML($xml){
    $this->_xml = $xml;
  }

  public function getXML(){
    return $this->_xml;
  }

  public function setNormalizedPaymentParams($paymentParams){
    $this->_normalizedPaymentParams = $paymentParams;
  }

  public function getNormalizedPaymentParams(){
    return $this->_normalizedPaymentParams;
  }

  public function setTerminal($terminal){
    $this->_terminal = $terminal;
  }

  public function getTerminal(){
    return $this->_terminal;
  }

  public function setPostHash($hash){
    $this->_postHash = $hash;
  }
  public function getPostHash($hash){
    return $this->_postHash;
  }

  public function setDateTime($dateTime){
    $this->_dateTime = $dateTime;
  }

  public function getDateTime(){
    return $this->_dateTime;
  }

  public function setPaymentResponse($paymentResponse){
    $this->_paymentResponse = $paymentResponse;
  }

  public function getPaymentResponse(){
    return $this->_paymentResponse;
  }

  public function normalizePaymentReponse(){
    $this->_normalizedPaymentReponse = $this->XMLToArray($this->_paymentResponse);
    return $this->_normalizedPaymentReponse;
  }

  private function preparePaymentXML(){
    $xmlStructure = $this->readFields();

    $out = '<?xml version="'.$xmlStructure['XMLHeader']['version'].'" encoding="'.$xmlStructure['XMLHeader']['encoding'].'"?>';

    $out .= '<'.$xmlStructure['XMLEnclosureTag'].'>';

    $params = $this->preparePaymentParameter();

    foreach($params as $key=>$param):
      $tag = strtoupper($key);
    $out .= '<'.$tag.'>'.$param.'</'.$tag.'>';
endforeach;
$out .= '</'.$xmlStructure['XMLEnclosureTag'].'>';
$this->_xml = $out;
return $out;
  }

  private function cleanExpiryDate($month = '',$year = ''){
    if(strlen($year)>2):
      $year = substr($year, 2,2);
endif;

$date = $month.$year;

return $date;
  }

  private function cleanCardNumber($cardNumber = ''){
    $cardNumber = str_replace('-' , '', $cardNumber);
    $cardNumber = str_replace(' ' , '', $cardNumber);

    return $cardNumber;
  }

  public function getCardType($cardNumber){
    $rcardtype = $this->Pivotal_Config->getCardType($cardNumber);
    return strtoupper($rcardtype);
  }

  private function preparePaymentParameter(){
    $params = $this->_paymentParams;
    $out = array();

    $out['ORDERID'] = $params['ORDERID'];
    $out['TERMINALID'] = $this->_terminal['terminal_id'];
    $out['AMOUNT'] = $params['AMOUNT'];
    $out['DATETIME'] = $this->_postDateTime;
    $out['CARDNUMBER'] = $this->cleanCardNumber($params['CARDNUMBER']);
    $out['CARDTYPE'] = $params['CARDTYPE'];
    $out['CARDEXPIRY'] = $this->cleanExpiryDate($params['MONTH'],$params['YEAR']);
    $out['CARDHOLDERNAME'] = $params['CARDHOLDERNAME'];
    $out['HASH'] = $this->_postHash;
    $out['CURRENCY'] = $params['CURRENCY'];
    $out['TERMINALTYPE'] = 2;
    $out['TRANSACTIONTYPE'] = 7;
    $out['CVV'] = $params['CVV'];
    if (!empty($params['DESCRIPTION'])) {
      $out['DESCRIPTION'] = $params['DESCRIPTION'];
    }

    $this->_normalizedPaymentParams = $out;

    return $out;
  }

  public function XMLToArray($xml,$main_heading = '') {

    $deXml = simplexml_load_string($xml);
    $deJson = json_encode($deXml);
    $xml_array = json_decode($deJson,TRUE);

    if (! empty($main_heading)):
      $returned = $xml_array[$main_heading];
    return $returned;
    else:
    return $xml_array;
endif;

  }

}
