<<<<<<< Updated upstream
# Protected PDFs
<<<<<<< HEAD
Attach PDFs to pages/posts/cpts that can only be viewed from the pages they are attached to (via PDF.js). Requires Genesis for file display and ACF Pro for the files metabox.
=======
Attach PDFs to pages/posts/cpts that can only be viewed from the pages they are attached to. Requires Genesis for file display and ACF Pro for the files metabox.
>>>>>>> da3eeae (initial commit to get back up and running)
=======
# Wampum - Protected Media
Attach PDFs and other files to pages/posts/cpts that can only be viewed from the pages they are attached to. Files are protected with time-limited tokens and cannot be directly accessed or indexed by search engines. Requires Genesis for file display and ACF Pro for the files metabox.
>>>>>>> Stashed changes

## Features

- Time-limited token-based protection (default: 2 hours)
- Prevents direct file access
- Prevents Google indexing with `X-Robots-Tag: noindex` header
- PDFs display inline in browser
- Other files download automatically

## Nginx Configuration

To prevent direct access to protected files, add the following location block to your Nginx server configuration:

```nginx
# Block direct access to protected uploads directory
location ~* /wp-content/uploads/wampum_protected_uploads/ {
    deny all;
    return 404;
}
```

This ensures that files can only be accessed through the protected download handler with valid tokens.

### Adding to Nginx Config

1. Open your Nginx configuration file (usually `/etc/nginx/sites-available/your-site` or `/etc/nginx/nginx.conf`)
2. Add the location block above within your `server` block
3. Test the configuration: `sudo nginx -t`
4. Reload Nginx: `sudo systemctl reload nginx` or `sudo service nginx reload`

## Filters

### `wampum_protected_media_post_types`
Filter which post types support Protected Media.

```php
add_filter( 'wampum_protected_media_post_types', function( $post_types ) {
    // Add or remove post types
    return $post_types;
} );
```

### `wampum_protected_media_image_size`
Change the image size used for display.

```php
add_filter( 'wampum_protected_media_image_size', function( $image_size ) {
    return 'medium'; // or 'thumbnail', 'large', etc.
} );
```

### `wampum_protected_media_token_expiration`
Change the token expiration time (default: 2 hours).

```php
add_filter( 'wampum_protected_media_token_expiration', function( $seconds ) {
    return 3 * HOUR_IN_SECONDS; // 3 hours
} );
```

### `wampum_protected_media_download_headers`
Customize headers when serving files.

```php
add_filter( 'wampum_protected_media_download_headers', function( $headers, $file_id, $is_pdf ) {
    // Modify headers array
    return $headers;
}, 10, 3 );
```
