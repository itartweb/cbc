<?php

namespace Drupal\commerce_buy_click\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_buy_click\CommerceBuyClickInterface;

/**
 * Class CommerceBuyClickButton.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("commerce_buy_click_button")
 */
class CommerceBuyClickButton extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Do nothing.
  }

  /**
   * Information about options for all kinds of purposes will be held here.
   * @code
   * 'option_name' => array(
   *  - 'default' => default value,
   *  - 'contains' => (optional) array of items this contains, with its own
   *      defaults, etc. If contains is set, the default will be ignored and
   *      assumed to be array().
   *  ),
   * @endcode
   *
   * @return array
   *   Returns the options of this handler/plugin.
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['link_title'] = ['default' => CommerceBuyClickInterface::TITLE_LINK_DEFAULT];
    return $options;
  }

  /**
   * Provides a form to edit options for this plugin.
   *
   * @see \Drupal\views\Plugin\views\PluginBase::defineOptions()
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['link_title'] = [
      '#type' => 'textfield',
      '#required' => FALSE,
      '#title' => $this->t('Link title'),
      '#description' => $this->t('This link title.'),
      '#default_value' => $this->options['link_title'],
    ];

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * Gets the configuration object when needed.
   *
   * @return object
   *   The config object.
   */
  protected function getConfig() {
    return \Drupal::config('commerce_buy_click.settings');
  }

  /**
   * Renders the field.
   *
   * @param \Drupal\views\ResultRow $values
   *   The values retrieved from a single row of a view's query result.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface
   *   The rendered output. If the output is safe it will be wrapped in an
   *   object that implements MarkupInterface. If it is empty or unsafe it
   *   will be a string.
   */
  public function render(ResultRow $values) {
    if (isset($values->product_id) && $values->product_id) {
      $product = Product::load($values->product_id);
      $cbc_helper = \Drupal::service('commerce_buy_click_product.service');
      $cbc_helper->setProduct($product);

      if ($cbc_helper->hasPrtoduct() && $cbc_helper->hasProductInStore()) {
        $config = $this->getConfig();
        $default_product_types = !empty($config->get('default.product_types')) ? $config->get('default.product_types') : [];
        if (in_array($cbc_helper->getProductBundle(), $default_product_types)
          && $default_product_types[$cbc_helper->getProductBundle()]) {

          $cbc_form_model = \Drupal::service('commerce_buy_click_form.service');
          return $cbc_form_model->buildFieldButton($product->id(), $this->options);
        }
      }
    }
    return NULL;
  }

}
