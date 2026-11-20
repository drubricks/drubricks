<?php

declare(strict_types=1);

namespace Drupal\drubricks_design_system\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * A Form API form with every common element, to show the theme's form look.
 *
 * Nothing is saved. "Validate" with an empty title shows the inline errors.
 */
final class DemoForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'drubricks_design_system_demo_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#required' => TRUE,
      '#placeholder' => $this->t('What needs to be done?'),
      '#description' => $this->t('Leave it empty and press Validate to see an inline error.'),
    ];
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#default_value' => 'alex@example.com',
    ];
    $form['row'] = ['#type' => 'container', '#attributes' => ['class' => ['form-row']]];
    $form['row']['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => [
        'todo' => $this->t('To Do'),
        'doing' => $this->t('In Progress'),
        'done' => $this->t('Done'),
      ],
    ];
    $form['row']['due'] = [
      '#type' => 'date',
      '#title' => $this->t('Due date'),
    ];
    $form['budget'] = [
      '#type' => 'number',
      '#title' => $this->t('Budget'),
      '#field_prefix' => '€',
      '#step' => 100,
    ];
    $form['notes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Notes'),
      '#rows' => 3,
      '#placeholder' => $this->t('Add details…'),
    ];
    $form['notify'] = [
      '#type' => 'radios',
      '#title' => $this->t('Notify me'),
      '#options' => [
        'all' => $this->t('About everything'),
        'mentions' => $this->t('Only mentions'),
        'never' => $this->t('Never'),
      ],
      '#default_value' => 'all',
    ];
    $form['labels'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Labels'),
      '#options' => [
        'design' => $this->t('Design'),
        'bug' => $this->t('Bug'),
        'docs' => $this->t('Docs'),
      ],
      '#default_value' => ['design'],
    ];
    $form['digest'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Email digest (a switch: class "switch")'),
      '#attributes' => ['class' => ['switch']],
      '#default_value' => TRUE,
    ];
    $form['remember'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Keep me signed in'),
    ];
    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced settings'),
      '#open' => FALSE,
    ];
    $form['advanced']['key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Key'),
      '#default_value' => 'APP',
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete'),
      '#button_type' => 'danger',
      '#limit_validation_errors' => [],
      '#submit' => ['::submitForm'],
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Validate'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->messenger()->addStatus($this->t('The form is valid. Nothing was saved.'));
  }

}
