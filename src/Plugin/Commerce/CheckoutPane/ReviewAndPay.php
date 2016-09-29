<?php

namespace Drupal\commerce_globalone\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\Review;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;

/**
 * Provides the review pane.
 *
 * @CommerceCheckoutPane(
 *   id = "review_and_pay",
 *   label = @Translation("Review And Pay"),
 *   default_step = "review",
 * )
 */
class ReviewAndPay extends Review {

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    
    $payment_methods = $this->order->payment_method->referencedEntities();
    $payment_method = array_shift($payment_methods);

    $payment_gateways = $this->order->payment_gateway->referencedEntities();
    $payment_gateway = array_shift($payment_gateways);

    $payment_storage = \Drupal::entityTypeManager()->getStorage('commerce_payment');
    $payment = $payment_storage->create([
      'type' => 'payment_default',
      'payment_gateway' => $payment_gateway->id(),
      'payment_method' => $payment_method->id(),
      'uid' => $this->order->getOwnerId(),
      'order_id' => $this->order->id(),
      'amount' => $this->order->getTotalPrice(),
      'state' => 'authorization',
    ]);

    $payment_gateway->getPlugin()->capturePayment($payment);

    $messages = drupal_get_messages('error');

    if(isset($messages['error'])) {
      $form_state->setError($pane_form['payment_information'], implode($messages['error'], ',  ')); 
      $payment->delete();
    } else {
      $this->order->payment = $payment->id();
    }

  }

}
