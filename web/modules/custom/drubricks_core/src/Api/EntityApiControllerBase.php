<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Api;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Base for custom JSON endpoints that change entities (JS islands).
 *
 * Routes should require `_csrf_request_header_token: 'TRUE'` (the JS sends
 * X-CSRF-Token fetched from drupalSettings.drubricks.csrfTokenUrl) and set
 * the option `_drubricks_api: TRUE`, so exceptions become JSON errors.
 */
abstract class EntityApiControllerBase extends ControllerBase {

  /**
   * Decodes the JSON body of the request.
   */
  protected function jsonBody(Request $request): array {
    $data = json_decode($request->getContent() ?: '{}', TRUE);
    if (!is_array($data)) {
      throw new BadRequestHttpException('The body must be a JSON object.');
    }
    return $data;
  }

  /**
   * Sets field values (with field access), validates and saves.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param array $values
   *   Field name => value.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse|null
   *   A 422 response when invalid, NULL when saved.
   */
  protected function updateEntity(ContentEntityInterface $entity, array $values): ?JsonResponse {
    if (!$entity->access('update')) {
      throw new AccessDeniedHttpException();
    }
    foreach ($values as $field => $value) {
      if (!$entity->hasField($field) || !$entity->get($field)->access('edit')) {
        throw new AccessDeniedHttpException("No access to the field $field.");
      }
      $entity->set($field, $value);
    }
    $violations = $entity->validate();
    $violations->filterByFieldAccess();
    if ($violations->count()) {
      return ApiResponse::violations($violations);
    }
    $entity->save();
    return NULL;
  }

}
