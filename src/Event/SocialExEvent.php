<?php

namespace Drupal\social_ex\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Class SocialExEvent.
 *
 * Manage event for pre-rendering element.
 */
class SocialExEvent extends Event {

  /**
   * An elemet array for processing.
   *
   * @var array
   */
  protected $element;

  /**
   * Constructor.
   */
  public function __construct($element) {
    $this->element = $element;
  }

  /**
   * Return the element.
   *
   * @return array
   */
  public function getElement() {
    return $this->element;
  }

  /**
   * Set element.
   */
  public function setElement($element) {
    $this->element = $element;
  }

}
