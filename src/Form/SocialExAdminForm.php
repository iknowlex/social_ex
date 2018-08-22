<?php

namespace Drupal\social_ex\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class SocialExAdminForm.
 */
class SocialExAdminForm extends ConfigFormBase {

  /**
   * An event dispatcher instance to use for configuration events.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public function __construct(
  ConfigFactoryInterface $config_factory,
  EventDispatcherInterface $event_dispatcher) {
    parent::__construct($config_factory);
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'), $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'social_ex.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'social_ex_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['description'] = [
      '#markup' => '<div>' . t('SocialEx configure your social media share link(s) to display on pages using block.') . '</div>',
    ];

    $i = 0;
    $token_types = ['current_page'];
    $num_links = $form_state->get('num_links');
    $config = $this->config('social_ex.settings');
    $socialex = $config->get('social_profiles');
    $socialex_conf = $config->get('socialex_conf');

    $remove = $form_state->get('remove-profile');
    if (!empty($remove)) {
      $remove = str_ireplace('remove-', '', $remove);
      unset($socialex[$remove]);
      $socialex = array_values($socialex);
    }

    if (count($socialex) > $num_links) {
      $num_links = count($socialex);
    }

    if (empty($num_links)) {
      $num_links = 1;
    }

    $form_state->set('num_links', $num_links);

    $form['#tree'] = TRUE;
    $form['profile_set'] = [
      '#type' => 'details',
      '#title' => $this->t('Social media share'),
      '#prefix' => '<div id="socialex-fieldset-wrapper">',
      '#suffix' => '</div>',
      '#open' => TRUE,
    ];

    for ($i = 0; $i < $num_links; $i++) {

      $title = (isset($socialex[$i]['link_text']) && !empty($socialex[$i]['link_text'])) ? $socialex[$i]['link_text'] : 'New share';

      $form['profile_set'][$i] = [
        '#type' => 'details',
        '#title' => $title,
        '#open' => FALSE,
      ];
      $form['profile_set'][$i]['link_text'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Link text'),
        '#default_value' => (isset($socialex[$i]['link_text']) ? $socialex[$i]['link_text'] : NULL),
        '#description' => t('Text of the link'),
      ];

      $form['profile_set'][$i]['link_url'] = [
        '#type' => 'textfield',
        '#title' => $this->t('API url'),
        '#default_value' => (isset($socialex[$i]['link_url']) ? $socialex[$i]['link_url'] : NULL),
        '#description' => $this->t('Use as href attribute to specifies the link destination'),
      ];

      $form['profile_set'][$i]['token_browser'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => $token_types,
        '#click_insert' => TRUE,
        '#dialog' => TRUE,
      ];

      $form['profile_set'][$i]['description'] = [
        '#type' => 'textfield',
        '#title' => t('Description'),
        '#description' => $this->t('The description is used for the title and WAI-ARIA attribute.'),
        '#default_value' => (isset($socialex[$i]['description']) ? $socialex[$i]['description'] : NULL),
      ];

      $form['profile_set'][$i]['link_img'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Image link'),
        '#default_value' => (isset($socialex[$i]['link_img']) ? $socialex[$i]['link_img'] : NULL),
        '#description' => t('If you want to have your custom image, give image path.'),
      ];

      $form['profile_set'][$i]['css'] = [
        '#type' => 'textfield',
        '#title' => t('CSS class'),
        '#description' => $this->t('CSS class(s) for the element.'),
        '#default_value' => (isset($socialex[$i]['css']) ? $socialex[$i]['css'] : NULL),
      ];
      $form['profile_set'][$i]['target'] = [
        '#type' => 'select',
        '#title' => t('Target'),
        '#options' => ['_blank' => 'Blank', '_self' => 'Self'],
        '#description' => $this->t('Specifies where to open the link.'),
        '#default_value' => (isset($socialex[$i]['target']) ? $socialex[$i]['target'] : NULL),
      ];

      $form['profile_set'][$i]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Display order'),
        '#delta' => 10,
        '#default_value' => (isset($socialex[$i]['weight']) ? $socialex[$i]['weight'] : NULL),
        '#description' => t('Order of the social link to render'),
      ];

      $form['profile_set'][$i]['enable'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Disable'),
        '#default_value' => (isset($socialex[$i]['enable']) ? $socialex[$i]['enable'] : NULL),
        '#description' => t('Disabled the settings form appearing'),
      ];

      $form['profile_set'][$i]['remove_profile'] = [
        '#type' => 'submit',
        '#name' => 'remove-' . $i,
        '#value' => t('Remove'),
        '#submit' => ['::removeCallback'],
        '#ajax' => [
          'callback' => '::addmoreCallback',
          'wrapper' => 'socialex-fieldset-wrapper',
        ],
      ];

    }
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['profile_set']['actions']['add_name'] = [
      '#type' => 'submit',
      '#value' => t('Create new share'),
      '#submit' => ['::addOne'],
      '#ajax' => [
        'callback' => '::addmoreCallback',
        'wrapper' => 'socialex-fieldset-wrapper',
      ],
    ];

    $form['config_set'] = [
      '#type' => 'details',
      '#title' => $this->t('Presentation settings'),
      '#prefix' => '<div id="socialex-configset-wrapper">',
      '#suffix' => '</div>',
      '#open' => FALSE,
    ];

    $form['config_set']['css_class'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CSS class'),
      '#default_value' => (isset($socialex_conf['css_class']) ? $socialex_conf['css_class'] : NULL),
      '#description' => t('Specify CSS class attribute to use for list. You can also specify custom css class from theme style. Use social_ex-black, social_ex-round to to enable the existing style.'),
    ];

    $form['config_set']['fontawesome'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Load fontawesome css'),
      '#default_value' => (isset($socialex_conf['fontawesome']) ? $socialex_conf['fontawesome'] : TRUE),
      '#description' => t('Enable loading of Fontawesome css from CDN. Recommended if you are not loading it form your local theme and using SocialEx style.'),
    ];

    $form_state->setCached(FALSE);
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return parent::buildForm($form, $form_state);

  }

  /**
   * Add new share configuration.
   */
  public function addOne(array &$form, FormStateInterface $form_state) {
    $num_links = $form_state->get('num_links');
    $add_button = $num_links + 1;
    $form_state->set('num_links', $add_button);
    $form_state->setRebuild();
  }

  /**
   * Callback method.
   */
  public function addmoreCallback(array &$form, FormStateInterface $form_state) {
    $num_links = $form_state->get('num_links');
    $add_button = $num_links + 1;
    $form_state->set('num_links', $add_button);
    $form_state->setRebuild();
    return $form['profile_set'];
  }

  /**
   * Callback method.
   */
  public function removeCallback(array &$form, FormStateInterface $form_state) {
    $num_links = $form_state->get('num_links');
    if ($num_links > 1) {
      $remove_button = $num_links - 1;
      $form_state->set('num_links', $remove_button);
    }
    $triggerdElement = $form_state->getTriggeringElement();
    if (isset($triggerdElement['#name'])) {
      $form_state->set('remove-profile', $triggerdElement['#name']);
    }
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $social_profiles = $form_state->getValues(['profile_set']);
    unset($social_profiles['profile_set']['actions']);

    $profiles = $social_profiles['profile_set'];
    array_walk($profiles, function (&$value, &$key) {
        unset($value['remove_profile']);
    });
    usort($profiles, function ($w1, $w2) {
        return ($w1['weight'] > $w2['weight']) ? 1 : -1;
    });

    $socialex_conf = $form_state->getValue(['config_set']);
    $config = $this->config('social_ex.settings');
    $config->set('social_profiles', $profiles);
    $config->set('socialex_conf', $socialex_conf);
    $config->save();

    drupal_set_message($this->t('Configuration has been saved'));
  }

}
