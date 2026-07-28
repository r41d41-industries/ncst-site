<?php

declare(strict_types=1);

/** @var string $adminPageTitle */
$adminPageTitle = $adminPageTitle ?? 'Admin';
/** @var string $adminSection posts|media|reports|settings */
$adminSection = $adminSection ?? 'posts';
/** @var string $adminPanelTitle */
$adminPanelTitle = $adminPanelTitle ?? 'Posts';
/** @var bool $adminShowAdd */
$adminShowAdd = $adminShowAdd ?? ($adminSection === 'posts');
/** @var string|null $adminCategoryFilter */
$adminCategoryFilter = isset($adminCategoryFilter) ? strtoupper((string) $adminCategoryFilter) : null;
if ($adminCategoryFilter === '') {
    $adminCategoryFilter = null;
}
/** @var string|null $adminStatusFilter draft|published|trash */
$adminStatusFilter = isset($adminStatusFilter) ? strtolower((string) $adminStatusFilter) : null;
if ($adminStatusFilter !== null && !in_array($adminStatusFilter, ['draft', 'published', 'trash'], true)) {
    $adminStatusFilter = null;
}
/** @var string|null $adminTypeFilter incident|article */
$adminTypeFilter = isset($adminTypeFilter) ? strtolower((string) $adminTypeFilter) : null;
if ($adminTypeFilter === 'all' || $adminTypeFilter === '') {
    $adminTypeFilter = null;
}
if ($adminTypeFilter !== null && !in_array($adminTypeFilter, ['incident', 'article'], true)) {
    $adminTypeFilter = null;
}
/** @var string $adminPostsPage all|facebook|'' */
$adminPostsPage = isset($adminPostsPage) ? (string) $adminPostsPage : '';

$username = (string) (auth_username() ?? 'Admin');
$displayName = (string) (auth_display_name() ?: $username);
$initial = strtoupper(substr($displayName !== '' ? $displayName : $username, 0, 1));
$postCount = isset($posts) && is_array($posts) ? count($posts) : null;
$navCategoriesWithPosts = categories_with_posts();
$statusCounts = ['all' => 0, 'draft' => 0, 'published' => 0, 'trash' => 0];
$typeCounts = ['incident' => 0, 'article' => 0];
if ($adminSection === 'posts') {
    try {
        $statusCounts = posts_status_counts();
    } catch (Throwable $e) {
        $statusCounts['all'] = $postCount ?? 0;
    }
    try {
        $typeCounts = posts_type_counts();
    } catch (Throwable $e) {
        // Keep zeros if counts unavailable.
    }
}
$allPostsCount = $statusCounts['all'];
$isAllPostsActive = $adminSection === 'posts'
    && $adminCategoryFilter === null
    && $adminStatusFilter === null
    && $adminTypeFilter === null
    && ($adminPostsPage === '' || $adminPostsPage === 'all');
$isIncidentsActive = $adminSection === 'posts'
    && $adminStatusFilter === null
    && $adminCategoryFilter === null
    && $adminTypeFilter === 'incident'
    && $adminPostsPage === '';
$isArticlesActive = $adminSection === 'posts'
    && $adminStatusFilter === null
    && $adminCategoryFilter === null
    && $adminTypeFilter === 'article'
    && $adminPostsPage === '';
$isFacebookPostsActive = $adminSection === 'posts'
    && $adminPostsPage === 'facebook';
$adminMediaPage = isset($adminMediaPage) ? (string) $adminMediaPage : '';
$panelSearchPlaceholders = [
    'posts' => 'Search posts…',
    'media' => 'Search media…',
    'reports' => 'Search reports…',
    'settings' => 'Search settings…',
];
$panelSearchPlaceholder = $panelSearchPlaceholders[$adminSection] ?? 'Search…';
$panelSearchLabel = $adminSection === 'posts' ? 'Filter posts' : 'Filter ' . $adminSection;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($adminPageTitle) ?> — NCST Admin</title>
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <script>window.NCST_ADMIN = { csrf: <?= json_encode(csrf_token(), JSON_UNESCAPED_SLASHES) ?> };</script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/admin.css">
  <link rel="stylesheet" href="/assets/css/admin-media.css">
  <?php if (!empty($adminExtraHead)) {
      echo $adminExtraHead;
  } ?>
  <script>
    (function () {
      try {
        if (window.matchMedia("(max-width: 899px)").matches) return;
        if (localStorage.getItem("ncst-admin-panel-collapsed") === "1") {
          document.documentElement.classList.add("admin-panel-collapsed-pending");
        }
      } catch (e) {}
    })();
  </script>
</head>
<body class="admin-body">
  <div class="admin-app">
    <button type="button" id="admin-overlay" class="admin-overlay" hidden aria-label="Close navigation"></button>

    <aside id="admin-sidenav" class="admin-sidenav" aria-label="Admin navigation">
      <div class="admin-rail">
        <a class="admin-rail__brand" href="/admin/" title="NCST Admin" aria-label="NCST Admin home">NC</a>
        <nav class="admin-rail__nav" aria-label="Sections">
          <button
            type="button"
            class="admin-rail__btn<?= $adminSection === 'posts' ? ' is-active' : '' ?>"
            data-rail-section="posts"
            <?= $adminSection === 'posts' ? 'aria-current="page"' : '' ?>
            aria-label="Posts"
            title="Posts"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M4 6h16M4 12h16M4 18h10" stroke-linecap="round"/>
            </svg>
          </button>
          <button
            type="button"
            class="admin-rail__btn<?= $adminSection === 'media' ? ' is-active' : '' ?>"
            data-rail-section="media"
            <?= $adminSection === 'media' ? 'aria-current="page"' : '' ?>
            aria-label="Media"
            title="Media"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <rect x="3" y="5" width="18" height="14" rx="2"/>
              <circle cx="8.5" cy="10.5" r="1.5"/>
              <path d="M21 16l-5-5-4 4-2-2-5 5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
          <button
            type="button"
            class="admin-rail__btn<?= $adminSection === 'reports' ? ' is-active' : '' ?>"
            data-rail-section="reports"
            aria-label="Reports (coming soon)"
            title="Reports — coming soon"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M4 19V5M4 19h16" stroke-linecap="round"/>
              <path d="M8 16v-5M12 16V8M16 16v-3" stroke-linecap="round"/>
            </svg>
          </button>
          <button
            type="button"
            class="admin-rail__btn<?= $adminSection === 'settings' ? ' is-active' : '' ?>"
            data-rail-section="settings"
            <?= $adminSection === 'settings' ? 'aria-current="page"' : '' ?>
            aria-label="Settings"
            title="Settings"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <circle cx="12" cy="12" r="3"/>
              <path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6l1.4 1.4M17 17l1.4 1.4M5.6 18.4L7 17M17 7l1.4-1.4" stroke-linecap="round"/>
            </svg>
          </button>
        </nav>
        <div class="admin-rail__spacer" aria-hidden="true"></div>
        <button
          type="button"
          id="admin-panel-toggle"
          class="admin-rail__btn"
          aria-expanded="true"
          aria-controls="admin-panel"
          aria-label="Collapse navigation panel"
          title="Collapse panel"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/>
          </svg>
        </button>
        <button type="button" id="admin-nav-close" class="admin-rail__btn" aria-label="Close navigation" title="Close">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round"/>
          </svg>
        </button>
      </div>

      <div id="admin-panel" class="admin-panel">
        <div class="admin-panel__header">
          <h2 id="admin-panel-title" class="admin-panel__title"><?= e($adminPanelTitle) ?></h2>
          <div
            id="admin-panel-add"
            class="admin-panel__add-wrap<?= $adminShowAdd ? '' : ' is-hidden' ?>"
            <?= $adminShowAdd ? '' : 'hidden aria-hidden="true"' ?>
          >
            <button
              type="button"
              id="admin-panel-add-trigger"
              class="admin-panel__add"
              aria-expanded="false"
              aria-haspopup="menu"
              aria-controls="admin-panel-add-menu"
              aria-label="Create new post"
              title="Create new post"
            >
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M12 5v14M5 12h14" stroke-linecap="round"/>
              </svg>
            </button>
            <ul id="admin-panel-add-menu" class="admin-panel__add-menu" role="menu" hidden>
              <li role="none">
                <a role="menuitem" href="/admin/post_incident.php">New incident</a>
              </li>
              <li role="none">
                <a role="menuitem" href="/admin/post_article.php">New article</a>
              </li>
            </ul>
          </div>
        </div>

        <div class="admin-panel__search">
          <svg class="admin-panel__search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <circle cx="11" cy="11" r="7"/>
            <path d="M20 20l-3.5-3.5" stroke-linecap="round"/>
          </svg>
          <label class="admin-sr-only" for="admin-panel-search"><?= e($panelSearchLabel) ?></label>
          <input
            id="admin-panel-search"
            class="admin-panel__search-input"
            type="search"
            placeholder="<?= e($panelSearchPlaceholder) ?>"
            autocomplete="off"
            aria-label="<?= e($panelSearchLabel) ?>"
          >
        </div>

        <div class="admin-panel__body">
          <div class="admin-panel__sections">
            <div data-panel-section="posts" class="<?= $adminSection === 'posts' ? 'is-active' : '' ?>">
              <ul class="admin-panel__nav">
                <li>
                  <a
                    class="admin-panel__link<?= $isAllPostsActive ? ' is-active' : '' ?>"
                    href="/admin/"
                    <?= $isAllPostsActive ? 'aria-current="page"' : '' ?>
                  >
                    All posts
                    <span class="admin-panel__badge"><?= e((string) $allPostsCount) ?></span>
                  </a>
                </li>
                <li>
                  <a
                    class="admin-panel__link<?= $isIncidentsActive ? ' is-active' : '' ?>"
                    href="/admin/?type=incident"
                    <?= $isIncidentsActive ? 'aria-current="page"' : '' ?>
                  >
                    All Incidents
                    <span class="admin-panel__badge"><?= e((string) $typeCounts['incident']) ?></span>
                  </a>
                </li>
                <li>
                  <a
                    class="admin-panel__link<?= $isArticlesActive ? ' is-active' : '' ?>"
                    href="/admin/?type=article"
                    <?= $isArticlesActive ? 'aria-current="page"' : '' ?>
                  >
                    All Articles
                    <span class="admin-panel__badge"><?= e((string) $typeCounts['article']) ?></span>
                  </a>
                </li>
                <li>
                  <a
                    class="admin-panel__link<?= $isFacebookPostsActive ? ' is-active' : '' ?>"
                    href="/admin/facebook/posts.php"
                    <?= $isFacebookPostsActive ? 'aria-current="page"' : '' ?>
                  >
                    Facebook
                  </a>
                </li>
                <li>
                  <button
                    type="button"
                    class="admin-panel__toggle"
                    data-nav-toggle
                    aria-expanded="false"
                    aria-controls="admin-posts-sub"
                  >
                    Library
                    <svg class="admin-panel__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                      <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </button>
                  <ul id="admin-posts-sub" class="admin-panel__sub" hidden>
                    <?php
                      $statusLinks = [
                          'draft' => ['label' => 'Drafts', 'count' => $statusCounts['draft']],
                          'published' => ['label' => 'Published', 'count' => $statusCounts['published']],
                          'trash' => ['label' => 'Trash', 'count' => $statusCounts['trash']],
                      ];
                    ?>
                    <?php foreach ($statusLinks as $statusKey => $statusLink): ?>
                      <?php $isStatusActive = $adminSection === 'posts' && $adminStatusFilter === $statusKey; ?>
                      <li>
                        <a
                          class="admin-panel__link<?= $isStatusActive ? ' is-active' : '' ?>"
                          href="/admin/?status=<?= e($statusKey) ?>"
                          <?= $isStatusActive ? 'aria-current="page"' : '' ?>
                        >
                          <?= e($statusLink['label']) ?>
                          <span class="admin-panel__badge"><?= e((string) $statusLink['count']) ?></span>
                        </a>
                      </li>
                    <?php endforeach; ?>
                    <li>
                      <button
                        type="button"
                        class="admin-panel__toggle"
                        data-nav-toggle
                        aria-expanded="true"
                        aria-controls="admin-categories-sub"
                      >
                        Categories
                        <svg class="admin-panel__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                          <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                      </button>
                      <ul id="admin-categories-sub" class="admin-panel__sub admin-panel__sub--nested is-open">
                        <?php if ($navCategoriesWithPosts === []): ?>
                          <li>
                            <span class="admin-panel__link is-disabled" aria-disabled="true">No posts yet</span>
                          </li>
                        <?php else: ?>
                          <?php foreach ($navCategoriesWithPosts as $navCat): ?>
                            <?php
                              $navSlug = (string) $navCat['slug'];
                              $navName = (string) $navCat['name'];
                              $navCount = (int) $navCat['count'];
                              $isCatActive = $adminSection === 'posts'
                                  && $adminStatusFilter === null
                                  && $adminTypeFilter === null
                                  && $adminCategoryFilter === $navSlug;
                            ?>
                            <li>
                              <a
                                class="admin-panel__link<?= $isCatActive ? ' is-active' : '' ?>"
                                href="/admin/?category=<?= e(rawurlencode($navSlug)) ?>"
                                <?= $isCatActive ? 'aria-current="page"' : '' ?>
                              ><?= e($navName) ?> (<?= e((string) $navCount) ?>)</a>
                            </li>
                          <?php endforeach; ?>
                        <?php endif; ?>
                      </ul>
                    </li>
                  </ul>
                </li>
              </ul>
            </div>

            <div data-panel-section="media" class="<?= $adminSection === 'media' ? 'is-active' : '' ?>">
              <ul class="admin-panel__nav" aria-label="Media">
                <li>
                  <a
                    class="admin-panel__link<?= $adminMediaPage === 'all' ? ' is-active' : '' ?>"
                    href="/admin/media/"
                    <?= $adminMediaPage === 'all' ? 'aria-current="page"' : '' ?>
                  >All media</a>
                </li>
                <li>
                  <?php
                    $mediaKindLinks = [
                        'image' => ['label' => 'Images', 'href' => '/admin/media/?kind=image'],
                        'audio' => ['label' => 'Audio', 'href' => '/admin/media/?kind=audio'],
                        'document' => ['label' => 'Documents', 'href' => '/admin/media/?kind=document'],
                    ];
                  ?>
                  <button
                    type="button"
                    class="admin-panel__toggle"
                    data-nav-toggle
                    aria-expanded="true"
                    aria-controls="admin-media-by-type"
                  >
                    By Type
                    <svg class="admin-panel__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                      <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </button>
                  <ul id="admin-media-by-type" class="admin-panel__sub is-open">
                    <?php foreach ($mediaKindLinks as $mediaKindKey => $mediaKindLink): ?>
                      <?php $isMediaKindActive = $adminMediaPage === $mediaKindKey; ?>
                      <li>
                        <a
                          class="admin-panel__link<?= $isMediaKindActive ? ' is-active' : '' ?>"
                          href="<?= e($mediaKindLink['href']) ?>"
                          <?= $isMediaKindActive ? 'aria-current="page"' : '' ?>
                        ><?= e($mediaKindLink['label']) ?></a>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </li>
                <li>
                  <a
                    class="admin-panel__link<?= $adminMediaPage === 'galleries' ? ' is-active' : '' ?>"
                    href="/admin/media/galleries.php"
                    <?= $adminMediaPage === 'galleries' ? 'aria-current="page"' : '' ?>
                  >Galleries</a>
                </li>
                <li>
                  <a
                    class="admin-panel__link<?= $adminMediaPage === 'playlists' ? ' is-active' : '' ?>"
                    href="/admin/media/playlists.php"
                    <?= $adminMediaPage === 'playlists' ? 'aria-current="page"' : '' ?>
                  >Playlists</a>
                </li>
              </ul>
            </div>

            <div data-panel-section="reports" class="<?= $adminSection === 'reports' ? 'is-active' : '' ?>">
              <p class="admin-panel__placeholder">Reports are coming soon.</p>
              <ul class="admin-panel__nav" aria-label="Reports placeholders">
                <li><span class="admin-panel__link is-disabled" aria-disabled="true">Overview</span></li>
                <li><span class="admin-panel__link is-disabled" aria-disabled="true">Engagement</span></li>
              </ul>
            </div>

            <div data-panel-section="settings" class="<?= $adminSection === 'settings' ? 'is-active' : '' ?>">
              <?php
                $adminSettingsPage = isset($adminSettingsPage) ? (string) $adminSettingsPage : '';
                $isSettingsPostsActive = $adminSettingsPage === 'posts';
                $isSettingsShortcodesActive = $adminSettingsPage === 'shortcodes';
                $isSettingsAgenciesActive = $adminSettingsPage === 'agencies';
                $settingsPostsSubOpen = $isSettingsPostsActive || $isSettingsShortcodesActive || $isSettingsAgenciesActive;
                $isFbCredentialsActive = $adminSettingsPage === 'facebook-credentials';
                $isFbCronActive = $adminSettingsPage === 'facebook-cron';
                $isFbImportActive = $adminSettingsPage === 'facebook-import';
                $isFbHashtagsActive = $adminSettingsPage === 'facebook-hashtags';
                $settingsFacebookSubOpen = $isFbCredentialsActive || $isFbCronActive || $isFbImportActive || $isFbHashtagsActive;
              ?>
              <ul class="admin-panel__nav" aria-label="Settings">
                <li>
                  <span class="admin-panel__link is-disabled" aria-disabled="true" title="Coming soon">
                    General
                  </span>
                </li>
                <li>
                  <button
                    type="button"
                    class="admin-panel__toggle"
                    data-nav-toggle
                    aria-expanded="<?= $settingsPostsSubOpen ? 'true' : 'false' ?>"
                    aria-controls="admin-settings-posts-sub"
                  >
                    Posts
                    <svg class="admin-panel__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                      <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </button>
                  <ul
                    id="admin-settings-posts-sub"
                    class="admin-panel__sub<?= $settingsPostsSubOpen ? ' is-open' : '' ?>"
                    <?= $settingsPostsSubOpen ? '' : 'hidden' ?>
                  >
                    <li>
                      <a
                        class="admin-panel__link<?= $isSettingsPostsActive ? ' is-active' : '' ?>"
                        href="/admin/settings/posts.php"
                        <?= $isSettingsPostsActive ? 'aria-current="page"' : '' ?>
                      >Categories</a>
                    </li>
                    <li>
                      <a
                        class="admin-panel__link<?= $isSettingsShortcodesActive ? ' is-active' : '' ?>"
                        href="/admin/settings/shortcodes.php"
                        <?= $isSettingsShortcodesActive ? 'aria-current="page"' : '' ?>
                      >Shortcodes</a>
                    </li>
                    <li>
                      <a
                        class="admin-panel__link<?= $isSettingsAgenciesActive ? ' is-active' : '' ?>"
                        href="/admin/settings/agencies.php"
                        <?= $isSettingsAgenciesActive ? 'aria-current="page"' : '' ?>
                      >Agencies</a>
                    </li>
                  </ul>
                </li>
                <li>
                  <a
                    class="admin-panel__link<?= $adminSettingsPage === 'open-graph' ? ' is-active' : '' ?>"
                    href="/admin/settings/open-graph.php"
                    <?= $adminSettingsPage === 'open-graph' ? 'aria-current="page"' : '' ?>
                  >Open Graph</a>
                </li>
                <li>
                  <button
                    type="button"
                    class="admin-panel__toggle"
                    data-nav-toggle
                    aria-expanded="<?= $settingsFacebookSubOpen ? 'true' : 'false' ?>"
                    aria-controls="admin-settings-facebook-sub"
                  >
                    Facebook Sync
                    <svg class="admin-panel__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                      <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </button>
                  <ul
                    id="admin-settings-facebook-sub"
                    class="admin-panel__sub<?= $settingsFacebookSubOpen ? ' is-open' : '' ?>"
                    <?= $settingsFacebookSubOpen ? '' : 'hidden' ?>
                  >
                    <li>
                      <a
                        class="admin-panel__link<?= $isFbCredentialsActive ? ' is-active' : '' ?>"
                        href="/admin/settings/facebook/credentials.php"
                        <?= $isFbCredentialsActive ? 'aria-current="page"' : '' ?>
                      >Credentials</a>
                    </li>
                    <li>
                      <a
                        class="admin-panel__link<?= $isFbCronActive ? ' is-active' : '' ?>"
                        href="/admin/settings/facebook/cron.php"
                        <?= $isFbCronActive ? 'aria-current="page"' : '' ?>
                      >Cron</a>
                    </li>
                    <li>
                      <a
                        class="admin-panel__link<?= $isFbImportActive ? ' is-active' : '' ?>"
                        href="/admin/settings/facebook/import.php"
                        <?= $isFbImportActive ? 'aria-current="page"' : '' ?>
                      >Import</a>
                    </li>
                    <li>
                      <a
                        class="admin-panel__link<?= $isFbHashtagsActive ? ' is-active' : '' ?>"
                        href="/admin/settings/facebook/hashtags.php"
                        <?= $isFbHashtagsActive ? 'aria-current="page"' : '' ?>
                      >Hashtags</a>
                    </li>
                  </ul>
                </li>
                <li>
                  <span class="admin-panel__link is-disabled" aria-disabled="true" title="Coming soon">
                    Users
                  </span>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <div class="admin-user">
          <button
            type="button"
            id="admin-user-trigger"
            class="admin-user__trigger"
            aria-expanded="false"
            aria-haspopup="menu"
            aria-controls="admin-user-menu"
          >
            <span class="admin-user__avatar" aria-hidden="true"><?= e($initial) ?></span>
            <span class="admin-user__meta">
              <span class="admin-user__name"><?= e($displayName) ?></span>
              <span class="admin-user__role">Administrator</span>
            </span>
            <svg class="admin-user__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
          <ul id="admin-user-menu" class="admin-user__menu" role="menu" hidden>
            <li role="none">
              <button type="button" role="menuitem" id="admin-manage-account" data-account-open>
                Manage account
              </button>
            </li>
            <li role="none">
              <a role="menuitem" href="/">View public feed</a>
            </li>
            <li role="none">
              <a role="menuitem" href="/admin/logout.php">Log out</a>
            </li>
          </ul>
        </div>
      </div>
    </aside>

    <div class="admin-main">
      <header class="admin-topbar">
        <button type="button" id="admin-nav-open" class="admin-topbar__toggle" aria-expanded="false" aria-controls="admin-sidenav" aria-label="Open navigation">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/>
          </svg>
        </button>
        <p class="admin-topbar__title"><?= e($adminPanelTitle) ?></p>
      </header>
      <main class="admin-content" id="admin-content">
