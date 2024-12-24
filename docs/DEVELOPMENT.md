# Development Guide

## Project Structure 
```
league-profiles/
├── assets/ # Static assets
│ ├── css/ # Stylesheets
│ └── js/ # JavaScript files (future use)
├── includes/ # PHP classes
│ ├── auth/ # Authentication system
│ └── ... # Core functionality
├── templates/ # Template files
│ ├── admin/ # Admin interface templates
│ └── ... # Frontend templates
└── docs/ # Documentation
```
## Development Setup

1. **Local Environment**
   ```bash
   # Create symlink for local development
   ln -s /path/to/project/league-profiles /path/to/wordpress/wp-content/plugins/
   ```

2. **Database Access**
   - The plugin expects the SQLite database at `wp-content/database/league.db`
   - For development, you can use a test database with sample data

3. **OAuth Configuration**
   - Create test credentials in Google Cloud Console
   - Create test credentials in Apple Developer Console
   - Use `http://localhost/wp-json/league/v1/auth/callback` as redirect URI

## Coding Standards

1. **PHP**
   - Follow WordPress Coding Standards
   - Use type hints where possible
   - Document all public methods with PHPDoc

2. **CSS**
   - Use BEM naming convention
   - Maintain mobile-first approach
   - Keep specificity low

3. **Security**
   - Always use prepared statements for SQLite queries
   - Sanitize all output with appropriate WordPress functions
   - Verify nonces in forms
   - Check capabilities before actions

## Testing

1. **Manual Test Cases**
   - OAuth login flow
   - Profile creation and editing
   - Game history display
   - Admin functions
   - Mobile responsiveness

2. **Security Testing**
   - XSS prevention
   - SQL injection prevention
   - Authorization checks
   - Rate limiting

## Common Tasks

1. **Adding New Player Fields**
   ```php
   // 1. Add meta registration in class-post-types.php
   register_post_meta('league_player', 'new_field', [
       'type' => 'string',
       'single' => true,
       'show_in_rest' => true
   ]);

   // 2. Update profile template
   // 3. Update edit form
   ```

2. **Adding OAuth Provider**
   - Create new provider class extending `League_OAuth_Provider`
   - Add provider to `League_Auth_Controller`
   - Add settings fields

3. **Database Schema Updates**
   - Document changes in SQLite schema
   - Update `class-game-history.php` queries
   - Test with sample data 