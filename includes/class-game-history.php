<?php
declare(strict_types=1);

class League_Game_History {
    private ?SQLite3 $db = null;
    private bool $initialized = false;

    private function init_db(): void {
        if ($this->initialized) {
            return;
        }

        try {
            if (file_exists(LEAGUE_GAME_DB_PATH)) {
                $this->db = new SQLite3(LEAGUE_GAME_DB_PATH);
                $this->db->enableExceptions(true);
                $this->db->exec('PRAGMA encoding = "UTF-8"');
                
                // Add debug check
                $test = $this->db->query('SELECT COUNT(*) as count FROM sqlite_master WHERE type="table" AND name="player"');
                $row = $test->fetchArray(SQLITE3_ASSOC);
                error_log('Database initialized. Player table exists: ' . ($row['count'] > 0 ? 'yes' : 'no'));
                
                $this->initialized = true;
            }
        } catch (Exception $e) {
            error_log('Database initialization failed: ' . $e->getMessage());
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
                 JOIN tournament t ON g.tournament_id = t.id
                 JOIN club c ON t.club_id = c.id
                 JOIN player_game pg ON g.id = pg.game_id
                 JOIN player p ON pg.player_id = p.id
                 WHERE g.id IN (
                     SELECT game_id 
                     FROM player_games pg2 
                     JOIN player p2 ON pg2.player_id = p2.id 
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
            error_log("Error fetching games for player $trr_id", $e);
            return null;
        }
    }

    public function is_initialized(): bool {
        return $this->initialized;
    }

    public function __destruct() {
        $this->db?->close();
    }

    public function search_unregistered_players(string $search): array {
        $this->init_db();
        
        if (!$this->initialized) {
            return [];
        }

        try {
            $search = '%' . League_Security::sanitize_input($search) . '%';
            
            $query = $this->db->prepare(
                'SELECT p.trr_id, p.name 
                 FROM player p
                 WHERE p.name LIKE :search
                 AND p.trr_id NOT IN (
                     SELECT meta_value 
                     FROM wp_postmeta 
                     WHERE meta_key = "trr_id"
                 )
                 LIMIT 10'
            );

            $query->bindValue(':search', $search, SQLITE3_TEXT);
            $result = $query->execute();
            
            $players = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $players[] = [
                    'trr_id' => $row['trr_id'],
                    'name' => League_Security::encode_utf8($row['name'])
                ];
            }

            return $players;

        } catch (Exception $e) {
            error_log("Error searching unregistered players: " . $e->getMessage());
            return [];
        }
    }

    public function is_unregistered_player(string $trr_id): bool {
        $this->init_db();
        
        if (!$this->initialized) {
            return false;
        }

        try {
            // First check if this trr_id exists in WordPress
            global $wpdb;
            $exists_in_wp = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) 
                     FROM {$wpdb->postmeta} 
                     WHERE meta_key = %s 
                     AND meta_value = %s",
                    'trr_id',
                    $trr_id
                )
            );

            // If it exists in WordPress, it's not unregistered
            if ($exists_in_wp > 0) {
                return false;
            }

            // Then check if it exists in the game database
            $query = $this->db->prepare(
                'SELECT COUNT(*) as count 
                 FROM player 
                 WHERE trr_id = :trr_id'
            );

            $query->bindValue(':trr_id', $trr_id, SQLITE3_TEXT);
            $result = $query->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            
            // Return true only if it exists in game database but not in WordPress
            return (int)$row['count'] === 1;

        } catch (Exception $e) {
            error_log("Error checking unregistered player: " . $e->getMessage());
            return false;
        }
    }

    public function get_player_stats_summary(): array {
        $this->init_db();
        
        if (!$this->initialized) {
            return [
                'database_exists' => false,
                'total_players' => 0,
                'registered_players' => 0
            ];
        }

        try {
            // Get total players from SQLite
            $result = $this->db->query('SELECT COUNT(*) as count FROM player');
            if (!$result) {
                throw new Exception('Failed to query player count');
            }
            $row = $result->fetchArray(SQLITE3_ASSOC);
            $total = $row['count'];
            
            // Get registered players from WordPress
            global $wpdb;
            $registered = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT meta_value) 
                     FROM {$wpdb->postmeta} 
                     WHERE meta_key = %s",
                    'trr_id'
                )
            );

            return [
                'database_exists' => true,
                'total_players' => (int)$total,
                'registered_players' => (int)$registered
            ];
        } catch (Exception $e) {
            error_log('Error getting player stats summary: ' . $e->getMessage());
            return [
                'database_exists' => true,
                'total_players' => -1,
                'registered_players' => 0
            ];
        }
    }

    public function get_unregistered_players(): array {
        $this->init_db();
        
        if (!$this->initialized) {
            return [];
        }

        try {
            // First get all registered trr_ids from WordPress
            global $wpdb;
            $registered_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT meta_value 
                     FROM {$wpdb->postmeta} 
                     WHERE meta_key = %s",
                    'trr_id'
                )
            );

            // If no registered players, return all players from SQLite
            if (empty($registered_ids)) {
                $query = $this->db->prepare(
                    'SELECT trr_id, name FROM player ORDER BY name ASC'
                );
            } else {
                // Otherwise, get players whose trr_id is not in the registered list
                $placeholders = str_repeat('?,', count($registered_ids) - 1) . '?';
                $query = $this->db->prepare(
                    "SELECT trr_id, name 
                     FROM player 
                     WHERE trr_id NOT IN ($placeholders)
                     ORDER BY name ASC"
                );
                
                // Bind each registered ID
                foreach ($registered_ids as $i => $id) {
                    $query->bindValue($i + 1, $id, SQLITE3_TEXT);
                }
            }

            $result = $query->execute();
            
            $players = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $players[] = [
                    'trr_id' => $row['trr_id'],
                    'name' => League_Security::encode_utf8($row['name'])
                ];
            }

            return $players;

        } catch (Exception $e) {
            error_log("Error fetching unregistered players: " . $e->getMessage());
            return [];
        }
    }
} 