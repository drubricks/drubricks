<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Htmx\HtmxRequestInfoTrait;
use Drupal\Core\Url;
use Drupal\drubricks_core\Htmx\HtmxResponses;
use Drupal\drubricks_core\Htmx\Swap;
use Drupal\drubricks_core\Screen\ScreenViews;

/**
 * Delete confirmation of a Drubricks entity, HTMX-aware.
 */
class DrubricksDeleteForm extends ContentEntityDeleteForm {

  use HtmxRequestInfoTrait;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $url = $this->getEntity()->toUrl('delete-form');
    if ($destination = HtmxResponses::destination($this->getRequest())) {
      $url->setOption('query', ['destination' => $destination]);
    }
    // A record that must stay never gets here: "delete" access is forbidden
    // with the reasons (DrubricksAccessControlHandler).
    Swap::form($form, 'drubricks-delete-form', $url);
    $form['actions']['submit']['#attributes']['class'][] = 'destructive';
    if (isset($form['actions']['cancel'])) {
      $form['actions']['cancel']['#attributes']['data-drubricks-modal-close'] = '';
      $form['actions']['cancel']['#attributes']['class'][] = 'btn';
    }
    return $form;
  }

  /**
   * The main view of the entity type (its default board / calendar or list).
   */
  private function defaultViewUrl(): Url {
    $type = $this->getEntity()->getEntityType();
    if ($type->hasHandlerClass('drubricks_screen')) {
      return ScreenViews::defaultUrl($type, $this->entityTypeManager->getHandler($type->id(), 'drubricks_screen'));
    }
    return Url::fromRoute('entity.' . $type->id() . '.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $entity = $this->getEntity();
    $message = strip_tags((string) $this->getDeletionMessage());
    $collection = $this->defaultViewUrl();
    $type = $entity->getEntityType();
    if ($type->hasHandlerClass('drubricks_screen')) {
      $handler = $this->entityTypeManager->getHandler($type->id(), 'drubricks_screen');
      $collection = ScreenViews::cameFrom($this->getRequest(), $type, $handler, ['open' => NULL]) ?? $collection;
    }
    // drubricks_core logs every delete (hook_entity_delete).
    $entity->delete();
    $destination = HtmxResponses::destination($this->getRequest());
    if ($destination !== NULL) {
      // Back to e.g. the admin view (core redirects to ?destination=).
      $this->messenger()->addStatus($message);
      if ($this->isHtmxRequest()) {
        $form_state->setResponse(HtmxResponses::redirect(Url::fromUserInput($destination)));
      }
      return;
    }
    if ($this->isHtmxRequest()) {
      $form_state->setResponse(HtmxResponses::navigate($collection, [
        'drubricks:deleted' => ['message' => $message, 'entityType' => $entity->getEntityTypeId()],
      ]));
      return;
    }
    $this->messenger()->addStatus($message);
    $form_state->setRedirectUrl($collection);
  }

}
