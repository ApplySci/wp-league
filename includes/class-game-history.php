<?php
class League_Game_History {
    private $db;
    private $initialized = false;

    public function __construct() {
        $this->init_db();
    }

    private function init_db(): void {
        try {
            $this->db = new SQLite3(LEAGUE_GAME_DB_PATH);
            $this->db->enableExceptions(true);
            $this->initialized = true;
        } catch (Exception $e) {
            error_log('Failed to connect to game history database: ' . $e->getMessage());
            $this->initialized = false;
        }
    }

    public function get_player_games(string $trr_id, int $limit = 10): array {
        if (!$this->initialized) return [];

        try {
            $query = $this->db->prepare("
                SELECT 
                    g.date,
                    g.round,
                    g.table,
                    pg.score,
                    t.name as tournament_name,
                    t.town as tournament_town
                FROM game g
                JOIN player_game pg ON g.id = pg.game_id
                JOIN player p ON pg.player_id = p.id
                LEFT JOIN tournament t ON g.tournament_id = t.id
                WHERE p.trr_id = :trr_id
                ORDER BY g.date DESC
                LIMIT :limit
            ");

            $query->bindValue(':trr_id', $trr_id, SQLITE3_TEXT);
            $query->bindValue(':limit', $limit, SQLITE3_INTEGER);
            
            $result = $query->execute();
            $games = [];

            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $games[] = [
                    'date' => $row['date'],
                    'round' => $row['round'],
                    'table' => $row['table'],
                    'score' => $row['score'],
                    'tournament' => $row['tournament_name'] ? [
                        'name' => $row['tournament_name'],
                        'town' => $row['tournament_town']
                    ] : null
                ];
            }

            return $games;
        } catch (Exception $e) {
            error_log('Error fetching player games: ' . $e->getMessage());
            return [];
        }
    }

    public function get_player_stats(string $trr_id): array {
        if (!$this->initialized) return [];

        try {
            // Get total games played
            $games_query = $this->db->prepare("
                SELECT COUNT(*) as total_games
                FROM player_game pg
                JOIN player p ON pg.player_id = p.id
                WHERE p.trr_id = :trr_id
            ");
            $games_query->bindValue(':trr_id', $trr_id, SQLITE3_TEXT);
            $games_result = $games_query->execute();
            $total_games = $games_result->fetchArray(SQLITE3_ASSOC)['total_games'];

            // Get ratings
            $ratings_query = $this->db->prepare("
                SELECT 
                    plackett_luce_score,
                    bradley_terry_score,
                    thurstone_mosteller_score,
                    plackett_luce_rank,
                    bradley_terry_rank,
                    thurstone_mosteller_rank
                FROM player
                WHERE trr_id = :trr_id
            ");
            $ratings_query->bindValue(':trr_id', $trr_id, SQLITE3_TEXT);
            $ratings_result = $ratings_query->execute();
            $ratings = $ratings_result->fetchArray(SQLITE3_ASSOC);

            return [
                'total_games' => $total_games,
                'ratings' => [
                    'plackett_luce' => [
                        'score' => $ratings['plackett_luce_score'],
                        'rank' => $ratings['plackett_luce_rank']
                    ],
                    'bradley_terry' => [
                        'score' => $ratings['bradley_terry_score'],
                        'rank' => $ratings['bradley_terry_rank']
                    ],
                    'thurstone_mosteller' => [
                        'score' => $ratings['thurstone_mosteller_score'],
                        'rank' => $ratings['thurstone_mosteller_rank']
                    ]
                ]
            ];
        } catch (Exception $e) {
            error_log('Error fetching player stats: ' . $e->getMessage());
            return [];
        }
    }

    public function get_player_tournaments(string $trr_id): array {
        if (!$this->initialized) return [];

        try {
            $query = $this->db->prepare("
                SELECT DISTINCT
                    t.id,
                    t.name,
                    t.town,
                    t.first_day,
                    t.rules,
                    t.status,
                    c.name_english as country
                FROM tournament t
                JOIN tournament_player tp ON t.id = tp.tournament_id
                JOIN player p ON tp.player_id = p.id
                JOIN country c ON t.country_id = c.id
                WHERE p.trr_id = :trr_id
                ORDER BY t.first_day DESC
            ");

            $query->bindValue(':trr_id', $trr_id, SQLITE3_TEXT);
            $result = $query->execute();
            $tournaments = [];

            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $tournaments[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'town' => $row['town'],
                    'date' => $row['first_day'],
                    'rules' => $row['rules'],
                    'status' => $row['status'],
                    'country' => $row['country']
                ];
            }

            return $tournaments;
        } catch (Exception $e) {
            error_log('Error fetching player tournaments: ' . $e->getMessage());
            return [];
        }
    }

    public function get_game_details(int $game_id): ?array {
        if (!$this->initialized) return null;

        try {
            $query = $this->db->prepare("
                SELECT 
                    g.*,
                    p.trr_id,
                    p.name as player_name,
                    pg.score,
                    t.name as tournament_name,
                    c.name_english as club_country
                FROM game g
                JOIN player_game pg ON g.id = pg.game_id
                JOIN player p ON pg.player_id = p.id
                LEFT JOIN tournament t ON g.tournament_id = t.id
                LEFT JOIN club cl ON g.club_id = cl.id
                LEFT JOIN country c ON cl.country_id = c.id
                WHERE g.id = :game_id
            ");

            $query->bindValue(':game_id', $game_id, SQLITE3_INTEGER);
            $result = $query->execute();
            
            $game_data = [
                'details' => null,
                'players' => []
            ];

            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                if (!$game_data['details']) {
                    $game_data['details'] = [
                        'date' => $row['date'],
                        'round' => $row['round'],
                        'table' => $row['table'],
                        'tournament' => $row['tournament_name'],
                        'club_country' => $row['club_country']
                    ];
                }

                $game_data['players'][] = [
                    'trr_id' => $row['trr_id'],
                    'name' => $row['player_name'],
                    'score' => $row['score']
                ];
            }

            return $game_data;
        } catch (Exception $e) {
            error_log('Error fetching game details: ' . $e->getMessage());
            return null;
        }
    }

    public function __destruct() {
        if ($this->initialized) {
            $this->db->close();
        }
    }
} 