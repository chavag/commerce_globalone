<?php

namespace Drupal\commerce_globalone\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType\CreditCard as CreditCardBase;
use Drupal\commerce\BundleFieldDefinition;
use Drupal\commerce_payment\CreditCard as CreditCardHelper;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;

/**
 * Provides the credit card payment method type.
 *
 * @CommercePaymentMethodType(
 *   id = "extend_credit_card",
 *   label = @Translation("Extended Credit card"),
 *   create_label = @Translation("New Extended credit card"),
 * )
 */
class ExtendCreditCard extends CreditCardBase {

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method) {
    $card_type = CreditCardHelper::getType($payment_method->card_type->value);
    $args = [
      '@card_type' => $card_type->getLabel(),
      '@card_number' => $payment_method->card_number->value,
    ];
    return $this->t('@card_type @card_number', $args);
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = parent::buildFieldDefinitions();

    $fields['card_owner'] = BundleFieldDefinition::create('string')
      ->setLabel(t('Card Owner'))
      ->setDescription(t('The credit card owner tz.'))
      ->setRequired(TRUE);

    $fields['card_cvv'] = BundleFieldDefinition::create('string')
      ->setLabel(t('Card Cvv'))
      ->setDescription(t('The credit card Cvv number'))
      ->setRequired(TRUE);  

    return $fields;
  }

}
