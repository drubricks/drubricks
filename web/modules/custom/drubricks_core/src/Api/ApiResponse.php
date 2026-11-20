<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Api;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Entity\EntityConstraintViolationListInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * JSON responses of Drubricks endpoints, one shape for the JS islands.
 *
 * Success: { "data": … }
 * Error:   { "message": "…", "errors": { "field": ["…"] } } with 4xx / 5xx.
 */
final class ApiResponse {

  /**
   * A successful response.
   */
  public static function ok(mixed $data, int $status = 200): JsonResponse {
    return new JsonResponse(['data' => $data], $status);
  }

  /**
   * A cacheable successful response (for GET endpoints).
   */
  public static function cacheable(mixed $data): CacheableJsonResponse {
    return new CacheableJsonResponse(['data' => $data]);
  }

  /**
   * An error response.
   *
   * @param int $status
   *   HTTP status.
   * @param string $message
   *   Message for the user (already translated).
   * @param array<string, string[]> $errors
   *   Field errors keyed by field name.
   */
  public static function error(int $status, string $message, array $errors = []): JsonResponse {
    $body = ['message' => $message];
    if ($errors) {
      $body['errors'] = $errors;
    }
    return new JsonResponse($body, $status);
  }

  /**
   * Returns 422 with the violations of an entity (or any) validation, by field.
   */
  public static function violations(ConstraintViolationListInterface $violations, string $message = 'The data is not valid.'): JsonResponse {
    $errors = [];
    foreach ($violations as $violation) {
      $path = $violation->getPropertyPath();
      $field = $path === '' ? '_entity' : explode('.', $path)[0];
      $errors[$field][] = strip_tags((string) $violation->getMessage());
    }
    if ($violations instanceof EntityConstraintViolationListInterface && $errors) {
      $first = reset($errors);
      $message = $first[0];
    }
    return self::error(422, $message, $errors);
  }

}
