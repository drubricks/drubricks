<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\EventSubscriber;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\drubricks_core\Api\ApiResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Exceptions on routes with the option _drubricks_api become JSON errors.
 */
final class ApiExceptionSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::EXCEPTION => ['onException', 90]];
  }

  /**
   * Converts the exception.
   */
  public function onException(ExceptionEvent $event): void {
    $route = $event->getRequest()->attributes->get('_route_object');
    if (!$route || !$route->getOption('_drubricks_api')) {
      return;
    }
    $e = $event->getThrowable();
    $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
    $message = match (TRUE) {
      $status === 403 => (string) $this->t('You are not allowed to do this.'),
      $status === 404 => (string) $this->t('Not found.'),
      $status >= 500 => (string) $this->t('Server error.'),
      default => $e->getMessage() ?: (string) $this->t('Bad request.'),
    };
    $event->setResponse(ApiResponse::error($status, $message));
  }

}
