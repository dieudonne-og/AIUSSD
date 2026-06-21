<?php
class UserRepository
{
    public function __construct(private PDO $db) {}

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        return $stmt->fetch() ?: null;
    }

    public function create(string $username, string $passwordHash): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, password_hash) VALUES (?, ?)'
        );
        $stmt->execute([$username, $passwordHash]);
    }
}
