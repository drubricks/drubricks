<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Hook;

use Drupal\block\BlockInterface;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\drubricks_core\Dashboard\DashboardBlockInterface;
use Drupal\drubricks_core\Entity\ColorTokens;
use Drupal\drubricks_core\Htmx\Swap;

/**
 * Blocks in the dashboard regions of the theme: panels and stat tiles.
 *
 * Any block placed in a dashboard region (an app's dashboard block, a Views
 * block, a content block) gets the template suggestion block__REGION; in the
 * "dashboard" region the template wraps it in a kit panel with the block
 * label as heading, the panel settings of the placement (third-party
 * settings: width, heading icon and colour) and the "Show all" link of a
 * dashboard block.
 *
 * @see \Drupal\drubricks_core\Controller\AppController::home()
 */
final class DashboardHooks {

  use StringTranslationTrait;

  /**
   * Panel widths.
   */
  public const SIZES = ['narrow', 'wide', 'full'];

  /**
   * Passes the dashboard region and the panel settings to the template.
   */
  #[Hook('block_view_alter')]
  public function blockViewAlter(array &$build, BlockPluginInterface $plugin): void {
    $block = $build['#block'] ?? NULL;
    if (!$block instanceof BlockInterface || !in_array($block->getRegion(), DashboardBlockInterface::REGIONS, TRUE)) {
      return;
    }
    $settings = $block->getThirdPartySettings('drubricks_core') + ['size' => 'narrow', 'icon' => '', 'color' => ''];
    $dashboard = [
      'region' => $block->getRegion(),
      'size' => in_array($settings['size'], self::SIZES, TRUE) ? $settings['size'] : 'narrow',
      'icon' => $settings['icon'],
      'color' => $settings['color'],
      'link' => NULL,
    ];
    if ($plugin instanceof DashboardBlockInterface && ($url = $plugin->getUrl())) {
      $link = ['#attributes' => []];
      Swap::link($link, $url, Swap::CONTENT);
      $generated = $url->toString(TRUE);
      CacheableMetadata::createFromRenderArray($build)
        ->merge(CacheableMetadata::createFromRenderArray($link))
        ->merge(CacheableMetadata::createFromObject($generated))
        ->applyTo($build);
      $dashboard['link'] = [
        'title' => $plugin->getLinkTitle(),
        'url' => $generated->getGeneratedUrl(),
        'attributes' => $link['#attributes'],
      ];
    }
    $build['#drubricks_dashboard'] = $dashboard;
  }

  /**
   * Template suggestion by dashboard region: block--dashboard(-stats).
   */
  #[Hook('theme_suggestions_block_alter')]
  public function themeSuggestionsBlockAlter(array &$suggestions, array $variables): void {
    if ($region = $variables['elements']['#drubricks_dashboard']['region'] ?? NULL) {
      // Least specific: templates of a plugin or a placement still win.
      array_unshift($suggestions, 'block__' . $region);
    }
  }

  /**
   * Variable "dashboard" (region, size, icon, color, link) of the template.
   */
  #[Hook('preprocess_block')]
  public function preprocessBlock(array &$variables): void {
    if (isset($variables['elements']['#drubricks_dashboard'])) {
      $variables['dashboard'] = $variables['elements']['#drubricks_dashboard'];
    }
  }

  /**
   * Panel settings in the block form, for the "dashboard" region.
   */
  #[Hook('form_block_form_alter')]
  public function blockFormAlter(array &$form, FormStateInterface $form_state): void {
    $form_object = $form_state->getFormObject();
    $block = $form_object instanceof EntityFormInterface ? $form_object->getEntity() : NULL;
    if (!$block instanceof BlockInterface || !isset($form['region'])) {
      return;
    }
    $settings = $block->getThirdPartySettings('drubricks_core');
    $form['drubricks_dashboard'] = [
      '#type' => 'details',
      '#title' => $this->t('Dashboard panel'),
      '#open' => TRUE,
      '#tree' => TRUE,
      '#weight' => $form['region']['#weight'] ?? 0,
      '#states' => ['visible' => [':input[name="region"]' => ['value' => DashboardBlockInterface::PANELS_REGION]]],
      'size' => [
        '#type' => 'select',
        '#title' => $this->t('Width'),
        '#options' => [
          'narrow' => $this->t('One column'),
          'wide' => $this->t('Two columns (when there is room)'),
          'full' => $this->t('Full row'),
        ],
        '#default_value' => $settings['size'] ?? 'narrow',
      ],
      'icon' => [
        '#type' => 'textfield',
        '#title' => $this->t('Heading icon'),
        '#default_value' => $settings['icon'] ?? '',
        '#description' => $this->t('A kit icon name (e.g. <em>users</em>) or <em>pack:icon</em>.'),
      ],
      'color' => [
        '#type' => 'select',
        '#title' => $this->t('Icon colour'),
        '#options' => ColorTokens::options(),
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $settings['color'] ?? '',
      ],
    ];
    $form['#entity_builders'][] = static::class . ':blockFormBuilder';
  }

  /**
   * Entity builder: the panel settings as third-party settings.
   */
  public function blockFormBuilder(string $entity_type_id, EntityInterface $block, array &$form, FormStateInterface $form_state): void {
    if (!$block instanceof BlockInterface) {
      return;
    }
    $values = (array) $form_state->getValue('drubricks_dashboard');
    if ($block->getRegion() !== DashboardBlockInterface::PANELS_REGION) {
      foreach (['size', 'icon', 'color'] as $key) {
        $block->unsetThirdPartySetting('drubricks_core', $key);
      }
      return;
    }
    $block->setThirdPartySetting('drubricks_core', 'size', in_array($values['size'] ?? '', self::SIZES, TRUE) ? $values['size'] : 'narrow');
    $block->setThirdPartySetting('drubricks_core', 'icon', trim((string) ($values['icon'] ?? '')));
    $block->setThirdPartySetting('drubricks_core', 'color', (string) ($values['color'] ?? ''));
  }

}
