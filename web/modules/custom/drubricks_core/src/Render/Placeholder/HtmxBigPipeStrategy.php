<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Render\Placeholder;

use Drupal\Core\Render\Placeholder\PlaceholderStrategyInterface;
use Drupal\drubricks_core\Htmx\HtmxResponses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * BigPipe, but not for HTMX requests (decorates placeholder_strategy.big_pipe).
 *
 * An HTMX response is a fragment the shell swaps in by id (Swap): BigPipe
 * would stream the placeholders' content as scripts after the swapped
 * element, where HTMX drops them — dashboard blocks (per user, cached until
 * midnight) came back empty after a cache invalidation. For HTMX requests the
 * placeholders are left to the next strategy in core's chain (single flush:
 * rendered into the same response); full page loads keep BigPipe.
 */
final class HtmxBigPipeStrategy implements PlaceholderStrategyInterface {

  public function __construct(
    private readonly PlaceholderStrategyInterface $inner,
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function processPlaceholders(array $placeholders) {
    if (HtmxResponses::isHtmx($this->requestStack->getCurrentRequest())) {
      return [];
    }
    return $this->inner->processPlaceholders($placeholders);
  }

}
