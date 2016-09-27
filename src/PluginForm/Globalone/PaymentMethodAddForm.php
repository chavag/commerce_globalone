<?php

namespace Drupal\commerce_globalone\PluginForm\Globalone;

use Drupal\commerce_payment\PluginForm\PaymentMethodAddForm as BasePaymentMethodAddForm;
use Drupal\Core\Form\FormStateInterface;

class PaymentMethodAddForm extends BasePaymentMethodAddForm {

  /**
   * {@inheritdoc}
   */
  protected function buildCreditCardForm(array $element, FormStateInterface $form_state) {
    $element = parent::buildCreditCardForm($element, $form_state);
    // Default to a known valid test credit card number.
    $element['number']['#default_value'] = '4444333322221111';

	  $element['owner'] = [
      '#type' => 'textfield',
      '#title' => t('Owner'),
      '#attributes' => ['autocomplete' => 'off'],
      '#required' => TRUE,
      '#maxlength' => 12,
      '#size' => 20,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function submitCreditCardForm(array $element, FormStateInterface $form_state) {
    parent::submitCreditCardForm($element, $form_state);

    $values = $form_state->getValue($element['#parents']);
    $this->entity->card_owner = $values['owner'];
    $this->entity->card_cvv = $values['security_code'];
  }

  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $payment_method = $this->entity;

    $form['#attached']['library'][] = 'commerce_payment/payment_method_form';
    $form['#tree'] = TRUE;
    $form['payment_details'] = [
      '#parents' => array_merge($form['#parents'], ['payment_details']),
      '#type' => 'container',
      '#payment_method_type' => $payment_method->bundle(),
    ];
    if ($payment_method->bundle() == 'credit_card' || $payment_method->bundle() == 'extend_credit_card') {
      $form['payment_details'] = $this->buildCreditCardForm($form['payment_details'], $form_state);
    }

    elseif ($payment_method->bundle() == 'paypal') {
      $form['payment_details'] = $this->buildPayPalForm($form['payment_details'], $form_state);
    }

    $form['billing_information'] = [
      '#parents' => array_merge($form['#parents'], ['billing_information']),
      '#type' => 'container',
    ];
    $form['billing_information'] = $this->buildBillingProfileForm($form['billing_information'], $form_state);

    return $form; 
  }

   /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $this->entity;

    if ($payment_method->bundle() == 'extend_credit_card') {
      parent::validateCreditCardForm($form['payment_details'], $form_state);
    }
  }

  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $this->entity;

    if ($payment_method->bundle() == 'credit_card' || $payment_method->bundle() == 'extend_credit_card') {
      $this->submitCreditCardForm($form['payment_details'], $form_state);
    }
    elseif ($payment_method->bundle() == 'paypal') {
      $this->submitPayPalForm($form['payment_details'], $form_state);
    }
    $this->submitBillingProfileForm($form['billing_information'], $form_state);

    $values = $form_state->getValue($form['#parents']);
    /** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsStoredPaymentMethodsInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $this->plugin;
    // The payment method form is customer facing. For security reasons
    // the returned errors need to be more generic.
    try {
      $payment_gateway_plugin->createPaymentMethod($payment_method, $values['payment_details']);
    }
    catch (DeclineException $e) {
      \Drupal::logger('commerce_payment')->warning($e->getMessage());
      throw new DeclineException('We encountered an error processing your payment method. Please verify your details and try again.');
    }
    catch (PaymentGatewayException $e) {
      \Drupal::logger('commerce_payment')->error($e->getMessage());
      throw new PaymentGatewayException('We encountered an unexpected error processing your payment method. Please try again later.');
    }
  }

}


