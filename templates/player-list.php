<?php
get_header();

$paged = get_query_var('paged') ? get_query_var('paged') : 1;
$search = get_query_var('player_search');

$args = [
    'post_type' => 'league_player',
    'posts_per_page' => 20,
    'paged' => $paged,
    'orderby' => 'title',
    'order' => 'ASC'
];

if ($search) {
    $args['s'] = $search;
}

$players_query = new WP_Query($args);
?>

<div class="league-players-list">
    <h1>League Players</h1>

    <form method="get" class="player-search-form">
        <input type="text" 
               name="player_search" 
               value="<?php echo esc_attr($search); ?>" 
               placeholder="Search players...">
        <button type="submit">Search</button>
    </form>

    <?php if ($players_query->have_posts()): ?>
        <div class="players-grid">
            <?php while ($players_query->have_posts()): $players_query->the_post(); 
                $player_user_id = get_post_meta(get_the_ID(), 'player_user_id', true);
                $rating = get_post_meta(get_the_ID(), 'player_rating', true);
            ?>
                <div class="player-card">
                    <a href="<?php echo esc_url(get_permalink()); ?>">
                        <div class="player-photo">
                            <?php 
                            if (has_post_thumbnail()) {
                                the_post_thumbnail('thumbnail');
                            } else {
                                echo '<img src="' . esc_url(LEAGUE_PLUGIN_URL . 'assets/images/default-avatar.png') . '" alt="Default avatar">';
                            }
                            ?>
                        </div>
                        <h2><?php the_title(); ?></h2>
                        <div class="player-rating">
                            Rating: <?php echo esc_html($rating ?: 'Unrated'); ?>
                        </div>
                    </a>
                </div>
            <?php endwhile; ?>
        </div>

        <?php
        echo paginate_links([
            'total' => $players_query->max_num_pages,
            'current' => $paged,
            'prev_text' => '&laquo; Previous',
            'next_text' => 'Next &raquo;'
        ]);
        ?>

    <?php else: ?>
        <p>No players found.</p>
    <?php endif; 
    wp_reset_postdata();
    ?>
</div>

<?php get_footer(); ?> 