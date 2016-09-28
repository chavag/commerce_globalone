<?php

namespace Drupal\commerce_globalone\PluginForm\Globalone;

use Drupal\commerce_payment\PluginForm\PaymentCaptureForm as BasePaymentCaptureForm;
use Drupal\Core\Form\FormStateInterface;

class PaymentCaptureForm extends BasePaymentCaptureForm {
  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    /** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsAuthorizationsInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $this->plugin;
    $success = $payment_gateway_plugin->capturePayment($payment, $form['amount']['#value']);
    if ($success) {
      $form['#success_message'] = t('Payment captured.');
    } else {
      $form['#success_message'] = t('Payment captured Failed.');
    }
  }

}
