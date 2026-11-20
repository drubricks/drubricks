<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Htmx\HtmxRequestInfoTrait;
use Drupal\Core\Url;
use Drupal\drubricks_core\Htmx\HtmxResponses;
use Drupal\drubricks_core\Htmx\Swap;
use Drupal\drubricks_core\Screen\ScreenViews;

/**
 * Add / edit form of a Drubricks entity.
 *
 * Submitted with HTMX: validation errors re-render the form in place, a
 * successful save navigates the shell to the record and fires
 * "drubricks:saved" (the JS closes the modal and shows a HUD message).
 * Without JS it is a normal form with a redirect. Opened with ?destination=
 * (e.g. "Edit" in an admin view) it returns there with a full page load.
 */
class DrubricksEntityForm extends ContentEntityForm {

  use HtmxRequestInfoTrait;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    Swap::form($form, 'drubricks-entity-form', $this->formUrl());
    $form['#attributes']['class'][] = 'form';
    return $form;
  }

  /**
   * Where the shell goes after a save.
   *
   * Back to the board / calendar the modal was opened from (record open in
   * the side panel), otherwise the record's page.
   */
  protected function returnUrl(): Url {
    $entity = $this->entity;
    $type = $entity->getEntityType();
    if ($type->hasHandlerClass('drubricks_screen')) {
      $handler = $this->entityTypeManager->getHandler($type->id(), 'drubricks_screen');
      $view = ScreenViews::cameFrom($this->getRequest(), $type, $handler, ['open' => $entity->id()]);
      if ($view) {
        return $view;
      }
    }
    return $entity->toUrl();
  }

  /**
   * The URL of this form (add or edit), where HTMX posts it.
   */
  protected function formUrl(): Url {
    $url = $this->entity->isNew()
      ? Url::fromRoute('entity.' . $this->entity->getEntityTypeId() . '.add_form')
      : $this->entity->toUrl('edit-form');
    if ($destination = HtmxResponses::destination($this->getRequest())) {
      $url->setOption('query', ['destination' => $destination]);
    }
    return $url;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state): array {
    $actions = parent::actions($form, $form_state);
    unset($actions['delete']);
    $actions['submit']['#button_type'] = 'primary';
    $actions['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => match (TRUE) {
        HtmxResponses::destination($this->getRequest()) !== NULL => Url::fromUserInput(HtmxResponses::destination($this->getRequest())),
        $this->entity->isNew() => Url::fromRoute('entity.' . $this->entity->getEntityTypeId() . '.collection'),
        default => $this->entity->toUrl(),
      },
      '#attributes' => ['class' => ['btn'], 'data-drubricks-modal-close' => ''],
      '#weight' => -10,
    ];
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $status = parent::save($form, $form_state);
    $entity = $this->entity;
    $args = ['%label' => $entity->label(), '@type' => $entity->getEntityType()->getSingularLabel()];
    $message = $status === SAVED_NEW ? $this->t('Created @type %label.', $args) : $this->t('Saved %label.', $args);

    $destination = HtmxResponses::destination($this->getRequest());
    if ($destination !== NULL) {
      // Back where the edit started (core redirects to ?destination=).
      $this->messenger()->addStatus($message);
      if ($this->isHtmxRequest()) {
        $form_state->setResponse(HtmxResponses::redirect(Url::fromUserInput($destination)));
      }
      return $status;
    }
    if ($this->isHtmxRequest()) {
      $form_state->setResponse(HtmxResponses::navigate($this->returnUrl(), [
        'drubricks:saved' => [
          'message' => strip_tags((string) $message),
          'entityType' => $entity->getEntityTypeId(),
          'id' => $entity->id(),
        ],
      ]));
      return $status;
    }
    $this->messenger()->addStatus($message);
    $form_state->setRedirectUrl($entity->toUrl());
    return $status;
  }

}
