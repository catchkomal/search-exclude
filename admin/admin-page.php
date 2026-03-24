<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="se-wrap">

    <header class="se-header">
        <div class="se-header__inner">
            <div class="se-logo">
                <svg width="32" height="32" viewBox="0 0 32 32" fill="none"><rect width="32" height="32" rx="8" fill="#1a1a2e"/><circle cx="14" cy="14" r="6" stroke="#6c63ff" stroke-width="2.2" fill="none"/><line x1="19" y1="19" x2="25" y2="25" stroke="#6c63ff" stroke-width="2.2" stroke-linecap="round"/><line x1="10" y1="14" x2="18" y2="14" stroke="#ff6584" stroke-width="2" stroke-linecap="round"/></svg>
                <span>Search Exclude</span>
                <span class="se-version">v2.0</span>
            </div>
            <p class="se-header__sub">Exclude pages, posts &amp; CPTs from WordPress search <em>and</em> your Algolia index.</p>
        </div>
    </header>

    <?php if ( $updated ) : ?>
    <div class="se-notice se-notice--success">
        <svg width="18" height="18" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="9" stroke="#22c55e" stroke-width="2"/><path d="M6 10.5l3 3 5-5" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Settings saved. Any newly excluded posts have been removed from Algolia.
    </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="se-form">
        <?php wp_nonce_field( 'se_save_settings_action', 'se_nonce' ); ?>
        <input type="hidden" name="action" value="se_save_settings">
        <input type="hidden" name="se_excluded_ids" id="se-hidden-ids"
               value="<?php echo esc_attr( implode( ',', $excluded_ids ) ); ?>">

        <!-- ── ALGOLIA CREDENTIALS ─────────────────────────────── -->
        <section class="se-card se-card--algolia">
            <div class="se-card__head">
                <h2>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z" fill="currentColor" opacity=".3"/><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.8" fill="none"/><path d="M8 12h8M12 8l4 4-4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Algolia Connection
                </h2>
                <div class="se-algolia-actions">
                    <button type="button" id="se-test-btn" class="se-btn-ghost">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="none"><path d="M10 2a8 8 0 100 16A8 8 0 0010 2z" stroke="currentColor" stroke-width="1.8"/><path d="M10 6v4l3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        Test Connection
                    </button>
                    <button type="button" id="se-sync-btn" class="se-btn-ghost se-btn-ghost--danger">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="none"><path d="M4 10a6 6 0 016-6 6 6 0 014.47 2M16 10a6 6 0 01-6 6 6 6 0 01-4.47-2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M14 6l2.47-2L19 6M6 14l-2.47 2L1 14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Force Sync Now
                    </button>
                </div>
            </div>
            <p class="se-card__desc">Enter your Algolia Admin API Key (not the Search-Only key). Excluded posts are removed from the index automatically on save.</p>

            <div id="se-algolia-msg" class="se-algolia-msg" style="display:none;"></div>

            <div class="se-creds-grid">
                <div class="se-field">
                    <label for="se_algolia_app_id">Application ID</label>
                    <input type="text" id="se_algolia_app_id" name="se_algolia_app_id"
                           value="<?php echo esc_attr( $algolia_cfg['app_id'] ); ?>"
                           placeholder="e.g. ABCDE12345" autocomplete="off" spellcheck="false">
                </div>
                <div class="se-field">
                    <label for="se_algolia_admin_key">Admin API Key</label>
                    <div class="se-field-wrap">
                        <input type="password" id="se_algolia_admin_key" name="se_algolia_admin_key"
                               value="<?php echo esc_attr( $algolia_cfg['admin_key'] ); ?>"
                               placeholder="Leave blank to keep existing" autocomplete="new-password" spellcheck="false">
                        <button type="button" class="se-eye" id="se-toggle-key" aria-label="Toggle visibility">
                            <svg width="16" height="16" viewBox="0 0 20 20" fill="none"><ellipse cx="10" cy="10" rx="8" ry="5" stroke="currentColor" stroke-width="1.8"/><circle cx="10" cy="10" r="2.5" stroke="currentColor" stroke-width="1.8"/></svg>
                        </button>
                    </div>
                </div>
                <div class="se-field">
                    <label for="se_algolia_index">Index Name</label>
                    <input type="text" id="se_algolia_index" name="se_algolia_index"
                           value="<?php echo esc_attr( $algolia_cfg['index'] ); ?>"
                           placeholder="e.g. wp_posts" autocomplete="off" spellcheck="false">
                </div>
            </div>
        </section>

        <div class="se-grid">

            <!-- ── INDIVIDUAL POSTS ──────────────────────────────── -->
            <section class="se-card">
                <div class="se-card__head">
                    <h2>
                        <svg width="18" height="18" viewBox="0 0 20 20" fill="none"><rect x="3" y="3" width="14" height="14" rx="3" stroke="currentColor" stroke-width="1.8"/><path d="M7 7h6M7 10h6M7 13h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        Exclude Individual Posts
                    </h2>
                    <span class="se-badge" id="se-count"><?php echo count( $excluded_ids ); ?></span>
                </div>
                <p class="se-card__desc">Search and exclude specific pages, posts, or CPT entries. Each will be deleted from Algolia on save.</p>

                <div class="se-search-box">
                    <div class="se-search-row">
                        <div class="se-search-input-wrap">
                            <svg class="se-search-icon" width="16" height="16" viewBox="0 0 20 20" fill="none"><circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.8"/><path d="M15 15l3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                            <input type="text" id="se-search" placeholder="Search posts, pages, CPTs…" autocomplete="off">
                        </div>
                        <select id="se-type-filter">
                            <option value="any">All types</option>
                            <?php foreach ( $public_types as $slug => $obj ) : ?>
                            <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $obj->labels->name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="se-results" class="se-results" style="display:none;"></div>
                </div>

                <div class="se-excluded-list" id="se-excluded-list">
                    <?php if ( empty( $excluded_posts ) ) : ?>
                    <div class="se-empty" id="se-empty-state">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="#94a3b8" stroke-width="1.5"/><path d="M17 17l3 3" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round"/><path d="M8 11h6M11 8v6" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round"/></svg>
                        <p>No posts excluded yet.<br>Search above to add some.</p>
                    </div>
                    <?php else : ?>
                    <?php foreach ( $excluded_posts as $p ) :
                        $type_obj   = get_post_type_object( $p->post_type );
                        $type_label = $type_obj ? $type_obj->labels->singular_name : $p->post_type;
                    ?>
                    <div class="se-item" data-id="<?php echo esc_attr( $p->ID ); ?>">
                        <div class="se-item__info">
                            <span class="se-item__type"><?php echo esc_html( $type_label ); ?></span>
                            <span class="se-item__title"><?php echo esc_html( $p->post_title ?: '(no title)' ); ?></span>
                        </div>
                        <button type="button" class="se-remove" aria-label="Remove">
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 2l10 10M12 2L2 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        </button>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <!-- ── POST TYPES ────────────────────────────────────── -->
            <section class="se-card">
                <div class="se-card__head">
                    <h2>
                        <svg width="18" height="18" viewBox="0 0 20 20" fill="none"><path d="M3 5h14M3 10h14M3 15h8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        Exclude Post Types
                    </h2>
                </div>
                <p class="se-card__desc">Toggle entire post types. All posts of that type will be removed from Algolia on save.</p>

                <div class="se-types-list">
                    <?php foreach ( $public_types as $slug => $obj ) :
                        $checked = in_array( $slug, $excluded_types, true );
                    ?>
                    <label class="se-toggle-row <?php echo $checked ? 'is-excluded' : ''; ?>">
                        <div class="se-toggle-info">
                            <span class="se-toggle-name"><?php echo esc_html( $obj->labels->name ); ?></span>
                            <span class="se-toggle-slug"><?php echo esc_html( $slug ); ?></span>
                        </div>
                        <div class="se-toggle-control">
                            <span class="se-type-badge <?php echo $checked ? '' : 'se-type-badge--active'; ?>">
                                <?php echo $checked ? 'Excluded' : 'Included'; ?>
                            </span>
                            <label class="se-switch">
                                <input type="checkbox" name="se_excluded_types[]"
                                       value="<?php echo esc_attr( $slug ); ?>"
                                       <?php checked( $checked ); ?>>
                                <span class="se-switch__thumb"></span>
                            </label>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </section>

        </div><!-- .se-grid -->

        <div class="se-footer">
            <button type="submit" class="se-btn-save">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="none"><path d="M4 10.5l4.5 4.5 8-9" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Save &amp; Sync to Algolia
            </button>
            <span class="se-footer__hint">Newly excluded items are removed from Algolia immediately on save.</span>
        </div>

    </form>
</div>
