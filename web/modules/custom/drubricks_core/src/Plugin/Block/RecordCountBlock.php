<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\drubricks_core\Entity\ColorTokens;
use Drupal\drubricks_core\Entity\DrubricksEntityInterface;
use Drupal\drubricks_core\Dashboard\DashboardBlockBase;

/**
 * A stat tile: the number of records of a type the user may see.
 *
 * Placed in the dashboard's stat strip, linked to the type's list. No code
 * needed for a simple counter: place it in the Block layout UI.
 */
#[Block(
  id: 'drubricks_record_count',
  admin_label: new TranslatableMarkup('Record count'),
  category: new TranslatableMarkup('Dashboard'),
)]
final class RecordCountBlock extends DashboardBlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['entity_type' => '', 'icon' => '', 'color' => ''];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $types = [];
    foreach ($this->entityTypeManager->getDefinitions() as $id => $definition) {
      if ($definition->entityClassImplements(DrubricksEntityInterface::class)) {
        $types[$id] = $definition->getLabel();
      }
    }
    asort($types);
    $form['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Records'),
      '#options' => $types,
      '#default_value' => $this->configuration['entity_type'],
      '#required' => TRUE,
      '#description' => $this->t('Counts the records the user may see; the tile links to their list.'),
    ];
    $form['icon'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Icon'),
      '#default_value' => $this->configuration['icon'],
      '#description' => $this->t('A kit icon name (e.g. <em>users</em>) or <em>pack:icon</em>.'),
    ];
    $form['color'] = [
      '#type' => 'select',
      '#title' => $this->t('Colour'),
      '#options' => ColorTokens::options(),
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->configuration['color'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    foreach (['entity_type', 'icon', 'color'] as $key) {
      $this->configuration[$key] = (string) $form_state->getValue($key);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl(): ?Url {
    $type = $this->entityTypeManager->getDefinition($this->configuration['entity_type'], FALSE);
    return $type?->hasLinkTemplate('collection') ? Url::fromRoute('entity.' . $type->id() . '.collection') : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $query = $this->configuration['entity_type'] ? $this->accessibleQuery($this->configuration['entity_type'], $this->currentUser) : NULL;
    if (!$query) {
      return [];
    }
    return $this->stat(array_filter([
      'label' => $this->label(),
      'value' => (int) $query->count()->execute(),
      'icon' => $this->configuration['icon'],
      'color' => $this->configuration['color'],
    ], static fn ($value) => $value !== ''), $this->getUrl());
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    $type = $this->entityTypeManager->getDefinition($this->configuration['entity_type'], FALSE);
    return Cache::mergeTags(parent::getCacheTags(), $type?->getListCacheTags() ?? []);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();
    // The tile goes away with the app that provides the records.
    if ($type = $this->entityTypeManager->getDefinition($this->configuration['entity_type'], FALSE)) {
      $dependencies['module'][] = $type->getProvider();
    }
    return $dependencies;
  }

}
