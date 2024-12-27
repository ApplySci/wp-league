<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$logger = League_Logger::get_instance();

try {
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
    $args = [
        'post_type' => 'league_player',
        'posts_per_page' => 20,
        'paged' => $paged,
        'orderby' => 'title',
        'order' => 'ASC'
    ];

    // Handle search
    $search = League_Security::sanitize_input($_GET['player_search'] ?? '');
    if ($search) {
        $args['s'] = $search;
    }

    $players_query = new WP_Query($args);

} catch (Exception $e) {
    error_log('Error loading player list', $e);
    wp_die(__('Error loading player list. Please try again later.', 'league-profiles'));
}

get_header();
?>

<div class="league-players-list">
    <h1><?php esc_html_e('Players', 'league-profiles'); ?></h1>

    <form method="get" class="player-search-form">
        <input type="text" 
               name="player_search" 
               value="<?php echo esc_attr($search); ?>" 
               placeholder="<?php esc_attr_e('Search players...', 'league-profiles'); ?>">
        <button type="submit" class="button">
            <?php esc_html_e('Search', 'league-profiles'); ?>
        </button>
    </form>

    <?php if ($players_query->have_posts()): ?>
        <div class="players-grid">
            <?php while ($players_query->have_posts()): $players_query->the_post(); ?>
                <div class="player-card">
                    <a href="<?php the_permalink(); ?>" class="player-link">
                        <?php if (has_post_thumbnail()): ?>
                            <div class="player-photo">
                                <?php the_post_thumbnail('thumbnail'); ?>
                            </div>
                        <?php endif; ?>
                        <h2 class="player-name"><?php the_title(); ?></h2>
                    </a>
                    <?php 
                    $rating = (int) get_post_meta(get_the_ID(), 'player_rating', true);
                    if ($rating): 
                    ?>
                        <div class="player-rating">
                            <?php echo esc_html(sprintf(
                                __('Rating: %d', 'league-profiles'),
                                $rating
                            )); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        </div>

        <?php 
        echo wp_kses_post(paginate_links([
            'total' => $players_query->max_num_pages,
            'current' => $paged,
            'prev_text' => __('&laquo; Previous', 'league-profiles'),
            'next_text' => __('Next &raquo;', 'league-profiles')
        ]));
        ?>

    <?php else: ?>
        <p><?php esc_html_e('No players found.', 'league-profiles'); ?></p>
    <?php endif; 
    wp_reset_postdata();
    ?>
</div>

<?php get_footer(); ?> 