<?php

declare(strict_types=1);

namespace Drupal\Tests\drubricks_core\Kernel;

use Drupal\Core\Render\Placeholder\PlaceholderStrategyInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\drubricks_core\Render\Placeholder\HtmxBigPipeStrategy;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * BigPipe leaves the placeholders of HTMX fragments to single flush.
 *
 * Dashboard blocks came back empty when the shell swapped the home page in
 * with HTMX while their render cache was cold: BigPipe streamed them after
 * the swapped element, where HTMX drops them.
 */
#[Group('drubricks')]
#[RunTestsInSeparateProcesses]
final class HtmxBigPipeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'big_pipe',
    'block',
    'views',
    'inline_form_errors',
    'options',
    'drubricks_core',
  ];

  /**
   * HTMX request → no BigPipe placeholders; other requests → BigPipe decides.
   */
  public function testHtmxRequestsSkipBigPipe(): void {
    $this->assertInstanceOf(HtmxBigPipeStrategy::class, $this->container->get('placeholder_strategy.big_pipe'));

    $placeholders = ['<drupal-render-placeholder>' => ['#markup' => 'Block']];
    $inner = new class implements PlaceholderStrategyInterface {

      /**
       * {@inheritdoc}
       */
      public function processPlaceholders(array $placeholders) {
        return array_map(static fn () => ['#markup' => 'big pipe'], $placeholders);
      }

    };
    $stack = $this->container->get('request_stack');
    $strategy = new HtmxBigPipeStrategy($inner, $stack);

    $stack->push(Request::create('/apps'));
    $this->assertCount(1, $strategy->processPlaceholders($placeholders), 'A full page load keeps BigPipe.');

    $htmx = Request::create('/apps');
    $htmx->headers->set('HX-Request', 'true');
    $stack->push($htmx);
    $this->assertSame([], $strategy->processPlaceholders($placeholders), 'An HTMX fragment renders its placeholders itself.');
    $stack->pop();
    $stack->pop();
  }

}
