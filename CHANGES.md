# Changelog

## 1.5.0 (4/9/26)
* Changed: PHP 8.4 compatibility.

## 1.4.0 (2/22/25)
* Added: Support for Restrict Content Pro and WooCommerce Memberships so files are only shown if the user can access/view the post content.
* Changed: Replace .htaccess referrer protection with time-limited token-based protection.
* Changed: Use WordPress action hooks instead of standalone download.php file (fixes symlink issues).
* Changed: Remove iframe overlay complexity - PDFs now open directly in browser.
* Changed: Token expiration reduced from 24 hours to 2 hours.
* Changed: Replace custom get_field() method with ACF's built-in get_field() function.
* Removed: All .htaccess code (not needed with Nginx).
* Removed: download.php file (using WordPress hooks instead).

## 1.3.2 (2/20/25)
* Fixed: PDFs and zips can now be viewed and downloaded.

## 1.1.2 (5/31/18)
* Fixed: Clear floats on file list display.

## 1.1.1 (3/16/18)
* Fixed: Upload prefilter priority args missing causing HTTP errors when uploading files.

## 1.1.0
* Changed: Initial name change from Protected PDFs to Wampum Protected Media.

## 1.0.5
* Changed: Don't swap iframe src if opening the same pdf that is already loaded.

## 1.0.4
* Changed: Move file display to later priority on genesis_entry_content hook.
* Changed: More seemless close button in PDFs.

## 1.0.3
* Fixed: jQuery when swapping PDF iframe src to load a different file.

## 1.0.2
* Fixed: Hook post type config later so plugins that register CPTs can use Protected PDFs.

## 1.0.1
* Fixed: Metabox post type config.

## 1.0.0
* Official release.
