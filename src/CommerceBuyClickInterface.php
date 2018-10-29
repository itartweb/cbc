<?php

namespace Drupal\commerce_buy_click;

/**
 * Provides an interface defining a commerce buy click.
 */
interface CommerceBuyClickInterface {

  /**
   * Return profile customer form id.
   */
  const PROFILE_CUSTOMER_FORM_ID = 'profile_customer_commerce_buy_click_form';

  /**
   * Return profile entity type.
   */
  const ENTITY_TYPE_PROFILE = 'profile';

  /**
   * Return profile entity type customer.
   */
  const ENTITY_TYPE_PROFILE_TYPE = 'customer';

  /**
   * Return commerce store entity type.
   */
  const ENTITY_TYPE_COMMERCE_STORE = 'commerce_store';

  /**
   * Return commerce product entity type.
   */
  const ENTITY_TYPE_COMMERCE_PRODUCT = 'commerce_product';

  /**
   * Return commerce product type entity type.
   */
  const ENTITY_TYPE_COMMERCE_PRODUCT_TYPE = 'commerce_product_type';

  /**
   * Return form display mode.
   */
  const FORM_DISPLAY_MODE = 'commerce_buy_click';

  /**
   * Return view display mode.
   */
  const VIEW_DISPLAY_MODE = 'commerce_buy_click';

  /**
   * Return cbc order type.
   */
  const CBC_ORDER_TYPE = 'cbc_order_type';

  /**
   * Return cbc order item type.
   */
  const CBC_ORDER_ITEM_TYPE = 'cbc_order_item_type';

  /**
   * Return commerce order.
   */
  const COMMERCE_ORDER = 'commerce_order';

  /**
   * Return commerce order item.
   */
  const COMMERCE_ORDER_ITEM = 'commerce_order_item';

  /**
   * Return default link title.
   */
  const TITLE_LINK_DEFAULT = 'Buy one click';

  /**
   * Return default button title.
   */
  const TITLE_BUTTON_DEFAULT = 'Submit';

}
