<?php

namespace Drupal\social_ex\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Utility\Token;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\social_ex\Event\SocialExEvent;

/**
 * Provides a 'SocialEx' block.
 *
 * @Block(
 *  id = "social_ex_block",
 *  admin_label = @Translation("SocialEx block"),
 * )
 */
class SocialExBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The configFactory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * An event dispatcher instance to use for configuration events.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * Constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, Token $token, EventDispatcherInterface $event_dispatcher, CurrentPathStack $current_path) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->token = $token;
    $this->eventDispatcher = $event_dispatcher;
    $this->currentPath = $current_path;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration, $plugin_id, $plugin_definition, $container->get('config.factory'), $container->get('token'), $container->get('event_dispatcher'), $container->get('path.current')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    global $base_url;

    $settings = [];
    $elements = [];

    $socialex = $this->configFactory
      ->get('social_ex.settings')
      ->get('social_profiles');

    $socialex_conf = $this->configFactory
      ->get('social_ex.settings')
      ->get('socialex_conf');
    // Arrange order for display.
    $socialex = $this->sortByWeight($socialex);

    foreach ($socialex as $delta => $socialshare) {

      if ($socialshare['enable'] === 0 && !empty($socialshare['link_url'])) {

        $link_text = strtolower(preg_replace(['/[^a-zA-Z0-9]+/', '/-+/',
          '/^-+/', '/-+$/',
        ],
        ['-', '-', '', ''],
        $socialshare['link_text']));

        $link_text = $link_text ?: 'socialex-' . $delta;

        $link_img = "";
        if (!empty($socialshare['link_img'])) {
          $link_img = $base_url . '/' . ltrim($socialshare['link_img'], '/');
        }

        $link_url = $this->token->replace($socialshare['link_url'], [],
        ['clear' => TRUE, 'callback' => [$this, 'cleanTokenValues']]);

        $elements[$link_text] = [
          'text' => $this->t($socialshare['link_text']),
          'attributes' => new Attribute([
            'class' => $socialshare['css'],
            'target' => $socialshare['target'],
            'title' => $this->t($socialshare['description']),
            'id' => $link_text,
          ]),
          'url' => new Attribute(['href' => $link_url]),
          'image' => $link_img,
        ];
      }
    }

    $build = [];
    $library = ['social_ex/social_ex.default'];
    if ($socialex_conf['fontawesome'] == '1') {
      $library[] = 'social_ex/fontawesome';
    }

    // Call pre-render event before render.
    $event = new SocialExEvent($elements);
    $this->eventDispatcher->dispatch('social_ex.pre_render', $event);
    $elements = $event->getElement();

    $build['social_ex_block'] = [
      '#theme' => 'social_ex_links',
      '#elements' => $elements,
      '#list_attributes' => new Attribute(['class' => ['social_ex', $socialex_conf['css_class']]]),
      '#attached' => [
        'library' => $library,
        'drupalSettings' => $settings,
      ],
      '#cache' => [
        'tags' => [
          'social_ex:' . $this->currentPath->getPath(),
        ],
        // 'contexts' => ['url',],.
      ],
    ];

    return $build;
  }

  /**
   * Arrange list items in order.
   */
  protected function sortByWeight($element) {
    usort($element, function ($w1, $w2) {
        return ($w1['weight'] > $w2['weight']) ? 1 : -1;
    });
    return $element;
  }

  /**
   * Clean value for rendering.
   */
  public function cleanTokenValues(&$replacements, $data = [], $options = []) {
    foreach ($replacements as $token => $value) {
      // Only clean non-path tokens.
      if (!preg_match('/(path|alias|url|url-brief)\\]$/', $token)) {
        $replacements[$token] = urlencode($value);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  /*
  public function getCacheTags() {
  //With this when your node change your block will rebuild
  if ($node = \Drupal::routeMatch()->getParameter('node')) {
  //if there is node add its cachetag
  return Cache::mergeTags(parent::getCacheTags(), array('node:' . $node->id()));
  } else {
  //Return default tags instead.
  return parent::getCacheTags();
  }
  }
   */

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    // If you depends on \Drupal::routeMatch()
    // you must set context of this block with 'route' context tag.
    // Every new route this block will rebuild.
    return Cache::mergeContexts(parent::getCacheContexts(), ['route', 'url']);
  }

}
