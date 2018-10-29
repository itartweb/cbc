<?php

namespace Drupal\commerce_buy_click\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CommerceBuyClickController.
 */
class CommerceBuyClickController extends ControllerBase {

  /**
   * The product service.
   *
   * @var \Drupal\commerce_buy_click\CommerceBuyClickProductService
   */
  protected $productService;

  /**
   * The product service.
   *
   * @var \Drupal\commerce_buy_click\CommerceBuyClickFormService
   */
  protected $formService;

  /**
   * Content Locker Service instance.
   *
   * @param object $product_service
   *   The product service.
   * @param object $form_service
   *   The form service.
   */
  public function __construct($product_service, $form_service) {
    $this->productService = $product_service;
    $this->formService = $form_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_buy_click.service'),
      $container->get('commerce_buy_click_form.service')
    );
  }

  /**
   * Returns a page title.
   */
  public function getTitle() {
    return $this->productService->getTitleProduct();
  }

  /**
   * Building profile form of commerce buy click mode view or default.
   */
  public function buildProfileForm() {
    return $this->formService->getEntityProfileFormDefault();
  }

  /**
   * Form submission handler for the commerce_buy_click form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Returns renderable array.
   */
  public static function ajaxSubmitCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $values = $form_state->getValues();

    // Set empty message by default.
    $response->addCommand(new HtmlCommand('.validation-message', ''));

    if ($form_state->hasAnyErrors()) {
      $errors = $form_state->getErrors();
      $messages = '';
      foreach ($errors as $message) {
        $messages .= '<div class="messages error">' . $message . '</div>';
      }
      return $response->addCommand(new HtmlCommand('.validation-message', $messages));
    }
    else {
      $profile_type = isset($values['type']) ? $values['type'] : NULL;
      $product_id = isset($values['product_id']) ? $values['product_id'] : NULL;
      $variation = isset($values['variation']) ? $values['variation'] : 0;

      // Create Profile and order.
      if ($profile_type && $product_id && $variation) {
        $cbc_model = \Drupal::service('commerce_buy_click.service');
        $profile = $cbc_model->createProfile($values);

        if ($profile) {
          $order_item = $cbc_model->createOrderItem($values);

          if ($order_item) {
            $oder_item_ids = [$order_item->id()];
            $order = $cbc_model->createOrder($profile, $oder_item_ids);

            if ($order) {
              $config = \Drupal::config('commerce_buy_click.settings');
              $message = !empty($config->get('default.message')) ? $config->get('default.message') : t('Thank you!');
              $messenger = \Drupal::messenger();
              // Delete all messages.
              $messenger->deleteAll();
              if (isset($message)) {
                $messenger->addMessage($message);
              }
            }
          }
        }
      }
      return $response->addCommand(new RedirectCommand($values['redirect']));
    }
  }

}
