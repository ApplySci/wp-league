<?php
get_header();

$post = get_post();
$player_user_id = get_post_meta($post->ID, 'player_user_id', true);
$trr_id = get_post_meta($post->ID, 'trr_id', true);
$current_user_id = get_current_user_id();
$can_edit = League_Capabilities::check_player_access($post->ID, $current_user_id);

$game_history = new League_Game_History();
$player_stats = $game_history->get_player_stats($trr_id);
$recent_games = $game_history->get_player_games($trr_id, 10);
$tournaments = $game_history->get_player_tournaments($trr_id);
?>

<div class="league-profile-container">
    <div class="league-profile-header">
        <?php if ($can_edit): ?>
            <a href="<?php echo esc_url(add_query_arg('edit', '1')); ?>" class="edit-profile-button">
                Edit Profile
            </a>
        <?php endif; ?>
        
        <div class="profile-image">
            <?php echo get_the_post_thumbnail($post->ID, 'thumbnail', ['class' => 'profile-photo']); ?>
        </div>
        
        <h1><?php echo esc_html(get_the_title()); ?></h1>
        
        <div class="player-ratings">
            <div class="rating-box">
                <h3>Plackett-Luce</h3>
                <div class="rating-score"><?php echo number_format($player_stats['ratings']['plackett_luce']['score'], 2); ?></div>
                <div class="rating-rank">Rank: <?php echo esc_html($player_stats['ratings']['plackett_luce']['rank']); ?></div>
            </div>
            <div class="rating-box">
                <h3>Bradley-Terry</h3>
                <div class="rating-score"><?php echo number_format($player_stats['ratings']['bradley_terry']['score'], 2); ?></div>
                <div class="rating-rank">Rank: <?php echo esc_html($player_stats['ratings']['bradley_terry']['rank']); ?></div>
            </div>
            <div class="rating-box">
                <h3>Thurstone-Mosteller</h3>
                <div class="rating-score"><?php echo number_format($player_stats['ratings']['thurstone_mosteller']['score'], 2); ?></div>
                <div class="rating-rank">Rank: <?php echo esc_html($player_stats['ratings']['thurstone_mosteller']['rank']); ?></div>
            </div>
        </div>
    </div>

    <div class="league-profile-content">
        <div class="player-bio">
            <?php echo wp_kses_post(get_the_content()); ?>
        </div>

        <div class="player-stats">
            <h2>Statistics</h2>
            <div class="stats-summary">
                <div class="stat-box">
                    <span class="stat-label">Total Games</span>
                    <span class="stat-value"><?php echo esc_html($player_stats['total_games']); ?></span>
                </div>
            </div>
        </div>

        <div class="recent-games">
            <h2>Recent Games</h2>
            <?php if ($recent_games): ?>
                <table class="game-history-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Tournament</th>
                            <th>Round</th>
                            <th>Table</th>
                            <th>Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_games as $game): ?>
                            <tr>
                                <td><?php echo esc_html(date('Y-m-d', strtotime($game['date']))); ?></td>
                                <td>
                                    <?php 
                                    if ($game['tournament']) {
                                        echo esc_html($game['tournament']['name']) . ' (' . esc_html($game['tournament']['town']) . ')';
                                    } else {
                                        echo 'Club Game';
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html($game['round']); ?></td>
                                <td><?php echo esc_html($game['table']); ?></td>
                                <td><?php echo esc_html($game['score']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No games played yet.</p>
            <?php endif; ?>
        </div>

        <div class="tournaments">
            <h2>Tournament History</h2>
            <?php if ($tournaments): ?>
                <div class="tournament-list">
                    <?php foreach ($tournaments as $tournament): ?>
                        <div class="tournament-card">
                            <h3><?php echo esc_html($tournament['name']); ?></h3>
                            <div class="tournament-details">
                                <p>
                                    <strong>Location:</strong> 
                                    <?php echo esc_html($tournament['town']); ?>, 
                                    <?php echo esc_html($tournament['country']); ?>
                                </p>
                                <p>
                                    <strong>Date:</strong> 
                                    <?php echo esc_html(date('F j, Y', strtotime($tournament['date']))); ?>
                                </p>
                                <p>
                                    <strong>Rules:</strong> 
                                    <?php echo esc_html($tournament['rules']); ?>
                                </p>
                                <p>
                                    <strong>Status:</strong> 
                                    <span class="tournament-status status-<?php echo esc_attr(strtolower($tournament['status'])); ?>">
                                        <?php echo esc_html($tournament['status']); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No tournaments attended yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php get_footer(); ?> 