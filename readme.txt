=== Komal889 Content Exclusion for Algolia ===
Contributors: komal889
Tags: algolia, search, exclude, filter, custom search
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 2.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Exclude selected content from Algolia search index safely and efficiently.

== Description ==
Algolia Search Exclude allows you to easily exclude posts, pages, and custom post types from Algolia search results.

This plugin integrates with the Algolia search plugin and provides a simple interface to control which content should be indexed or removed from Algolia.

Perfect for:
- Excluding private or restricted content
- Removing outdated posts from search
- Controlling search visibility without deleting content

What the service does:
Used to remove selected content from Algolia search index.

What data is sent:
- Application ID
- Admin API Key
- Object IDs for deletion

When:
- When admin submits exclusion settings
- When syncing content

Service provider:
Algolia Inc.

Terms of Service:
https://www.algolia.com/policies/terms/

Privacy Policy:
https://www.algolia.com/policies/privacy/

== Features ==
- Exclude posts and pages from Algolia indexing
- Support for custom post types
- Bulk exclusion support
- Automatically sync excluded content with Algolia
- Lightweight and easy to use
- No coding required

== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/komal889-content-exclusion-for-algolia` directory, or install via the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Configure settings from the admin panel.
4. Start excluding content from Algolia search.

== Frequently Asked Questions ==

= Does this plugin require Algolia? =
Yes, this plugin works alongside the Algolia plugin.

= Will this affect default WordPress search? =
No, this plugin is designed specifically for Algolia indexing and search.

= Can I exclude custom post types? =
Yes, you can exclude any registered post type.

== Screenshots ==
1. Admin settings page
2. Exclude option in post editor

== Changelog ==
= 2.0.0 =
- Improved Algolia sync handling
- Added support for bulk exclusion
- Code improvements and optimization

= 1.0.0 =
- Initial release

== Upgrade Notice ==
= 2.0.0 =
Improved performance and better control over Algolia indexing.

== External Services ==

This plugin connects to Algolia to manage search indexing.

It sends selected post IDs to Algolia API to remove them from search results.

Service: Algolia  
Privacy Policy: https://www.algolia.com/policies/privacy/
