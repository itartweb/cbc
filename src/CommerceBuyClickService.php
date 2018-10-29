<?php

namespace Drupal\commerce_buy_click;

use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Class CommerceBuyClickModel.
 */
class CommerceBuyClickService {

  use StringTranslationTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The request.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * The request.
   *
   * @var \Drupal\commerce_store\CurrentStoreInterface
   */
  protected $currentStore;

  /**
   * The request.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Content Locker Service instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   *   The request.
   * @param \Drupal\commerce_store\CurrentStoreInterface $current_store
   *   The current store.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(ConfigFactoryInterface $config_factory,
                              EntityTypeManagerInterface $entity_type_manager,
                              RequestStack $request,
                              CurrentStoreInterface $current_store,
                              MessengerInterface $messenger
  ) {

    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->request = $request;
    $this->currentStore = $current_store;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity.manager'),
      $container->get('request_stack'),
      $container->get('commerce_store.current_store'),
      $container->get('messenger')
    );
  }

  /**
   * Returns a page title of the product.
   */
  public function getTitleProduct() {
    $title = $this->t('Commerce Buy Click');
    $args = commerce_buy_click_get_arg();

    if ($args[1] == 'commerce_buy_click' & isset($args[2])) {
      $productId = intval($args[2]);
      $product = Product::load($productId);
      if ($product) {
        return $this->t('Buy: %label', ['%label' => $product->label()]);
      }
    }
    return $title;
  }

  /**
   * Gets the configuration object when needed.
   *
   * @return object
   *   The config object.
   */
  protected function getConfig() {
    return $this->configFactory->get('commerce_buy_click.settings');
  }

  /**
   * Return order state default for the commerce buy click module.
   */
  protected function getOrderStateDefault() {
    $config = $this->getConfig();
    $default_state = !empty($config->get('default.state')) ? $config->get('default.state') : 'draft';
    return $default_state;
  }

  /**
   * Creating profile.
   *
   * @param array $values
   *   The values.
   *
   * @return mixed
   *   Returns profile or NULL.
   */
  public function createProfile(array $values) {
    if (isset($values['address'][0]['address'])) {
      $values['address'] = $values['address'][0]['address'];
      unset($values['address'][0]['address']);
    }
    try {
      $profile = $this->entityTypeManager->getStorage(CommerceBuyClickInterface::ENTITY_TYPE_PROFILE)->create($values);
      $profile->save();
      return $profile;
    }
    catch (\Exception $e) {
      $this->messenger->addMessage($e->getMessage(), 'error');
      return NULL;
    }
  }

  /**
   * Creating order item.
   *
   * @param array $values
   *   The values.
   *
   * @return mixed
   *   Returns order item object or NULL.
   */
  public function createOrderItem(array $values) {
    $variation = ProductVariation::load($values['variation']);
    $quantity = (isset($values['quantity']) && $values['quantity'] > 0) ? $values['quantity'] : 1;

    try {
      $order_item = $this->entityTypeManager
        ->getStorage(CommerceBuyClickInterface::COMMERCE_ORDER_ITEM)
        ->create([
          'type' => CommerceBuyClickInterface::CBC_ORDER_ITEM_TYPE,
          'purchased_entity' => $variation,
          'quantity' => $quantity,
          'unit_price' => $variation->getPrice(),
        ]);
      $order_item->save();
    }
    catch (\Exception $e) {
      $this->messenger->addMessage($e->getMessage(), 'error');
      return NULL;
    }

    return $order_item;
  }

  /**
   * Creating order.
   *
   * @param object $profile
   *   The customer profile.
   * @param array $oder_item_ids
   *   The order item ids.
   *
   * @return mixed
   *   Returns order id or NULL.
   */
  public function createOrder($profile, array $oder_item_ids) {
    $account = $profile->getOwner();
    $uid = $account->id();
    $email = $account->getEmail();
    $ip_address = $this->request->getCurrentRequest()->getClientIp();
    $current_store_id = $this->currentStore->getStore()->id();

    try {
      $order = $this->entityTypeManager
        ->getStorage(CommerceBuyClickInterface::COMMERCE_ORDER)
        ->create([
          'type' => CommerceBuyClickInterface::CBC_ORDER_TYPE,
          'state' => $this->getOrderStateDefault(),
          'mail' => $email,
          'uid' => $uid,
          'ip_address' => $ip_address,
          'billing_profile' => $profile,
          'store_id' => $current_store_id,
          'order_items' => $oder_item_ids,
          'placed' => time(),
        ]);
      $order->save();
    }
    catch (\Exception $e) {
      $this->messenger->addMessage($e->getMessage(), 'error');
      return NULL;
    }

    return $order;
  }

}
