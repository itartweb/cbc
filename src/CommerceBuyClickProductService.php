<?php

namespace Drupal\commerce_buy_click;

use Drupal\commerce_product\Entity\Product;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Class CommerceBuyClickProduct.
 */
class CommerceBuyClickProductService {

  /**
   * The current object product.
   *
   * @var \Drupal\commerce_product\Entity\Product
   */
  protected $product;

  /**
   * The current object product bundle.
   *
   * @var \Drupal\commerce_product\Entity\Product
   */
  protected $productBundle;

  /**
   * The current product id.
   *
   * @var \Drupal\commerce_product\Entity\Product
   */
  protected $productId;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Content Locker Service instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * Constructs a new CommerceBuyClickProduct object.
   *
   * @param \Drupal\commerce_product\Entity\Product $product
   *   The current state from the form.
   */
  public function setProduct(Product $product) {
    $this->product = $product;
    $this->productBundle = $product->bundle();
    $this->productId = $product->id();
  }

  /**
   * Checks current product.
   *
   * @return bool
   *   Returns TRUE if the product, or FALSE otherwise.
   */
  public function hasProduct() {
    if ($this->product) {
      return TRUE;
    }
    return FALSE;
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
   * Checks current product in store.
   *
   * @return bool
   *   Returns TRUE if the product in store, or FALSE otherwise.
   */
  public function hasProductInStore() {
    $config = $this->getConfig();
    $storeIds = !empty($config->get('default.stores')) ? $config->get('default.stores') : [];
    if (array_intersect($storeIds, $this->product->getStoreIds())) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Returns the current product.
   *
   * @return object
   *   Returns the product object.
   */
  public function getProduct() {
    return $this->product;
  }

  /**
   * Returns the bundle of current product.
   *
   * @return string
   *   Returns the product bundle.
   */
  public function getProductBundle() {
    return $this->productBundle;
  }

  /**
   * Returns the id of current product.
   *
   * @return int
   *   Returns the product id.
   */
  public function getProductId() {
    return (int) $this->productId;
  }

}
