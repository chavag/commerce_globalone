<?php

namespace Drupal\commerce_globalone\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_globalone\Integrations\GlobalonePost;
use Drupal\commerce_globalone\Integrations\GlobalonePostPayment;
use Drupal\commerce_globalone\Integrations\GlobalonePostPaymentMethod;
use Drupal\commerce_payment\CreditCard;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\Exception\InvalidRequestException;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\PaymentGatewayBase;
use Drupal\commerce_price\Price;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html;

/**
 * Provides the Onsite payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "globalone",
 *   label = "Globalone",
 *   display_label = "Globalone",
 *    forms = {
 *     "add-payment-method" = "Drupal\commerce_globalone\PluginForm\Globalone\PaymentMethodAddForm",
 *     "capture-payment" = "Drupal\commerce_globalone\PluginForm\Globalone\PaymentCaptureForm"
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "amex", "dinersclub", "discover", "jcb", "maestro", "mastercard", "visa",
 *   },
 * )
 */

class Globalone extends PaymentGatewayBase implements GlobaloneInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager);

    // You can create an instance of the SDK here and assign it to $this->api.
    // Or inject Guzzle when there's no suitable SDK.
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {

    return [
      'terminal' => [
        'terminal_path' => 'estore/terminal',
      ],
      'live' => [
        'currency' => '',
        'terminal_id' => '',
        'secret' => '',
      ],
      'test' => [
        'terminal_id' => '33001',
      ],
      'card_types' => [],
      'log' => ['request' => '0', 'response' => '0'],
    ] + parent::defaultConfiguration();

  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['test'] = [
      '#type' => 'container',
      '#title' => $this->t('test cards'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#states' => [
        'visible' => [
          ':input[id="edit-configuration-mode-test"]' => array('checked' => TRUE),
        ],
      ],
    ];

    $form['test']['cards_info'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Test cards numbers'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    $form['test']['cards_info']['cards_no'] = [
      '#theme' => 'item_list',
      '#items' => [
        '<b>Visa</b>  4444333322221111',
        '<b>MasterCard</b> 5404000000000001',
        '<b>Visa Debit</b> 4462000000000003',
        '<b>Debit MasterCard</b> 5573470089010012',
        '<b>American Express</b> 374200000000004',
        '<b>JCB</b> 3569990000000009',
        '<b>Diners</b> 36000000000008',
      ],
      '#type' => 'ol',
      '#suffix' => $this->t('CVV is 3 for all but amex is 4'),
    ];

    $form['test']['terminal_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Test mode currency'),
      '#default_value' => $this->configuration['test']['terminal_id'],
      '#options' => [
        '33001' => $this->t('USD'), 
        '33002' => $this->t('CAD'), 
        '33003' => $this->t('EUR'), 
        '33004' => $this->t('GBP'), 
        '36001' => $this->t('Multi Currency'), 
      ],
    ];

    $form['live'] = [
      '#type' => 'container',
      '#title' => $this->t('Live terminal info'),
      '#states' => [
        'visible' => [
          ':input[id="edit-configuration-mode-live"]' => array('checked' => TRUE),
        ],
      ],
    ];

    // Build a currency options list from all enabled currencies.
    $options = array();

    /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $currency_storage */
    $currency_storage = \Drupal::service('entity_type.manager')->getStorage('commerce_currency');

    /** @var \Drupal\commerce_price\Entity\CurrencyInterface[] $currencies */
    $currencies = $currency_storage->loadMultiple();

    foreach ($currencies as $currency_code => $currency) {
      $options[$currency_code] = $currency_code;
    }

    $form['live']['currency'] = [
      '#type' => 'select',
      '#options' => $options,
      '#title' => $this->t('Currency'),
      '#default_value' => $this->configuration['live']['currency'],
    ];

    $form['live']['terminal_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Terminal ID'),
      '#default_value' => $this->configuration['live']['terminal_id'],
    ];

    $form['live']['secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret'),
      '#default_value' => $this->configuration['live']['secret'],
    ];

    /** @var \Drupal\commerce_payment\CreditCardType[] $card_types */

    $card_types = CreditCard::getTypes();
    $options = [];
    
    foreach ($card_types as $id => $card_type) {
      $options[$id] = $card_type->getLabel();
    }

    $form['card_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Limit accepted credit cards to the following types'),
      '#description' => $this->t('If you want to limit acceptable card types, you should only select those supported by your merchant account.') . '<br />' .$this->t('If none are checked, any credit card type will be accepted.'),
      '#options' => $options,
      '#default_value' => $this->configuration['card_types'],
    ];

    // if (module_exists('commerce_globalone_terminal')) {
    //   $form['terminal'] = [
    //     '#type' => 'fieldset',
    //     '#title' => $this->t('GlobalONE terminal Payment'),
    //     '#collapsible' => TRUE,
    //     '#collapsed' => FALSE,
    //   ];
    //   $form['terminal']['terminal_path'] = [
    //     '#type' => 'textfield',
    //     '#title' => $this->t('Terminal path'),
    //     '#default_value' => $this->configuration['terminal']['terminal_path'],
    //     '#required' => TRUE,
    //   ];
    // }

    $form['log'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Log the following messages for debugging'),
      '#options' => [
        'request' => $this->t('API request messages'),
        'response' => $this->t('API response messages'),
      ],
      '#default_value' => $this->configuration['log'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);

      foreach ($values as $key => $value) {
        $this->configuration[$key] = $value;  
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createPayment(PaymentInterface $payment, $capture = TRUE) {

    if ($payment->getState()->value != 'new') {
      throw new \InvalidArgumentException(' the provided payment is in an invalid state.');
    }

    $payment_method = $payment->getPaymentMethod();

    if (empty($payment_method)) {
      throw new \InvalidArgumentException(' the provided payment has no payment method referenced.');
    }
    if (REQUEST_TIME >= $payment_method->getExpiresTime()) {
      throw new HardDeclineException(' the provided payment method has expired');
    }

    $test = $this->getMode() == 'test';
    $payment->setTest($test);
    $payment->state = 'authorization';

    if ($capture) {
      return $this->capturePayment($payment);
    } 

    $payment->setAuthorizedTime(REQUEST_TIME);
    $payment->save();
  }

  public function globalonePostParams(GlobalonePost $globalone_post) {
    $settings = $this->getConfiguration();
      
    if ($settings['log']['response']) {
      $globalone_post->logResponse();
    }
    if ($settings['log']['request']) {
      $globalone_post->logRequest();
    }

    $response = $globalone_post->sendRequest(); 

    return $globalone_post->handleResponse();
  }

  /**
 * Return live terminals.
 *
 * @param $live_terminal
 *  The terminal id to map details for.
 *
 * @return
 *  Array with 'url', currency
 *
 *
 */
  public function getLiveTerminalInfo($live_terminal) {
    $terminal = array(
      'url' => \Drupal::config('commerce_globalone.settings')->get('globalone_live_url'),
      'currency' => $live_terminal['currency'],
      'terminal_id' => $live_terminal['terminal_id'],
      'secret' => $live_terminal['secret'],
      'mode' => 'live',
    );
    return $terminal;
  }

/**
 * Return test terminals.
 *
 * @param $terminal_id
 *  The terminal id to get details for.
 *
 * @return
 *  Array with 'url', currency
 *
 *
 */
  public function getTestTerminalInfo($terminal_id) {
    $test_url = \Drupal::config('commerce_globalone.settings')->get('globalone_test_url');

    $test_terminals = array(
      '33001' => array(
        'url' => $test_url,
        'terminal_id' => '33001',
        'currency' => 'USD',
        'secret' => 'SandboxSecret001',
        'mode' => 'test',
      ),
      '33002' => array(
        'url' => $test_url,
        'terminal_id' => '33002',
        'currency' => 'CAD',
        'secret' => 'SandboxSecret002',
        'mode' => 'test',
      ),
      '33003' => array(
        'url' => $test_url,
        'terminal_id' => '33003',
        'currency' => 'EUR',
        'secret' => 'SandboxSecret003',
        'mode' => 'test',
      ),
      '33004' => array(
        'url' => $test_url,
        'terminal_id' => '33004',
        'currency' => 'GBP',
        'secret' => 'SandboxSecret004',
        'mode' => 'test',
      ),
      '36001' => array(
        'url' => $test_url,
        'terminal_id' => '36001',
        'currency' => 'MCP',
        'secret' => 'SandboxSecret001',
        'mode' => 'test',
      ),
    );
    return $test_terminals[$terminal_id];
  }

  public function getTerminal() {
    $settings = $this->getConfiguration();
    return $this->getMode() == 'live' ?  $this->getLiveTerminalInfo($settings['live']) :
     $this->getTestTerminalInfo($settings['test']['terminal_id']);
  }

  /**
   * {@inheritdoc}
   */
  public function capturePayment(PaymentInterface $payment, Price $amount = NULL) {

    if ($payment->getState()->value != 'authorization') {
      throw new \InvalidArgumentException('Only payments in  the "authorization" state can be captured.');
    }

    $globalone_post = new globalonePostPayment($this->getTerminal(), $payment); 

    if ($this->globalonePostParams($globalone_post)) {
      $payment = $globalone_post->getPayment();
      $payment->setCapturedTime(REQUEST_TIME);
      $payment->state = 'capture_completed';
      $payment->save();
    } else {
      $this->voidPayment($payment);
    } 

  }

  /**
   * {@inheritdoc}
   */
  public function voidPayment(PaymentInterface $payment) {
    if ($payment->getState()->value != 'authorization') {
     throw new \InvalidArgumentException('Only payments in the "authorization" state can be voided.');
    }

    $payment->state = 'authorization_voided';
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function refundPayment(PaymentInterface $payment, Price $amount = NULL) {
    if (!in_array($payment->getState()->value, ['capture_completed', 'capture_partially_refunded'])) {
     throw new \InvalidArgumentException('Only payments in the "capture_completed" and "capture_partially_refunded" states can be refunded.');
    }
    // If not specified, refund the entire amount.
    $amount = $amount ?: $payment->getAmount();
    // Validate the requested amount.
    $balance = $payment->getBalance();
    if ($amount->greaterThan($balance)) {
     throw new InvalidRequestException(sprintf("Can't refund more than %s.", $balance->__toString()));
    }

    // Perform the refund request here,throw an exception if it fails.
    // See \Drupal\commerce_payment\Exception for the available exceptions.
    $remote_id = $payment->getRemoteId();
    $decimal_amount = $amount->getDecimalAmount();

    $old_refunded_amount = $payment->getRefundedAmount();
    $new_refunded_amount = $old_refunded_amount->add($amount);
    if ($new_refunded_amount->lessThan($payment->getAmount())) {
      $payment->state = 'capture_partially_refunded';
    }
    else {
      $payment->state = 'capture_refunded';
    }

    $payment->setRefundedAmount($new_refunded_amount);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function createPaymentMethod(PaymentMethodInterface $payment_method, array $payment_details) {
    $required_keys = [
      // the expected keys are payment gateway specific and usually match
      // the PaymentMethodAddForm form elements. they are expected to be valid.
      'type', 'number', 'expiration',
    ];

    foreach ($required_keys as $required_key) {
      if (empty($payment_details[$required_key])) {
       throw new \InvalidArgumentException(sprintf('$payment_details must contain the %s key.', $required_key));
      }
    }

    // Perform the create request here,throw an exception if it fails.
    // See \Drupal\commerce_payment\Exception for the available exceptions.
    // You might need to do different API requests based on whe ther the
    // payment method is reusable: $payment_method->isReusable().
    // Non-reusable payment methods usually have an expiration timestamp.
    $payment_method->card_type = $payment_details['type'];
    $payment_method->card_number = $payment_details['number'];
    $payment_method->card_owner = $payment_details['owner'];
    $payment_method->card_cvv = $payment_details['security_code'];
    $payment_method->card_exp_month = $payment_details['expiration']['month'];
    $payment_method->card_exp_year = $payment_details['expiration']['year'];
    $expires = CreditCard::calculateExpirationTimestamp($payment_details['expiration']['month'], $payment_details['expiration']['year']);
    
    $globalone_post = new GlobalonePostPaymentMethod($this->getTerminal(), $payment_method, 'Create');

    $success = $this->globalonePostParams($globalone_post);

    if ($success) {
      $payment_method = $globalone_post->getPaymentMethod();
      $payment_method->setExpiresTime($expires);
      // Only the last 4 numbers are safe to store.
      $payment_method->card_number = substr($payment_details['number'], -4);
      $payment_method->save();     
    }
    
  }

  /**
   * {@inheritdoc}
   */
  public function deletePaymentMethod(PaymentMethodInterface $payment_method) {
    // Delete the remote record here,throw an exception if it fails.
    // See \Drupal\commerce_payment\Exception for the available exceptions.
    // Delete the local entity.
    $payment_method->delete();
  }

  /**
   * {@inheritdoc}
   */
  public function getPaymentMethodTypes() {
    // Filter out payment method types disabled by the merchant.
    return array_intersect_key($this->paymentMethodTypes, $this->configuration['payment_method_types']);
  }

}
