<?php

namespace Drupal\commerce_buy_click;

use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_product\Entity\Product;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Component\Utility\Html;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_product\Event\ProductVariationAjaxChangeEvent;
use Drupal\commerce_product\Event\ProductEvents;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_product\ProductAttributeFieldManagerInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Class CommerceBuyClickModel.
 */
class CommerceBuyClickFormService {

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
   * The attribute field manager.
   *
   * @var \Drupal\commerce_product\ProductAttributeFieldManagerInterface
   */
  protected $attributeFieldManager;

  /**
   * The entity form builder.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected $entityFormBuilder;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Content Locker Service instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_product\ProductAttributeFieldManagerInterface $attribute_field_manager
   *   The attribute field manager.
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entity_form_builder
   *   The entity form builder.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(ConfigFactoryInterface $config_factory,
                              EntityTypeManagerInterface $entity_type_manager,
                              ProductAttributeFieldManagerInterface $attribute_field_manager,
                              EntityFormBuilderInterface $entity_form_builder,
                              AccountProxyInterface $current_user
  ) {

    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->attributeFieldManager = $attribute_field_manager;
    $this->entityFormBuilder = $entity_form_builder;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity.manager'),
      $container->get('commerce_product.attribute_field_manager'),
      $container->get('entity.form_builder'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityProfileFormDefault() {
    $customer = $this->entityTypeManager
      ->getStorage(CommerceBuyClickInterface::ENTITY_TYPE_PROFILE)
      ->create([
        'type' => CommerceBuyClickInterface::ENTITY_TYPE_PROFILE_TYPE,
        'uid'  => $this->getCurrentUserId(),
      ]);

    return $this->entityFormBuilder->getForm($customer, CommerceBuyClickInterface::FORM_DISPLAY_MODE);
  }

  /**
   * Returns the renderer commerce price plain.
   *
   * @param float $number
   *   The number of price.
   * @param string $currency_code
   *   The currency code.
   *
   * @return array
   *   Returns renderable array.
   */
  protected function buildRendererCommercePrice($number, $currency_code) {
    return [
      '#theme' => 'commerce_price_plain',
      '#number' => $number,
      '#currency' => $currency_code,
      '#cache' => [
        'contexts' => [
          'languages:' . LanguageInterface::TYPE_INTERFACE,
          'country',
        ],
      ],
    ];
  }

  /**
   * Returns the output code.
   *
   * @param \Drupal\commerce_product\Entity\Product $product
   *   The product object.
   *
   * @return array
   *   Returns renderable array.
   */
  protected function rendererPrices(Product $product) {
    $build = [];
    $variations = $product->getVariations();
    if ($variations) {
      foreach ($variations as $variation) {
        $prices_tmp[] = round($variation->getPrice()->getNumber(), 2);
        $currency_code = $variation->getPrice()->getCurrencyCode();
      }
      $min_number = min($prices_tmp);
      $max_number = max($prices_tmp);

      if ($min_number !== $max_number) {
        $build['prices'] = [
          '#theme' => 'commerce_buy_click_prices',
          '#min_number' => render($this->buildRendererCommercePrice($min_number, $currency_code)),
          '#max_number' => render($this->buildRendererCommercePrice($max_number, $currency_code)),
          '#currency' => $currency_code,
          '#label' => $this->t('Prices:'),
          '#cache' => [
            'contexts' => [
              'languages:' . LanguageInterface::TYPE_INTERFACE,
              'country',
            ],
          ],
        ];
      }
      else {
        $build['price'] = [
          '#theme' => 'commerce_buy_click_price',
          '#number' => render($this->buildRendererCommercePrice($min_number, $currency_code)),
          '#currency' => $currency_code,
          '#label' => $this->t('Price:'),
          '#cache' => [
            'contexts' => [
              'languages:' . LanguageInterface::TYPE_INTERFACE,
              'country',
            ],
          ],
        ];
      }
    }

    return $build;
  }

  /**
   * Returns the output product view.
   *
   * @param \Drupal\commerce_product\Entity\Product $product
   *   The product object.
   *
   * @return array
   *   Returns renderable array.
   */
  protected function rendererProduct(Product $product) {
    $product_view_builder = $this->entityTypeManager->getViewBuilder(CommerceBuyClickInterface::ENTITY_TYPE_COMMERCE_PRODUCT);
    $build = $product_view_builder->view($product, CommerceBuyClickInterface::VIEW_DISPLAY_MODE);
    return $build;
  }

  /**
   * Returns the id of current user.
   *
   * @return int
   *   Returns user id.
   */
  protected function getCurrentUserId() {
    return $this->currentUser->id();
  }

  /**
   * {@inheritdoc}
   */
  protected function variationStorage() {
    return $this->entityTypeManager->getStorage('commerce_product_variation');
  }

  /**
   * {@inheritdoc}
   */
  protected function attributeStorage() {
    return $this->entityTypeManager->getStorage('commerce_product_attribute');
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
   * Gets the enabled variations for the product.
   *
   * @param \Drupal\commerce_product\Entity\ProductInterface $product
   *   The product.
   *
   * @return \Drupal\commerce_product\Entity\ProductVariationInterface[]
   *   An array of variations.
   */
  protected function loadEnabledVariations(ProductInterface $product) {
    $variations = $this->variationStorage()->loadEnabled($product);
    return $variations;
  }

  /**
   * Gets the attribute information for the selected product variation.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface $selected_variation
   *   The selected product variation.
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface[] $variations
   *   The available product variations.
   *
   * @return array[]
   *   The attribute information, keyed by field name.
   */
  protected function getAttributeInfo(ProductVariationInterface $selected_variation, array $variations) {
    $attributes = [];
    $field_definitions = $this->attributeFieldManager->getFieldDefinitions($selected_variation->bundle());
    $field_map = $this->attributeFieldManager->getFieldMap($selected_variation->bundle());
    $field_names = array_column($field_map, 'field_name');
    $attribute_ids = array_column($field_map, 'attribute_id');
    $index = 0;
    foreach ($field_names as $field_name) {
      $field = $field_definitions[$field_name];
      /** @var \Drupal\commerce_product\Entity\ProductAttributeInterface $attribute */
      $attribute = $this->attributeStorage()->load($attribute_ids[$index]);

      $attributes[$field_name] = [
        'field_name' => $field_name,
        'title' => $attribute->label(),
        'required' => $field->isRequired(),
        'element_type' => $attribute->getElementType(),
      ];
      // The first attribute gets all values. Every next attribute gets only
      // the values from variations matching the previous attribute value.
      // For 'Color' and 'Size' attributes that means getting the colors of all
      // variations, but only the sizes of variations with the selected color.
      $callback = NULL;
      if ($index > 0) {
        $previous_field_name = $field_names[$index - 1];
        $previous_field_value = $selected_variation->getAttributeValueId($previous_field_name);
        $callback = function ($variation) use ($previous_field_name, $previous_field_value) {
          /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $variation */
          return $variation->getAttributeValueId($previous_field_name) == $previous_field_value;
        };
      }

      $attributes[$field_name]['values'] = $this->getAttributeValues($variations, $field_name, $callback);
      $index++;
    }
    // Filter out attributes with no values.
    $attributes = array_filter($attributes, function ($attribute) {
      return !empty($attribute['values']);
    });

    return $attributes;
  }

  /**
   * Gets the attribute values of a given set of variations.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface[] $variations
   *   The variations.
   * @param string $field_name
   *   The field name of the attribute.
   * @param callable|null $callback
   *   An optional callback to use for filtering the list.
   *
   * @return array[]
   *   The attribute values, keyed by attribute ID.
   */
  protected function getAttributeValues(array $variations, $field_name, callable $callback = NULL) {
    $values = [];
    foreach ($variations as $variation) {
      if (is_null($callback) || call_user_func($callback, $variation)) {
        $attribute_value = $variation->getAttributeValue($field_name);
        if ($attribute_value) {
          $values[$attribute_value->id()] = $attribute_value->label();
        }
        else {
          $values['_none'] = '';
        }
      }
    }

    return $values;
  }

  /**
   * Selects a product variation from user input.
   *
   * If there's no user input (form viewed for the first time), the default
   * variation is returned.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface[] $variations
   *   An array of product variations.
   * @param array $user_input
   *   The user input.
   *
   * @return \Drupal\commerce_product\Entity\ProductVariationInterface
   *   The selected variation.
   */
  protected function selectVariationFromUserInput(array $variations, array $user_input) {
    $current_variation = reset($variations);
    if (!empty($user_input['attributes'])) {
      $attributes = $user_input['attributes'];
      foreach ($variations as $variation) {
        $match = TRUE;
        foreach ($attributes as $field_name => $value) {
          if ($variation->getAttributeValueId($field_name) != $value) {
            $match = FALSE;
          }
        }
        if ($match) {
          $current_variation = $variation;
          break;
        }
      }
    }

    return $current_variation;
  }

  /**
   * {@inheritdoc}
   */
  protected function getAdditionalVariation(array &$form, Product $product, FormStateInterface $form_state) {
    $variations = $this->loadEnabledVariations($product);
    if (count($variations) === 0) {
      // Nothing to purchase, tell the parent form to hide itself.
      $form_state->set('hide_form', TRUE);
      $element['variation'] = [
        '#type' => 'hidden',
        '#value' => 0,
      ];
      return $element;
    }
    elseif (count($variations) === 1) {
      /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $selected_variation */
      $selected_variation = reset($variations);
      $element['variation'] = [
        '#type' => 'hidden',
        '#value' => $selected_variation->id(),
      ];
      return $element;
    }
    else {
      $config = $this->getConfig();
      $display_variation = !empty($config->get('default.display_variation')) ? $config->get('default.display_variation') : 0;
      if (!$display_variation) {
        $selected_variation = reset($variations);
        $element['variation'] = [
          '#type' => 'hidden',
          '#value' => $selected_variation->id(),
        ];
        return $element;
      }
    }

    // If an operation caused the form to rebuild, select the variation from
    // the user's current input.
    if ($form_state->isRebuilding()) {
      $user_input = (array) $form_state->getUserInput();
      if ($user_input) {
        foreach ($user_input as $field_name => $value) {
          if (strpos($field_name, 'attribute_') === FALSE) {
            unset($user_input[$field_name]);
          }
        }
      }
      $selected_variation = $this->selectVariationFromUserInput($variations, ['attributes' => $user_input]);
    }
    // Otherwise load from the current context.
    else {
      $selected_variation = reset($variations);
    }

    // Build the full attribute form.
    $wrapper_id = Html::getUniqueId('commerce-product-add-to-cart-form');
    $form += [
      '#wrapper_id' => $wrapper_id,
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
    ];

    $element['variation'] = [
      '#type' => 'hidden',
      '#value' => $selected_variation->id(),
    ];
    // Set the selected variation in the form state for our AJAX callback.
    $form_state->set('selected_variation', $selected_variation->id());

    $element['attributes'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['attribute-widgets'],
      ],
    ];
    foreach ($this->getAttributeInfo($selected_variation, $variations) as $field_name => $attribute) {
      $element['attributes'][$field_name] = [
        '#type' => $attribute['element_type'],
        '#title' => $attribute['title'],
        '#options' => $attribute['values'],
        '#required' => $attribute['required'],
        '#default_value' => $selected_variation->getAttributeValueId($field_name),
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => [get_class($this), 'ajaxRefresh'],
          'wrapper' => $form['#wrapper_id'],
        ],
      ];
      // Convert the _none option into #empty_value.
      if (isset($element['attributes'][$field_name]['#options']['_none'])) {
        if (!$element['attributes'][$field_name]['#required']) {
          $element['attributes'][$field_name]['#empty_value'] = '';
        }
        unset($element['attributes'][$field_name]['#options']['_none']);
      }
      // 1 required value -> Disable the element to skip unneeded ajax calls.
      if ($attribute['required'] && count($attribute['values']) === 1) {
        $element['attributes'][$field_name]['#disabled'] = TRUE;
      }
      // Optimize the UX of optional attributes:
      // - Hide attributes that have no values.
      // - Require attributes that have a value on each variation.
      if (empty($element['attributes'][$field_name]['#options'])) {
        $element['attributes'][$field_name]['#access'] = FALSE;
      }
      if (!isset($element['attributes'][$field_name]['#empty_value'])) {
        $element['attributes'][$field_name]['#required'] = TRUE;
      }
    }

    return $element;
  }

  /**
   * Ajax callback.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return mixed
   *   Returns @var \Drupal\Core\Ajax\AjaxResponse $response.
   */
  public static function ajaxRefresh(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Render\MainContent\MainContentRendererInterface $ajax_renderer */
    $ajax_renderer = \Drupal::service('main_content_renderer.ajax');
    $request = \Drupal::request();
    $route_match = \Drupal::service('current_route_match');
    /** @var \Drupal\Core\Ajax\AjaxResponse $response */
    $response = $ajax_renderer->renderResponse($form, $request, $route_match);

    $variation = ProductVariation::load($form_state->get('selected_variation'));

    /** @var \Drupal\commerce_product\ProductVariationFieldRendererInterface $variation_field_renderer */
    $variation_field_renderer = \Drupal::service('commerce_product.variation_field_renderer');
    $view_mode = $form_state->get('form_display')->getMode();
    $variation_field_renderer->replaceRenderedFields($response, $variation, $view_mode);
    // Allow modules to add arbitrary ajax commands to the response.
    $event = new ProductVariationAjaxChangeEvent($variation, $response, $view_mode);
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $event_dispatcher->dispatch(ProductEvents::PRODUCT_VARIATION_AJAX_CHANGE, $event);

    return $response;
  }

  /**
   * Altering of profile form in the modal window.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function getAdditionalFormElements(array &$form, FormStateInterface $form_state) {
    $args = commerce_buy_click_get_arg();

    if ($args[1] == 'commerce_buy_click' && isset($args[2])) {
      $productId = intval($args[2]);
      $product = Product::load($productId);
      $current_uri = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : NULL;

      $config = $this->getConfig();

      $form['uid'] = [
        '#type' => 'value',
        '#value' => $this->getCurrentUserId(),
      ];

      $form['type'] = [
        '#type' => 'value',
        '#value' => CommerceBuyClickInterface::ENTITY_TYPE_PROFILE_TYPE,
      ];

      $form['redirect'] = [
        '#type' => 'value',
        '#value' => $current_uri,
      ];

      $form['product_id'] = [
        '#type' => 'value',
        '#value' => $productId,
      ];

      $form['commerce_buy_click']['prices'] = [
        '#markup' => render($this->rendererPrices($product)),
        '#weight' => -100,
      ];

      $description = !empty($config->get('default.description')) ? $config->get('default.description') : '';
      if ($description) {
        $form['commerce_buy_click']['description'] = [
          '#markup' => $description,
          '#weight' => -90,
        ];
      }

      $display_quantity = !empty($config->get('default.display_quantity')) ? $config->get('default.display_quantity') : 0;
      if ($display_quantity) {
        $form['commerce_buy_click']['quantity'] = [
          '#type' => 'number',
          '#title' => $this->t('Quantity'),
          '#default_value' => 1,
          '#weight' => 90,
          '#min' => 1,
          '#max' => 99999999,
          '#required' => TRUE,
        ];
      }

      $element = $this->getAdditionalVariation($form, $product, $form_state);
      $form['commerce_buy_click'] += $element;

      $display_product = !empty($config->get('default.display_product')) ? $config->get('default.display_product') : 0;
      if ($display_product) {
        $form['commerce_buy_click']['display_product'] = [
          '#markup' => render($this->rendererProduct($product)),
          '#weight' => -90,
        ];
      }

      $form['actions'] = [
        '#type' => 'actions',
        '#weight' => 100,
      ];

      $submit_label = !empty($config->get('default.submit_label')) ? $config->get('default.submit_label') : CommerceBuyClickInterface::TITLE_BUTTON_DEFAULT;
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#name' => 'submit',
        '#weight' => 100,
        '#value' => $this->t('%submit_label', ['%submit_label' => $submit_label]),
        '#ajax' => [
          'callback' => '\Drupal\commerce_buy_click\Controller\CommerceBuyClickController::ajaxSubmitCallback',
          'event' => 'click',
          'progress' => [
            'type' => 'throbber',
          ],
        ],
      ];

      $form['#attached']['library'][] = 'commerce_buy_click/commerce_buy_click.css';
    }
  }

  /**
   * Returns the renderer ajax button.
   *
   * @param int $productId
   *   The product id.
   * @param string $link_title
   *   The link title from settings.
   *
   * @return array
   *   Returns renderable array.
   */
  protected function rendererButtonAjax($productId, $link_title) {
    $config = $this->getConfig();
    $window_width = !empty($config->get('default.window_width')) ? $config->get('default.window_width') : 600;
    return [
      '#type' => 'link',
      '#title' => $this->t('%$link_title', ['%$link_title' => $link_title]),
      '#url' => Url::fromRoute('commerce_buy_click.create_order', [
        'commerce_product' => $productId,
      ]),
      '#options' => [
        'attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode([
            'width' => $window_width,
          ]),
        ],
      ],
      '#attached' => ['library' => ['core/drupal.dialog.ajax']],
    ];
  }

  /**
   * Altering of commerce add to cart form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function getAdditionalFormButton(array &$form, FormStateInterface $form_state) {
    if (is_object($form_state) && isset($form_state->getStorage()['product'])) {
      $productId = $form_state->getStorage()['product']->id();

      $config = $this->getConfig();
      $link_title = !empty($config->get('default.link_title')) ? $config->get('default.link_title') : CommerceBuyClickInterface::TITLE_LINK_DEFAULT;

      $form['actions']['commerce_buy_click'] = $this->rendererButtonAjax($productId, $link_title);
    }
  }

  /**
   * Building field for views.
   *
   * @param int $productId
   *   The product id.
   * @param array $options
   *   The options of settings.
   *
   * @return array
   *   Returns renderable array.
   */
  public function buildFieldButton($productId, array $options) {
    $link_title = !empty($options['link_title']) ? $options['link_title'] : CommerceBuyClickInterface::TITLE_LINK_DEFAULT;
    $link = $this->rendererButtonAjax($productId, $link_title);
    return $link;
  }

}
