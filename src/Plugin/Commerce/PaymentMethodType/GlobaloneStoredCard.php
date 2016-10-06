<?php

namespace Drupal\commerce_globalone\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce\BundleFieldDefinition;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType\PaymentMethodTypeBase

/**
 * Provides the credit card payment method type.
 *
 * @CommercePaymentMethodType(
 *   id = "globalone_stored_card",
 *   label = @Translation("Remote credit card"),
 *   create_label = @Translation("New credit card"),
 * )
 */
class GlobaloneStoredCard extends PaymentMethodTypeBase {

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method) {
    $args = [
      '@card_number' => $payment_method->card_number->value,
    ];
    return $this->t('Card ending in @card_number', $args);
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
  
    $fields['card_number'] = BundleFieldDefinition::create('string')
      ->setLabel(t('Card number'))
      ->setDescription(t('The last few digits of the credit card number'))
      ->setRequired(TRUE);

    return $fields;
  }

}
