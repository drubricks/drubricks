<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * A Drubricks application record: owner, created / changed, status, colour.
 */
interface DrubricksEntityInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface, EntityPublishedInterface {

  /**
   * Returns the creation timestamp.
   */
  public function getCreatedTime(): int;

  /**
   * Returns the colour token name (e.g. 'teal'), or NULL.
   */
  public function getColor(): ?string;

}
