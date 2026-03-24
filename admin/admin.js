/* Search Exclude — Admin JS */
(function ($) {
    'use strict';

    /* ── State ──────────────────────────────────────────────── */
    var excludedIds  = SE.excluded_ids.slice();   // array of ints from PHP
    var searchTimer  = null;

    /* ── Helpers ────────────────────────────────────────────── */
    function syncHiddenInput() {
        $('#se-hidden-ids').val( excludedIds.join(',') );
        $('#se-count').text( excludedIds.length );
    }

    function isExcluded(id) {
        return excludedIds.indexOf( parseInt(id) ) !== -1;
    }

    function addPost(post) {
        if ( isExcluded(post.id) ) return;
        excludedIds.push( post.id );
        syncHiddenInput();

        // Remove empty state if present
        $('#se-empty-state').remove();

        // Build item HTML
        var $item = $(
            '<div class="se-item" data-id="' + post.id + '">' +
                '<div class="se-item__info">' +
                    '<span class="se-item__type">' + escHtml(post.type) + '</span>' +
                    '<span class="se-item__title">' + escHtml(post.title) + '</span>' +
                '</div>' +
                '<button type="button" class="se-remove" aria-label="Remove">' +
                    '<svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 2l10 10M12 2L2 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>' +
                '</button>' +
            '</div>'
        );
        $('#se-excluded-list').prepend( $item );

        // Mark result row as added
        $('.se-result-item[data-id="' + post.id + '"]').addClass('is-added');
    }

    function removePost(id) {
        id = parseInt(id);
        excludedIds = excludedIds.filter(function(i){ return i !== id; });
        syncHiddenInput();

        // Un-mark result row
        $('.se-result-item[data-id="' + id + '"]').removeClass('is-added');

        // Show empty state if no items left
        if ( excludedIds.length === 0 ) {
            $('#se-excluded-list').html(
                '<div class="se-empty" id="se-empty-state">' +
                    '<svg width="40" height="40" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="#94a3b8" stroke-width="1.5"/><path d="M17 17l3 3" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round"/><path d="M8 11h6M11 8v6" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round"/></svg>' +
                    '<p>No posts excluded yet.<br>Search above to add some.</p>' +
                '</div>'
            );
        }
    }

    function escHtml(str) {
        return $('<span>').text(str).html();
    }

    /* ── Search ─────────────────────────────────────────────── */
    function doSearch() {
        var term = $('#se-search').val().trim();
        var type = $('#se-type-filter').val();
        var $results = $('#se-results');

        $results.html('<div class="se-result-empty">Searching…</div>').show();

        $.ajax({
            url:      SE.ajaxurl,
            method:   'GET',
            data: {
                action:    'se_search_posts',
                nonce:     SE.nonce,
                term:      term,
                post_type: type
            },
            success: function(res) {
                if ( ! res.success || ! res.data.length ) {
                    $results.html('<div class="se-result-empty">No results found.</div>');
                    return;
                }
                var html = '';
                $.each(res.data, function(i, post) {
                    var added = isExcluded(post.id) ? ' is-added' : '';
                    html +=
                        '<div class="se-result-item' + added + '" data-id="' + post.id + '">' +
                            '<span class="se-result-item__title">' + escHtml(post.title) + '</span>' +
                            '<span class="se-result-item__type">' + escHtml(post.type) + '</span>' +
                            '<button type="button" class="se-result-add">' +
                                (isExcluded(post.id) ? 'Added' : '+ Add') +
                            '</button>' +
                        '</div>';
                });
                $results.html(html);
            },
            error: function() {
                $results.html('<div class="se-result-empty">Error fetching results.</div>');
            }
        });
    }

    /* ── Toggle row class when switch changes ───────────────── */
    function updateToggleRowState($input) {
        var $row = $input.closest('.se-toggle-row');
        var $badge = $row.find('.se-type-badge');
        if ( $input.is(':checked') ) {
            $row.addClass('is-excluded');
            $badge.removeClass('se-type-badge--active').text('Excluded');
        } else {
            $row.removeClass('is-excluded');
            $badge.addClass('se-type-badge--active').text('Included');
        }
    }

    /* ── Event Binding ──────────────────────────────────────── */
    $(function () {

        // Search input with debounce
        $('#se-search').on('input', function() {
            clearTimeout(searchTimer);
            if ( $(this).val().trim().length === 0 && $('#se-type-filter').val() === 'any' ) {
                $('#se-results').hide().empty();
                return;
            }
            searchTimer = setTimeout(doSearch, 280);
        });

        // Type filter
        $('#se-type-filter').on('change', function() {
            if ( $('#se-search').val().trim() || $(this).val() !== 'any' ) {
                doSearch();
            }
        });

        // Click add button in results
        $(document).on('click', '.se-result-add', function(e) {
            e.stopPropagation();
            var $row = $(this).closest('.se-result-item');
            if ( $row.hasClass('is-added') ) return;
            addPost({
                id:    parseInt($row.data('id')),
                title: $row.find('.se-result-item__title').text(),
                type:  $row.find('.se-result-item__type').text()
            });
            $row.addClass('is-added');
            $(this).text('Added');
        });

        // Click result row (not button)
        $(document).on('click', '.se-result-item', function(e) {
            if ( $(e.target).is('.se-result-add') ) return;
            $(this).find('.se-result-add').trigger('click');
        });

        // Remove from excluded list
        $(document).on('click', '.se-remove', function() {
            var $item = $(this).closest('.se-item');
            var id    = $item.data('id');
            $item.css({ opacity: 0, transform: 'translateX(20px)', transition: 'all .2s' });
            setTimeout(function() {
                $item.remove();
                removePost(id);
            }, 200);
        });

        // Hide results when clicking outside
        $(document).on('click', function(e) {
            if ( ! $(e.target).closest('.se-search-box').length ) {
                $('#se-results').hide();
            }
        });
        $('#se-search').on('focus', function() {
            if ( $('#se-results').children().length ) {
                $('#se-results').show();
            }
        });

        // Post type toggle switches
        $(document).on('change', '.se-switch input[type="checkbox"]', function() {
            updateToggleRowState($(this));
        });

    });

})(jQuery);

/* ── Algolia UI (appended) ──────────────────────────────────── */
(function ($) {
    'use strict';

    function showAlgoliaMsg(msg, type) {
        var icon = type === 'success'
            ? '<svg width="16" height="16" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="9" stroke="#22c55e" stroke-width="2"/><path d="M6 10.5l3 3 5-5" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
            : '<svg width="16" height="16" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="9" stroke="#f87171" stroke-width="2"/><path d="M10 6v5M10 13v1" stroke="#f87171" stroke-width="2" stroke-linecap="round"/></svg>';
        var $el = $('#se-algolia-msg');
        $el.removeClass('se-algolia-msg--success se-algolia-msg--error')
           .addClass('se-algolia-msg--' + type)
           .html(icon + ' ' + msg)
           .show();
    }

    $(function () {

        // Toggle API key visibility
        $('#se-toggle-key').on('click', function () {
            var $input = $('#se_algolia_admin_key');
            $input.attr('type', $input.attr('type') === 'password' ? 'text' : 'password');
        });

        // Test connection
        $('#se-test-btn').on('click', function () {
            var $btn = $(this).addClass('is-loading').text('Testing…');
            $.ajax({
                url:    SE.ajaxurl,
                method: 'POST',
                data:   { action: 'se_algolia_test', nonce: SE.nonce },
                success: function (res) {
                    showAlgoliaMsg( res.data, res.success ? 'success' : 'error' );
                },
                error: function () {
                    showAlgoliaMsg('Request failed. Check your server logs.', 'error');
                },
                complete: function () {
                    $btn.removeClass('is-loading').html(
                        '<svg width="14" height="14" viewBox="0 0 20 20" fill="none"><path d="M10 2a8 8 0 100 16A8 8 0 0010 2z" stroke="currentColor" stroke-width="1.8"/><path d="M10 6v4l3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg> Test Connection'
                    );
                }
            });
        });

        // Force sync
        $('#se-sync-btn').on('click', function () {
            if ( ! confirm('This will delete ALL currently excluded posts from your Algolia index. Continue?') ) return;
            var $btn = $(this).addClass('is-loading').text('Syncing…');
            $.ajax({
                url:    SE.ajaxurl,
                method: 'POST',
                data:   { action: 'se_algolia_sync', nonce: SE.nonce },
                success: function (res) {
                    showAlgoliaMsg( res.data, res.success ? 'success' : 'error' );
                },
                error: function () {
                    showAlgoliaMsg('Sync request failed. Check your server logs.', 'error');
                },
                complete: function () {
                    $btn.removeClass('is-loading').html(
                        '<svg width="14" height="14" viewBox="0 0 20 20" fill="none"><path d="M4 10a6 6 0 016-6 6 6 0 014.47 2M16 10a6 6 0 01-6 6 6 6 0 01-4.47-2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M14 6l2.47-2L19 6M6 14l-2.47 2L1 14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg> Force Sync Now'
                    );
                }
            });
        });

    });
})(jQuery);
