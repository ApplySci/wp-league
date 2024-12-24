# League Profiles WordPress Plugin

A WordPress plugin for managing player profiles and game histories for a league system. Integrates with an existing SQLite database of game records and provides OAuth2 authentication.

## Core Files

### Main Plugin File
- `league-profiles.php`: Main plugin entry point. Handles initialization, dependencies and activation/deactivation hooks.

### Authentication
- `includes/auth/class-oauth-provider.php`: Base abstract class for OAuth providers
- `includes/auth/class-google-provider.php`: Google OAuth2 implementation
- `includes/auth/class-apple-provider.php`: Apple OAuth2 implementation  
- `includes/auth/class-auth-controller.php`: Handles OAuth authentication flow and user creation/login

### Data Management
- `includes/class-post-types.php`: Registers custom post type for player profiles
- `includes/class-roles.php`: Manages custom user roles and capabilities
- `includes/class-capabilities.php`: Defines and checks user permissions
- `includes/class-game-history.php`: Interface to SQLite game database. Key methods:
  - `get_player_games()`: Fetches recent games for a player
  - `get_player_stats()`: Gets player ratings and statistics
  - `get_player_tournaments()`: Retrieves tournament history
  - `get_game_details()`: Gets full details of a specific game

### Admin Interface
- `includes/class-admin.php`: Admin panel functionality including:
  - Player invitation system
  - OAuth settings management
  - Custom columns in player list
- `templates/admin/invite-player.php`: Player invitation form
- `templates/admin/settings.php`: Plugin settings page

### Frontend Templates
- `templates/profile-page.php`: Public player profile display
- `templates/profile-edit.php`: Profile editing form
- `templates/player-list.php`: Archive/search page for all players

### Assets
- `assets/css/league-profiles.css`: Frontend styles
- `assets/css/admin.css`: Admin interface styles

## Key Features

- OAuth2 authentication with Google and Apple
- Player profile management
- Integration with existing game history database
- Tournament history display
- Multiple rating system display (Plackett-Luce, Bradley-Terry, Thurstone-Mosteller)
- Admin tools for player management
- Responsive design

## Database Integration

The plugin integrates with an existing SQLite database containing game histories. The database schema includes:

- Players with multiple rating systems
- Game records with scores
- Tournament records
- Club and country data

Players are linked between WordPress and the game database using the `trr_id` field as the unique identifier.

## Installation

1. Upload plugin directory to `/wp-content/plugins/`
2. Activate plugin through WordPress admin
3. Configure OAuth credentials in Settings
4. Set up player invitations as needed

## Requirements

- WordPress 5.0+
- PHP 7.4+
- SQLite3 PHP extension
