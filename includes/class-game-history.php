<?php
declare(strict_types=1);

class League_Game_History {
    private ?SQLite3 $db = null;
    private bool $initialized = false;
    private League_Logger $logger;

    public function __construct() {
        $this->logger = League_Logger::get_instance();
    }

    private function init_db(): void {
        if ($this->initialized) {
            return;
        }

        try {
            // Ensure directory exists
            $db_dir = dirname(LEAGUE_GAME_DB_PATH);
            if (!is_dir($db_dir)) {
                wp_mkdir_p($db_dir);
            }

            // Only try to connect if the database exists
            if (file_exists(LEAGUE_GAME_DB_PATH)) {
                $this->db = new SQLite3(LEAGUE_GAME_DB_PATH);
                $this->db->enableExceptions(true);
                $this->db->exec('PRAGMA encoding = "UTF-8"');
                $this->initialized = true;
            }
        } catch (Exception $e) {
            $this->logger->error('Database initialization failed', $e);
            $this->initialized = false;
        }
    }

    public function get_player_games(string $trr_id, int $limit = 10): ?array {
        $this->init_db();
        
        if (!$this->initialized) {
            return null;
        }

        try {
            $trr_id = League_Security::sanitize_input($trr_id);
            
            $query = $this->db->prepare(
                'SELECT g.date, g.round, g.table_number, t.name as tournament_name,
                        c.name as club_name, c.country as club_country,
                        p.trr_id, p.name as player_name, pg.score
                 FROM games g
                 JOIN tournaments t ON g.tournament_id = t.id
                 JOIN clubs c ON t.club_id = c.id
                 JOIN player_games pg ON g.id = pg.game_id
                 JOIN players p ON pg.player_id = p.id
                 WHERE g.id IN (
                     SELECT game_id 
                     FROM player_games pg2 
                     JOIN players p2 ON pg2.player_id = p2.id 
                     WHERE p2.trr_id = :trr_id
                 )
                 ORDER BY g.date DESC
                 LIMIT :limit'
            );

            $query->bindValue(':trr_id', $trr_id, SQLITE3_TEXT);
            $query->bindValue(':limit', $limit, SQLITE3_INTEGER);
            
            $result = $query->execute();
            
            $games = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $game_id = $row['date'] . $row['round'] . $row['table_number'];
                if (!isset($games[$game_id])) {
                    $games[$game_id] = [
                        'date' => $row['date'],
                        'round' => $row['round'],
                        'table' => $row['table_number'],
                        'tournament' => League_Security::encode_utf8($row['tournament_name']),
                        'club' => League_Security::encode_utf8($row['club_name']),
                        'country' => $row['club_country'],
                        'players' => []
                    ];
                }
                
                $games[$game_id]['players'][] = [
                    'trr_id' => $row['trr_id'],
                    'name' => League_Security::encode_utf8($row['player_name']),
                    'score' => (int)$row['score']
                ];
            }

            return array_values($games);

        } catch (Exception $e) {
            $this->logger->error("Error fetching games for player $trr_id", $e);
            return null;
        }
    }

    public function is_initialized(): bool {
        return $this->initialized;
    }

    public function create_database(): bool {
        try {
            // Ensure directory exists
            $db_dir = dirname(LEAGUE_GAME_DB_PATH);
            if (!is_dir($db_dir)) {
                wp_mkdir_p($db_dir);
            }

            // Create new database
            $this->db = new SQLite3(LEAGUE_GAME_DB_PATH);
            $this->db->enableExceptions(true);
            $this->db->exec('PRAGMA encoding = "UTF-8"');
            
            // Create tables
            $this->db->exec(
                'CREATE TABLE IF NOT EXISTS players (
                    id INTEGER PRIMARY KEY,
                    trr_id TEXT UNIQUE NOT NULL,
                    name TEXT NOT NULL
                )'
            );
            // Add other table creation statements as needed

            $this->initialized = true;
            return true;
        } catch (Exception $e) {
            $this->logger->error('Database creation failed', $e);
            return false;
        }
    }

    public function __destruct() {
        $this->db?->close();
    }
} 