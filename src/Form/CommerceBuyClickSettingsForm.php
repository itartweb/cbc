<?php

namespace Drupal\commerce_buy_click\Form;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\state_machine\WorkflowManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_buy_click\CommerceBuyClickInterface;

/**
 * Class CommerceBuyClickSettingsForm.
 */
class CommerceBuyClickSettingsForm extends ConfigFormBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The workflow type plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $workflowManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Content Locker Service instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\state_machine\WorkflowManagerInterface $workflow_manager
   *   The workflow manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, WorkflowManagerInterface $workflow_manager, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($config_factory);

    $this->configFactory = $config_factory;
    $this->workflowManager = $workflow_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.workflow'),
      $container->get('entity.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_buy_click_settings_form';
  }

  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return [
      'commerce_buy_click.settings',
    ];
  }

  /**
   * Get options of Entity type.
   *
   * @param string $type
   *   Entity type.
   *
   * @return array
   *   The options array.
   */
  protected function getCommerceOptionsByEntityType($type) {
    $options = [];

    try {
      $ids = $this->entityTypeManager->getStorage($type)->getQuery()->execute();

      $entities = $this->entityTypeManager->getListBuilder($type)->getStorage()->loadMultiple($ids);

      if ($entities) {
        foreach ($entities as $entity) {
          $options[$entity->id()] = $entity->label();
        }
      }

      return $options;
    }
    catch (InvalidPluginDefinitionException $e) {
      watchdog_exception('config', $e);
      return $options;
    }
  }

  /**
   * Get options of Order States.
   *
   * @return array
   *   The states options array.
   */
  protected function getCommerceOptionsByState() {
    $options = [];
    $order_type = OrderType::load(CommerceBuyClickInterface::CBC_ORDER_TYPE);

    try {
      $states = $this->workflowManager->createInstance($order_type->getWorkflowId())->getStates();

      if ($states) {
        foreach ($states as $state) {
          $options[$state->getId()] = $state->getLabel()->render();
        }
      }

      return $options;
    }
    catch (PluginException $e) {
      watchdog_exception('config', $e);
      return $options;
    }
  }

  /**
   * Get options of yes or no.
   *
   * @return array
   *   The states options array.
   */
  protected function getCommerceOptionsByYesNo() {
    return [
      0 => $this->t('No'),
      1 => $this->t('Yes'),
    ];
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
   * Builds the settings form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The parent form with the form elements added.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->getConfig();
    $window_width = !empty($config->get('default.window_width')) ? $config->get('default.window_width') : 600;
    $default_stores = !empty($config->get('default.stores')) ? $config->get('default.stores') : [];
    $default_product_types = !empty($config->get('default.product_types')) ? $config->get('default.product_types') : [];
    $default_state = !empty($config->get('default.state')) ? $config->get('default.state') : NULL;
    $display_product = !empty($config->get('default.display_product')) ? $config->get('default.display_product') : 0;
    $display_variation = !empty($config->get('default.display_variation')) ? $config->get('default.display_variation') : 0;
    $display_quantity = !empty($config->get('default.display_quantity')) ? $config->get('default.display_quantity') : 0;
    $submit_label = !empty($config->get('default.submit_label')) ? $config->get('default.submit_label') : CommerceBuyClickInterface::TITLE_BUTTON_DEFAULT;
    $link_title = !empty($config->get('default.link_title')) ? $config->get('default.link_title') : CommerceBuyClickInterface::TITLE_LINK_DEFAULT;
    $description = !empty($config->get('default.description')) ? $config->get('default.description') : '';
    $message = !empty($config->get('default.message')) ? $config->get('default.message') : '';

    $form['default'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Default'),
    ];

    $form['default']['window_width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Window width'),
      '#description' => $this->t('The modal window width.'),
      '#default_value' => $window_width,
    ];

    $form['default']['stores'] = [
      '#type' => 'checkboxes',
      '#options' => $this->getCommerceOptionsByEntityType(CommerceBuyClickInterface::ENTITY_TYPE_COMMERCE_STORE),
      '#title' => $this->t('Stores'),
      '#default_value' => $default_stores,
      '#required' => TRUE,
    ];

    $form['default']['display_product'] = [
      '#type' => 'radios',
      '#options' => $this->getCommerceOptionsByYesNo(),
      '#title' => $this->t('Display Product in Modal'),
      '#default_value' => $display_product,
    ];

    $form['default']['state'] = [
      '#type' => 'radios',
      '#options' => $this->getCommerceOptionsByState(),
      '#title' => $this->t('Order State'),
      '#default_value' => $default_state,
      '#required' => TRUE,
    ];

    $form['default']['product_types'] = [
      '#type' => 'checkboxes',
      '#options' => $this->getCommerceOptionsByEntityType(CommerceBuyClickInterface::ENTITY_TYPE_COMMERCE_PRODUCT_TYPE),
      '#title' => $this->t('Product types'),
      '#default_value' => $default_product_types,
      '#required' => TRUE,
    ];

    $form['default']['display_quantity'] = [
      '#type' => 'radios',
      '#options' => $this->getCommerceOptionsByYesNo(),
      '#title' => $this->t('Display Product Quantity'),
      '#default_value' => $display_quantity,
    ];

    $form['default']['display_variation'] = [
      '#type' => 'radios',
      '#options' => $this->getCommerceOptionsByYesNo(),
      '#title' => $this->t('Display Product Variations'),
      '#default_value' => $display_variation,
    ];

    $form['default']['link_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link title'),
      '#description' => $this->t('The link title on the add to cart form.'),
      '#default_value' => $link_title,
    ];

    $form['default']['submit_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Submit Button Label'),
      '#description' => $this->t('The submit label into the modal window form.'),
      '#default_value' => $submit_label,
    ];

    $form['default']['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Form Description'),
      '#default_value' => $description,
    ];

    $form['default']['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Thank You Message'),
      '#description' => $this->t('The displayed message after successful form submission.'),
      '#default_value' => $message,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Submits the commerce buy click settings form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('commerce_buy_click.settings')
      ->set('default.window_width', $form_state->getValue('window_width'))
      ->set('default.stores', $form_state->getValue('stores'))
      ->set('default.product_types', $form_state->getValue('product_types'))
      ->set('default.state', $form_state->getValue('state'))
      ->set('default.display_product', $form_state->getValue('display_product'))
      ->set('default.display_variation', $form_state->getValue('display_variation'))
      ->set('default.display_quantity', $form_state->getValue('display_quantity'))
      ->set('default.link_title', $form_state->getValue('link_title'))
      ->set('default.submit_label', $form_state->getValue('submit_label'))
      ->set('default.description', $form_state->getValue('description'))
      ->set('default.message', $form_state->getValue('message'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
