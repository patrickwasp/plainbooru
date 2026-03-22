<?php
/** @var array $settings  — all current setting values keyed by name */
$roles = ['user', 'trusted'];
?>
<div class="max-w-5xl flex flex-col gap-6">

  <?= $this->partial('admin/nav', ['adminSection' => 'settings', 'currentUser' => $currentUser]) ?>

  <div>
    <h1 class="text-2xl font-bold">Site Settings</h1>
    <p class="text-sm text-muted-foreground mt-1">Changes take effect immediately.</p>
  </div>

  <form action="/admin/settings" method="post" class="flex flex-col gap-8">
    <?= $this->csrfInput() ?>

    <!-- General -->
    <section class="card shadow-sm overflow-hidden p-0 gap-0">
      <div class="px-6 py-4 border-b border-border bg-muted/30">
        <h2 class="text-sm font-semibold">General</h2>
      </div>
      <div class="px-6 py-6 grid gap-6">

        <div class="grid gap-2">
          <label class="text-sm font-medium" for="site_title">Site title</label>
          <input id="site_title" type="text" name="site_title" required
                 value="<?= $this->e($settings['site_title'] ?? 'plainbooru') ?>"
                 class="input h-9 text-sm max-w-sm">
        </div>

        <div class="grid gap-2">
          <label class="text-sm font-medium" for="site_description">Site description</label>
          <input id="site_description" type="text" name="site_description"
                 value="<?= $this->e($settings['site_description'] ?? '') ?>"
                 class="input h-9 text-sm max-w-lg" placeholder="A booru-style media archive">
          <p class="text-xs text-muted-foreground">Used in meta tags and the home page. Leave blank to omit.</p>
        </div>

        <div class="flex items-center gap-3">
          <input id="registration_enabled" type="checkbox" name="registration_enabled" value="1"
                 class="checkbox" <?= ($settings['registration_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
          <label class="text-sm" for="registration_enabled">Allow new user registration</label>
        </div>

        <div class="grid gap-2">
          <label class="text-sm font-medium" for="default_user_role">Default role for new users</label>
          <select id="default_user_role" name="default_user_role" class="input h-9 text-sm max-w-[180px]">
            <?php foreach ($roles as $r): ?>
              <option value="<?= $this->e($r) ?>"<?= ($settings['default_user_role'] ?? 'user') === $r ? ' selected' : '' ?>><?= $this->e($r) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="grid gap-2">
          <label class="text-sm font-medium" for="items_per_page">Items per page</label>
          <input id="items_per_page" type="number" name="items_per_page" min="5" max="100"
                 value="<?= (int)($settings['items_per_page'] ?? 20) ?>"
                 class="input h-9 text-sm w-24">
          <p class="text-xs text-muted-foreground">5 – 100</p>
        </div>

      </div>
    </section>

    <!-- Uploads -->
    <section class="card shadow-sm overflow-hidden p-0 gap-0">
      <div class="px-6 py-4 border-b border-border bg-muted/30">
        <h2 class="text-sm font-semibold">Uploads</h2>
      </div>
      <div class="px-6 py-6 grid gap-6">

        <div class="grid gap-2">
          <label class="text-sm font-medium" for="max_upload_mb">Max upload size (MB)</label>
          <input id="max_upload_mb" type="number" name="max_upload_mb" min="1" max="2048"
                 value="<?= (int)($settings['max_upload_mb'] ?? 50) ?>"
                 class="input h-9 text-sm w-28">
          <p class="text-xs text-muted-foreground">1 – 2048 MB</p>
        </div>

        <div class="grid gap-2">
          <label class="text-sm font-medium" for="allowed_mime_types">Allowed MIME types</label>
          <input id="allowed_mime_types" type="text" name="allowed_mime_types"
                 value="<?= $this->e($settings['allowed_mime_types'] ?? '') ?>"
                 class="input h-9 text-sm max-w-lg">
          <p class="text-xs text-muted-foreground">Comma-separated, e.g. <code>image/jpeg,image/png,video/mp4</code></p>
        </div>

      </div>
    </section>

    <!-- Anonymous permissions -->
    <section class="card shadow-sm overflow-hidden p-0 gap-0">
      <div class="px-6 py-4 border-b border-border bg-muted/30">
        <h2 class="text-sm font-semibold">Anonymous Permissions</h2>
      </div>
      <div class="px-6 py-6 grid gap-4">
        <?php
        $anonFlags = [
            'anon_can_upload'      => 'Allow anonymous uploads',
            'anon_can_comment'     => 'Allow anonymous comments',
            'anon_can_vote'        => 'Allow anonymous votes',
            'anon_can_create_pool' => 'Allow anonymous pool creation',
            'anon_can_edit_tags'   => 'Allow anonymous tag editing',
        ];
        foreach ($anonFlags as $key => $label):
        ?>
          <div class="flex items-center gap-3">
            <input id="<?= $this->e($key) ?>" type="checkbox" name="<?= $this->e($key) ?>" value="1"
                   class="checkbox" <?= ($settings[$key] ?? '0') === '1' ? 'checked' : '' ?>>
            <label class="text-sm" for="<?= $this->e($key) ?>"><?= $this->e($label) ?></label>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Access Control -->
    <section class="card shadow-sm overflow-hidden p-0 gap-0">
      <div class="px-6 py-4 border-b border-border bg-muted/30">
        <h2 class="text-sm font-semibold">Access Control</h2>
      </div>
      <div class="px-6 py-6 grid gap-6">

        <div class="flex items-center gap-3">
          <input id="require_login_to_view" type="checkbox" name="require_login_to_view" value="1"
                 class="checkbox" <?= ($settings['require_login_to_view'] ?? '0') === '1' ? 'checked' : '' ?>>
          <div>
            <label class="text-sm" for="require_login_to_view">Require login to browse</label>
            <p class="text-xs text-muted-foreground">Anonymous visitors are redirected to the login page.</p>
          </div>
        </div>

        <div class="grid gap-3">
          <div class="flex items-center gap-3">
            <input id="maintenance_mode" type="checkbox" name="maintenance_mode" value="1"
                   class="checkbox" <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
            <div>
              <label class="text-sm" for="maintenance_mode">Maintenance mode</label>
              <p class="text-xs text-muted-foreground">Non-admins see a maintenance page (HTTP 503). Admins can still browse normally.</p>
            </div>
          </div>

          <div class="grid gap-2 pl-7">
            <label class="text-sm font-medium" for="maintenance_message">Maintenance message</label>
            <input id="maintenance_message" type="text" name="maintenance_message"
                   value="<?= $this->e($settings['maintenance_message'] ?? 'Site is under maintenance. Please check back soon.') ?>"
                   class="input h-9 text-sm max-w-lg">
          </div>
        </div>

      </div>
    </section>

    <!-- Content Policy -->
    <section class="card shadow-sm overflow-hidden p-0 gap-0">
      <div class="px-6 py-4 border-b border-border bg-muted/30">
        <h2 class="text-sm font-semibold">Content Policy</h2>
      </div>
      <div class="px-6 py-6 grid gap-6">

        <div class="grid gap-2">
          <label class="text-sm font-medium" for="max_tags_per_media">Max tags per item</label>
          <input id="max_tags_per_media" type="number" name="max_tags_per_media" min="1" max="200"
                 value="<?= (int)($settings['max_tags_per_media'] ?? 50) ?>"
                 class="input h-9 text-sm w-24">
          <p class="text-xs text-muted-foreground">1 – 200. Enforced when adding tags to a media item.</p>
        </div>

      </div>
    </section>

    <!-- Rate Limits -->
    <section class="card shadow-sm overflow-hidden p-0 gap-0">
      <div class="px-6 py-4 border-b border-border bg-muted/30">
        <h2 class="text-sm font-semibold">Rate Limits</h2>
      </div>
      <div class="px-6 py-6 grid gap-6">

        <div class="grid gap-2">
          <label class="text-sm font-medium" for="rate_limit_uploads_per_hour">Uploads per hour</label>
          <input id="rate_limit_uploads_per_hour" type="number" name="rate_limit_uploads_per_hour" min="1" max="10000"
                 value="<?= (int)($settings['rate_limit_uploads_per_hour'] ?? 20) ?>"
                 class="input h-9 text-sm w-28">
          <p class="text-xs text-muted-foreground">Per user (or IP if anonymous). 1 – 10000.</p>
        </div>

        <div class="grid gap-2">
          <label class="text-sm font-medium" for="rate_limit_comments_per_hour">Comments per hour</label>
          <input id="rate_limit_comments_per_hour" type="number" name="rate_limit_comments_per_hour" min="1" max="10000"
                 value="<?= (int)($settings['rate_limit_comments_per_hour'] ?? 30) ?>"
                 class="input h-9 text-sm w-28">
          <p class="text-xs text-muted-foreground">Per user (or IP if anonymous). 1 – 10000.</p>
        </div>

        <div class="grid gap-2">
          <label class="text-sm font-medium" for="rate_limit_api_per_minute">API requests per minute</label>
          <input id="rate_limit_api_per_minute" type="number" name="rate_limit_api_per_minute" min="1" max="10000"
                 value="<?= (int)($settings['rate_limit_api_per_minute'] ?? 300) ?>"
                 class="input h-9 text-sm w-28">
          <p class="text-xs text-muted-foreground">Per API token user. 1 – 10000.</p>
        </div>

      </div>
    </section>

    <div>
      <button type="submit" class="btn-primary">Save settings</button>
    </div>

  </form>

</div>
