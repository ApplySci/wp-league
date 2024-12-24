# Troubleshooting Guide

## Common Issues

### OAuth Authentication

1. **Login Fails Silently**
   - Check OAuth credentials in settings
   - Verify redirect URI matches exactly
   - Check SSL certificate if using HTTPS
   - Check error logs for details

2. **State Mismatch Errors**
   ```
   Solution: Clear transients in wp_options table
   DELETE FROM wp_options WHERE option_name LIKE '_transient_league_oauth%';
   ```

### Database Integration

1. **No Game History Showing**
   - Verify SQLite file permissions
   - Check SQLite3 PHP extension is enabled
   - Verify database path in configuration
   - Check error logs for SQL errors

2. **Performance Issues**
   ```sql
   -- Add indexes if missing
   CREATE INDEX IF NOT EXISTS idx_player_trr_id ON player(trr_id);
   CREATE INDEX IF NOT EXISTS idx_game_date ON game(date);
   ```

### Profile Management

1. **Profile Updates Fail**
   - Check user capabilities
   - Verify nonce configuration
   - Check file upload permissions for photos
   - Verify post type permissions

2. **Missing Player Data**
   - Verify trr_id mapping is correct
   - Check database connections
   - Verify data exists in SQLite database

## Error Logging

1. **Enable Debug Logging**
   ```php
   // wp-config.php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```

2. **Common Log Locations**
   - WordPress debug log: `wp-content/debug.log`
   - PHP error log: Check php.ini configuration
   - Server error log: Apache/Nginx logs

## Performance Optimization

1. **Database Queries**
   - Use LIMIT in game history queries
   - Cache frequently accessed data
   - Use WordPress transients for OAuth states

2. **Asset Loading**
   - Minify CSS files for production
   - Load assets only on relevant pages
   - Consider using asset versioning

## Security Checks

1. **Regular Audits**
   - Check file permissions
   - Review OAuth settings
   - Verify capability checks
   - Test authentication flows

2. **Data Validation**
   ```php
   // Always sanitize and validate
   $trr_id = sanitize_text_field($_POST['trr_id']);
   if (!preg_match('/^[A-Za-z0-9]+$/', $trr_id)) {
       wp_die('Invalid TRR ID format');
   }
   ```

## Support Information

1. **Required Information for Support**
   - WordPress version
   - PHP version
   - SQLite version
   - Error log entries
   - Steps to reproduce issues

2. **Debugging Tools**
   - WordPress Debug Bar plugin
   - Query Monitor plugin
   - Browser developer tools 