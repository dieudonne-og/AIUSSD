<?php
class ResponseRepository
{
    public function __construct(private PDO $db) {}

    public function saveCompleted(string $sid, string $cell, array $a, int $score, string $category): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO responses
                (session_id, consented, cell, q2,q3,q4,q5,q6,q7,q8, score, category)
             VALUES (?,1,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $sid, $cell,
            $a['q2'], $a['q3'], $a['q4'], $a['q5'], $a['q6'], $a['q7'], $a['q8'],
            $score, $category,
        ]);
    }

    public function saveDeclined(string $sid): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO responses (session_id, consented) VALUES (?, 0)'
        );
        $stmt->execute([$sid]);
    }

    public function total(): int
    {
        return (int) $this->db->query(
            'SELECT COUNT(*) FROM responses WHERE consented = 1'
        )->fetchColumn();
    }

    public function averageScore(): float
    {
        $v = $this->db->query(
            'SELECT AVG(score) FROM responses WHERE consented = 1'
        )->fetchColumn();
        return round((float) $v, 1);
    }

    public function categoryBreakdown(): array
    {
        $rows = $this->db->query(
            'SELECT category, COUNT(*) c FROM responses
             WHERE consented = 1 GROUP BY category'
        )->fetchAll();
        $out = [];
        foreach ($rows as $r) { $out[$r['category']] = (int) $r['c']; }
        return $out;
    }

    public function byCell(): array
    {
        $rows = $this->db->query(
            'SELECT cell, COUNT(*) c, AVG(score) a FROM responses
             WHERE consented = 1 GROUP BY cell'
        )->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[$r['cell']] = ['count' => (int) $r['c'], 'avg' => round((float) $r['a'], 1)];
        }
        return $out;
    }

    public function indicatorStats(): array
    {
        $total = $this->total() ?: 1; // avoid divide-by-zero
        $pct = fn(string $where) => round(
            100 * (int) $this->db->query(
                "SELECT COUNT(*) FROM responses WHERE consented = 1 AND $where"
            )->fetchColumn() / $total, 1
        );
        return [
            'home_internet_pct' => $pct('q3 = 1'),  // Yes, at home
            'cannot_afford_pct' => $pct('q4 = 3'),  // cannot afford
            'no_skills_pct'     => $pct('q7 = 3'),  // no digital skills
        ];
    }

    public function recent(int $limit): array
    {
        $stmt = $this->db->prepare(
            'SELECT cell, score, category, created_at FROM responses
             WHERE consented = 1 ORDER BY id DESC LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
