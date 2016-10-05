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
    $this->entity->card_number = $payment_details['number'];
  }

}


