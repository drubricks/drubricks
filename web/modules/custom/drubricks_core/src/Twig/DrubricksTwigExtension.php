<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Twig;

use Drupal\drubricks_core\Entity\ColorTokens;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig helper for kit markup: color().
 *
 * In a style attribute, color(item.color) gives var(--teal). Icons use core's
 * Icon API: {{ icon('kit', 'i-folder') }} (the "kit" pack of the drubricks
 * theme).
 */
final class DrubricksTwigExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFunctions(): array {
    return [
      new TwigFunction('color', ColorTokens::css(...)),
    ];
  }

}
