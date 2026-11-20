<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Url;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\drubricks_core\Entity\DrubricksEntityInterface;
use Drupal\drubricks_core\Render\TopBarVisibility;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\drubricks_core\KitInfo;

/**
 * Hooks of drubricks_core.
 */
final class DrubricksCoreHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly AdminContext $adminContext,
    private readonly KitInfo $kit,
    private readonly AccountInterface $currentUser,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Implements hook_library_info_alter().
   *
   * Core's JS translation file is loaded as it is, not as an aggregate. Core
   * gives a library that is alone in its group an aggregate URL of its own
   * (?libraries=locale/translations). That request has no other JS files, so
   * LocaleJs::jsTranslate() returns no translation file, the group is empty
   * and the asset controller answers 400: the page runs without translated
   * JS strings. The file is small and already one file per language.
   *
   * @see https://www.drupal.org/project/drupal/issues/3232810
   */
  #[Hook('library_info_alter')]
  public function libraryInfoAlter(array &$libraries, string $extension): void {
    if ($extension === 'locale' && isset($libraries['translations']['js']['locale.translation.js'])) {
      $libraries['translations']['js']['locale.translation.js']['preprocess'] = FALSE;
    }
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'drubricks_screen' => [
        'variables' => ['entity_type' => NULL, 'list' => NULL, 'detail' => NULL, 'inspector' => NULL],
      ],
      'drubricks_list' => [
        'variables' => [
          'title' => NULL,
          'subtitle' => NULL,
          'rows' => [],
          'add' => NULL,
          'search' => '',
          'search_attributes' => [],
          'pager' => NULL,
          'views' => [],
        ],
      ],
      'drubricks_detail' => [
        'variables' => [
          'title' => NULL,
          'actions' => [],
          'content' => NULL,
          'empty' => NULL,
          'back_url' => NULL,
          'inspector' => FALSE,
        ],
      ],
      'drubricks_inspector' => ['variables' => ['title' => NULL, 'content' => NULL, 'open' => FALSE, 'actions' => []]],
      'drubricks_facts' => ['variables' => ['facts' => [], 'title' => '', 'first' => FALSE]],
      'drubricks_fields' => ['variables' => ['content' => []]],
      'drubricks_sidebar' => ['variables' => ['brand' => NULL, 'sections' => [], 'account' => []]],
      'drubricks_home' => [
        'variables' => [
          'title' => NULL,
          'name' => NULL,
          'date' => NULL,
          'stats' => [],
          'panels' => [],
          'sections' => [],
        ],
      ],
      'drubricks_state' => ['variables' => ['icon' => 'info', 'title' => NULL, 'message' => NULL]],
      'drubricks_table' => [
        'variables' => [
          'title' => NULL,
          'subtitle' => NULL,
          'views' => [],
          'add' => NULL,
          'filters' => NULL,
          'table' => NULL,
          'table_attributes' => [],
          'inspector' => NULL,
        ],
      ],
      'drubricks_board' => [
        'variables' => [
          'title' => NULL,
          'subtitle' => NULL,
          'views' => [],
          'add' => NULL,
          'filter' => NULL,
          'board' => [],
          'inspector' => NULL,
        ],
      ],
      'drubricks_calendar' => [
        'variables' => ['title' => NULL, 'views' => [], 'add' => NULL, 'calendar' => [], 'inspector' => NULL],
      ],
      'drubricks_views_list' => ['variables' => ['view' => NULL, 'options' => [], 'rows' => []]],
    ];
  }

  /**
   * Implements hook_entity_insert().
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    $this->logOperation($entity, 'created');
  }

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    $this->logOperation($entity, 'updated');
  }

  /**
   * Implements hook_entity_delete().
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    $this->logOperation($entity, 'deleted');
  }

  /**
   * Writes one log entry (dblog) per create / update / delete of a record.
   *
   * Channel: the entity type id (e.g. crm_deal), so the "Recent log
   * messages" report can be filtered per entity type. The current user is
   * stored by the logger itself.
   */
  private function logOperation(EntityInterface $entity, string $operation): void {
    // Records created by recipes (demo content) are not user operations.
    if (!$entity instanceof DrubricksEntityInterface || RecipeRunner::isApplying()) {
      return;
    }
    $context = [
      '@type' => $entity->getEntityType()->getSingularLabel(),
      '%label' => $entity->label(),
      '@id' => $entity->id(),
    ];
    if ($operation !== 'deleted' && $entity->hasLinkTemplate('canonical')) {
      $context['link'] = $entity->toLink($this->t('View'))->toString();
    }
    $logger = $this->loggerFactory->get($entity->getEntityTypeId());
    match ($operation) {
      'created' => $logger->info('Created @type %label (id @id).', $context),
      'updated' => $logger->info('Updated @type %label (id @id).', $context),
      default => $logger->notice('Deleted @type %label (id @id).', $context),
    };
  }

  /**
   * Implements hook_form_FORM_ID_alter() for user_login_form.
   *
   * Without a ?destination=, a login lands on the app home, not the profile.
   */
  #[Hook('form_user_login_form_alter')]
  public function userLoginFormAlter(array &$form, FormStateInterface $form_state): void {
    $form['#submit'][] = [self::class, 'loginRedirect'];
  }

  /**
   * Submit handler: redirect to the app home.
   */
  public static function loginRedirect(array &$form, FormStateInterface $form_state): void {
    if (!\Drupal::request()->query->has('destination')) {
      $form_state->setRedirect('drubricks_core.home');
    }
  }

  /**
   * Implements hook_element_info_alter().
   *
   * The Navigation module's top bar only on admin pages (TopBarVisibility):
   * app pages have their own toolbar; on admin pages the admin theme shows
   * local actions, form actions and the breadcrumb in it. Navigation adds it
   * in a page_top hook that must run last, so it is switched off at the
   * element level. The admin sidebar stays.
   */
  #[Hook('element_info_alter')]
  public function elementInfoAlter(array &$info): void {
    if (isset($info['top_bar'])) {
      array_unshift($info['top_bar']['#pre_render'], [TopBarVisibility::class, 'preRender']);
    }
  }

  /**
   * Implements hook_block_build_BASE_BLOCK_ID_alter() for local_tasks_block.
   *
   * Navigation hides the local tasks block when it shows the tabs in its top
   * bar; app pages have no top bar, so there the tabs (View / Edit …) stay.
   */
  #[Hook('block_build_local_tasks_block_alter', order: new OrderAfter(modules: ['navigation']))]
  public function localTasksBlockAlter(array &$build, BlockPluginInterface $block): void {
    if (!$this->adminContext->isAdminRoute()) {
      unset($build['#access']);
    }
  }

  /**
   * Implements hook_page_attachments().
   *
   * The JS bridge: drupalSettings.drubricks feeds UI.init() and the shell
   * navigation (endpoints, CSRF token URL, locale, currency, kit strings).
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    $route = $this->routeMatch->getRouteName();
    if (in_array($route, ['user.login', 'user.pass', 'user.register', 'user.reset.form', 'user.logout.confirm'], TRUE)) {
      // Gin Login pages: admin theme + brand CSS, no app shell.
      $attachments['#attached']['library'][] = 'drubricks_core/login';
      $attachments['#cache']['contexts'][] = 'route';
      return;
    }
    // Core's admin sidebar: the current page's trail (any route with it).
    if ($this->currentUser->hasPermission('access navigation')) {
      $attachments['#attached']['library'][] = 'drubricks_core/navigation_active_trail';
    }
    $attachments['#cache']['contexts'][] = 'user.permissions';
    if ($this->adminContext->isAdminRoute()) {
      return;
    }
    $attachments['#attached']['library'][] = 'drubricks_core/shell';
    $urls = [];
    $routes = [
      'sidebarUrl' => 'drubricks_core.sidebar',
      'csrfTokenUrl' => 'system.csrftoken',
      'loginUrl' => 'user.login',
    ];
    foreach ($routes as $key => $route_name) {
      $url = Url::fromRoute($route_name)->toString(TRUE);
      $urls[$key] = $url->getGeneratedUrl();
      CacheableMetadata::createFromRenderArray($attachments)->merge(CacheableMetadata::createFromObject($url))->applyTo($attachments);
    }
    $attachments['#attached']['drupalSettings']['drubricks'] = $urls + [
      'ui' => $this->kit->initOptions(),
      'authenticated' => $this->currentUser->isAuthenticated(),
      'siteName' => (string) $this->configFactory->get('system.site')->get('name'),
    ];
    $attachments['#cache']['contexts'][] = 'languages:language_interface';
    $attachments['#cache']['contexts'][] = 'user.roles:authenticated';
    $attachments['#cache']['tags'][] = 'config:drubricks_core.settings';
    $attachments['#cache']['tags'][] = 'config:system.site';
  }

}
