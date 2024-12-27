<?php
$game_history = new League_Game_History();
$stats = $game_history->get_player_stats_summary();

if (!$stats['database_exists']): ?>
    <div class="notice notice-error">
        <p>
            <?php esc_html_e('Warning: No game database found. Please upload a database file in the Settings page.', 'league-profiles'); ?>
        </p>
    </div>
<?php else: ?>
    <div class="notice notice-info">
        <p>
            <?php 
            printf(
                esc_html__('Database contains %1$d players, %2$d of which have registered profiles (%3$d%%).', 'league-profiles'),
                $stats['total_players'],
                $stats['registered_players'],
                $stats['total_players'] > 0 ? round(($stats['registered_players'] / $stats['total_players']) * 100) : 0
            );
            ?>
        </p>
    </div>
<?php endif; ?> 