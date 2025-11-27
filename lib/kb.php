<?php
//lib/kb.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

/** Chuẩn hoá nhẹ để tìm kiếm/so sánh */
function kb_clean_text(string $s): string
{
    $s = preg_replace('/https?:\/\/\S+/i', ' ', $s);
    $s = preg_replace('/\s+/', ' ', trim($s));
    return $s;
}

/** Tạo chunk có overlap (theo ký tự, đủ dùng cho VN nếu chưa dùng tokenizer) */
function kb_make_chunks(string $text, int $maxLen = 1200, int $overlap = 200): array
{
    $text = trim($text);
    if ($text === '') return [];
    $chunks = [];
    $len = mb_strlen($text, 'UTF-8');
    for ($i = 0, $idx = 0; $i < $len; $i += ($maxLen - $overlap), $idx++) {
        $piece = mb_substr($text, $i, $maxLen, 'UTF-8');
        $chunks[] = ['idx' => $idx, 'text' => $piece];
        if ($i + $maxLen >= $len) break;
    }
    return $chunks;
}

/** Upsert nguồn */
function kb_upsert_source(PDO $pdo, string $name, string $platform = 'facebook', float $trust = 1.0, ?string $url = null): int
{
    $stmt = $pdo->prepare("SELECT id FROM kb_sources WHERE source_name=? LIMIT 1");
    $stmt->execute([$name]);
    $id = (int)$stmt->fetchColumn();
    if ($id) return $id;

    $ins = $pdo->prepare("INSERT INTO kb_sources(platform,source_name,trust_level,url) VALUES (?,?,?,?)");
    $ins->execute([$platform, $name, $trust, $url]);
    return (int)$pdo->lastInsertId();
}

/** Upsert post + chunks */
function kb_upsert_post_from_fb(PDO $pdo, array $fb): int
{
    $sourceName = envv('KB_SOURCE_NAME', 'IUHDemo');
    $trust      = (float) envv('KB_SOURCE_TRUST', '1.0');

    $sourceId = kb_upsert_source($pdo, $sourceName, 'facebook', $trust, null);

    $fb_id = $fb['id'] ?? null;
    $msg   = trim($fb['message'] ?? '');
    $url   = $fb['permalink_url'] ?? null;
    $ct    = !empty($fb['created_time']) ? date('Y-m-d H:i:s', strtotime($fb['created_time'])) : null;
    $ut    = !empty($fb['updated_time']) ? date('Y-m-d H:i:s', strtotime($fb['updated_time'])) : $ct;

    if (!$fb_id || ($msg === '' && !$url)) return 0;

    $clean = kb_clean_text($msg);
    $hash  = md5($clean);

    $stmt = $pdo->prepare("SELECT id, md5 FROM kb_posts WHERE fb_post_id=? LIMIT 1");
    $stmt->execute([$fb_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        // cập nhật nếu thay đổi
        if ($row['md5'] !== $hash) {
            $upd = $pdo->prepare("UPDATE kb_posts
                                  SET message_raw=?, message_clean=?, updated_time=?, trust_level=?, permalink_url=?, md5=?
                                  WHERE id=?");
            $upd->execute([$msg, $clean, $ut, $trust, $url, $hash, $row['id']]);
            $postId = (int)$row['id'];

            // xóa chunk cũ & nạp lại
            $pdo->prepare("DELETE FROM kb_chunks WHERE post_id=?")->execute([$postId]);
            kb_insert_chunks($pdo, $postId, $msg, $trust);
        } else {
            $postId = (int)$row['id'];
        }
        return $postId;
    }

    $ins = $pdo->prepare("INSERT INTO kb_posts(source_id, fb_post_id, title, message_raw, message_clean, permalink_url,
                        created_time, updated_time, trust_level, md5)
                        VALUES (?,?,?,?,?,?,?,?,?,?)");
    $ins->execute([$sourceId, $fb_id, null, $msg, $clean, $url, $ct, $ut, $trust, $hash]);
    $postId = (int)$pdo->lastInsertId();

    kb_insert_chunks($pdo, $postId, $msg, $trust);
    return $postId;
}

function kb_insert_chunks(PDO $pdo, int $postId, string $text, float $trust)
{
    $maxLen  = (int) envv('KB_CHUNK_LEN', 1200);
    $overlap = (int) envv('KB_CHUNK_OVERLAP', 200);
    $chunks  = kb_make_chunks($text, $maxLen, $overlap);

    $ins = $pdo->prepare("INSERT INTO kb_chunks(post_id, chunk_idx, text, text_clean, tokens, trust_level)
                          VALUES (?,?,?,?,?,?)");
    foreach ($chunks as $c) {
        $clean = kb_clean_text($c['text']);
        $ins->execute([$postId, $c['idx'], $c['text'], $clean, mb_strlen($c['text'], 'UTF-8'), $trust]);
    }
}

/** Tìm kiếm NL mode (tạm thời thay cho vector) */
// lib/kb.php
function kb_search_chunks(PDO $pdo, string $q, int $limit = 6): array
{
    $limit = max(1, min(50, (int)$limit));

    $baseSelect = "
        SELECT 
            kc.id,
            kc.post_id,
            kp.title,
            kp.created_time AS created_time,
            kc.trust_level   AS trust_level,
            kc.text          AS text,
            kp.permalink_url
    ";

    /* 1) Natural language (nếu có FULLTEXT) */
    $sql1 = $baseSelect . ",
            MATCH(kc.text, kc.text_clean) AGAINST(:q IN NATURAL LANGUAGE MODE) AS score
        FROM kb_chunks kc
        JOIN kb_posts  kp ON kp.id = kc.post_id
        WHERE MATCH(kc.text, kc.text_clean) AGAINST(:q IN NATURAL LANGUAGE MODE)
        ORDER BY score DESC
        LIMIT $limit
    ";
    try {
        $st = $pdo->prepare($sql1);
        $st->execute([':q' => $q]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $rows = [];
    }

    /* 2) Boolean mode */
    if (!$rows) {
        $tokens = preg_split('/\s+/u', trim($q));
        $tokens = array_filter(array_map(
            fn($t) => preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($t)),
            $tokens
        ));
        $bool = implode(' ', array_map(fn($t) => '+' . $t . '*', array_slice($tokens, 0, 8)));

        if ($bool !== '') {
            $sql2 = $baseSelect . ",
                    MATCH(kc.text, kc.text_clean) AGAINST(:q IN BOOLEAN MODE) AS score
                FROM kb_chunks kc
                JOIN kb_posts  kp ON kp.id = kc.post_id
                WHERE MATCH(kc.text, kc.text_clean) AGAINST(:q IN BOOLEAN MODE)
                ORDER BY score DESC
                LIMIT $limit
            ";
            try {
                $st = $pdo->prepare($sql2);
                $st->execute([':q' => $bool]);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                $rows = [];
            }
        }
    }

    /* 3) LIKE fallback (luôn chạy được nếu chưa có FULLTEXT) */
    if (!$rows) {
        $like = '%' . $q . '%';
        $sql3 = $baseSelect . "
            FROM kb_chunks kc
            JOIN kb_posts  kp ON kp.id = kc.post_id
            WHERE kc.text LIKE :like
               OR kc.text_clean LIKE :like
               OR kp.title LIKE :like
               OR kp.message_clean LIKE :like
            ORDER BY kc.id DESC
            LIMIT $limit
        ";
        $st = $pdo->prepare($sql3);
        $st->execute([':like' => $like]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    }

    return $rows ?: [];
}

// lib/kb.php
function vi_norm($s)
{
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $s);
    $s = preg_replace('/\s+/u', ' ', trim($s));
    return $s;
}

function expand_vi_query($q)
{
    $q = vi_norm($q);
    $syn = [
        'hoc phi' => ['học phí', 'hoc phi', 'học-phi'],
        'hk1'     => ['học kỳ 1', 'hk 1', 'hki'],
        'hk2'     => ['học kỳ 2', 'hk 2', 'hkii'],
        'tin chi' => ['tín chỉ', 'tin-chi', 'tinchi'],
        'tang'    => ['tăng', 'điều chỉnh', 'điều-chỉnh', 'điều chỉnh']
    ];
    $parts = preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY);
    $terms = [];
    foreach ($parts as $p) {
        $alts = [$p];
        foreach ($syn as $k => $arr) {
            if (strpos($p, $k) !== false || in_array($p, $arr, true)) {
                $alts = array_merge($alts, $arr);
            }
        }
        $alts = array_unique($alts);
        $terms[] = '(' . implode(' OR ', array_map(fn($t) => "\"$t\"", $alts)) . ')';
    }
    $boolean = '+' . implode(' +', $parts); // yêu cầu có đủ các từ chính
    return [$boolean, implode(' ', $terms)];
}

/**
 * Tìm top-k chunk theo điểm fulltext (title + chunk) + boost theo độ mới.
 * opts:
 *  - source: 'IUHDemo' (mặc định). Truyền '%' để cho phép mọi nguồn.
 *  - trust_min: 0..1
 *  - days: khoảng thời gian (mặc định 730 ngày)
 */
function kb_search_chunks_like(PDO $pdo, string $q, int $k = 6, array $opts = []): array
{
    $trustMin = (float)($opts['trust_min'] ?? 0.9);
    $days     = (int)($opts['days'] ?? 730);
    $source   = $opts['source'] ?? 'IUHDemo';
    $wild     = ($source === '%') ? 1 : 0;
    $qlike    = '%' . preg_replace('/\s+/u', '%', $q) . '%';

    $sql = "
      SELECT
        kc.id AS chunk_id, kc.post_id, kc.chunk_idx,
        kc.text, kc.text_clean,
        kp.permalink_url, kp.created_time, kp.title,
        ks.source_name,
        (COALESCE(kp.trust_level,1.0) * COALESCE(kc.trust_level,1.0)) AS trust,
        (
          (CASE WHEN kp.title LIKE :ql THEN 1.5 ELSE 0 END) +
          (CASE WHEN (kc.text LIKE :ql OR kc.text_clean LIKE :ql) THEN 1.0 ELSE 0 END) +
          (1.0/(1.0 + IFNULL(TIMESTAMPDIFF(DAY,kp.created_time,NOW()),99999)/90))
        ) AS score
      FROM kb_chunks kc
      JOIN kb_posts   kp ON kp.id = kc.post_id
      JOIN kb_sources ks ON ks.id = kp.source_id
      WHERE (:wild=1 OR ks.source_name = :src)
        AND COALESCE(kp.trust_level,1.0) >= :t
        AND COALESCE(kc.trust_level,1.0) >= :t
        AND (kp.created_time IS NULL OR kp.created_time >= (NOW() - INTERVAL :days DAY))
        AND ((kc.text IS NOT NULL AND kc.text <> '') OR (kc.text_clean IS NOT NULL AND kc.text_clean <> ''))
        AND (kp.title LIKE :ql OR kc.text LIKE :ql OR kc.text_clean LIKE :ql)
      ORDER BY score DESC
      LIMIT :k
    ";
    $st = $pdo->prepare($sql);
    $st->bindValue(':wild', $wild, PDO::PARAM_INT);
    $st->bindValue(':src', $source);
    $st->bindValue(':t', $trustMin);
    $st->bindValue(':days', $days, PDO::PARAM_INT);
    $st->bindValue(':ql', $qlike);
    $st->bindValue(':k', (int)$k, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Truy vấn chính dùng FULLTEXT. Nếu gặp lỗi 1191/FULLTEXT sẽ tự động fallback sang LIKE.
 */
function kb_has_fulltext(PDO $pdo, string $table, array $cols): bool
{
    if (!$cols) return false;
    $in  = implode(',', array_fill(0, count($cols), '?'));
    $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = ?
              AND INDEX_TYPE   = 'FULLTEXT'
              AND COLUMN_NAME IN ($in)";
    $st = $pdo->prepare($sql);
    $params = array_merge([$table], $cols);
    $st->execute($params);
    // chỉ cần có FULLTEXT cho *ít nhất một* cột trong nhóm là đủ dùng ở dưới
    return ((int)$st->fetchColumn()) > 0;
}

function kb_search_chunks_v2(PDO $pdo, string $q, int $k = 6, array $opts = []): array
{
    $trustMin = (float)($opts['trust_min'] ?? 0.9);
    $days     = (int)($opts['days'] ?? 730);
    $source   = $opts['source'] ?? 'IUHDemo';
    $wild     = ($source === '%') ? 1 : 0;
    $k        = max(1, min(50, (int)$k));

    // Tính mốc thời gian tại PHP để tránh bind trong INTERVAL
    $since = date('Y-m-d H:i:s', time() - $days * 86400);

    // Có FULLTEXT cho title không?
    $hasFTTitle = kb_has_fulltext($pdo, 'kb_posts', ['title', 'message_clean']);

    // Phần score cho title: nếu có FULLTEXT dùng MATCH, nếu không dùng LIKE cộng điểm nhẹ
    $titleScoreFT   = "COALESCE(MATCH(kp.title) AGAINST (:qq IN NATURAL LANGUAGE MODE), 0) * 1.2";
    $titleScoreLike = "(CASE WHEN kp.title LIKE :ql THEN 0.3 ELSE 0 END)";

    // ===== 1) FULLTEXT NATURAL =====
    $baseScore = "
        (
          " . ($hasFTTitle ? $titleScoreFT : $titleScoreLike) . " +
          COALESCE(MATCH(kc.text, kc.text_clean) AGAINST (:qq IN NATURAL LANGUAGE MODE), 0) * 1.0 +
          (1.0/(1.0 + IFNULL(TIMESTAMPDIFF(DAY,kp.created_time,NOW()),99999)/90))
        ) AS score
    ";

    $sqlFT = "
      SELECT
        kc.id AS chunk_id, kc.post_id, kc.chunk_idx,
        kc.text, kc.text_clean,
        kp.permalink_url, kp.created_time, kp.title,
        ks.source_name,
        (COALESCE(kp.trust_level,1.0) * COALESCE(kc.trust_level,1.0)) AS trust,
        $baseScore
      FROM kb_chunks kc
      JOIN kb_posts   kp ON kp.id = kc.post_id
      JOIN kb_sources ks ON ks.id = kp.source_id
      WHERE (:wild=1 OR ks.source_name = :src)
        AND COALESCE(kp.trust_level,1.0) >= :t
        AND COALESCE(kc.trust_level,1.0) >= :t
        AND (kp.created_time IS NULL OR kp.created_time >= :since)
        AND ((kc.text IS NOT NULL AND kc.text <> '') OR (kc.text_clean IS NOT NULL AND kc.text_clean <> ''))
      ORDER BY score DESC, kp.created_time DESC
      LIMIT :k
    ";

    $rows = [];
    try {
        $st = $pdo->prepare($sqlFT);
        $st->bindValue(':qq', $q);
        if (!$hasFTTitle) $st->bindValue(':ql', '%' . $q . '%');
        $st->bindValue(':wild', $wild, PDO::PARAM_INT);
        $st->bindValue(':src',  $source);
        $st->bindValue(':t',    $trustMin);
        $st->bindValue(':since', $since);
        $st->bindValue(':k',    (int)$k, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        // Nếu lỗi FULLTEXT (vd 1191) thì rơi xuống BOOLEAN/LIKE
        $rows = [];
    }

    // ===== 2) FULLTEXT BOOLEAN (mở rộng từ khóa TV) =====
    if (!$rows) {
        if (function_exists('expand_vi_query')) {
            [$bool, $human] = expand_vi_query($q);
            if ($bool) {
                $titleScoreFTB = "COALESCE(MATCH(kp.title) AGAINST (:bb IN BOOLEAN MODE), 0) * 1.2";
                $baseScoreB = "
                    (
                      " . ($hasFTTitle ? $titleScoreFTB : $titleScoreLike) . " +
                      COALESCE(MATCH(kc.text, kc.text_clean) AGAINST (:bb IN BOOLEAN MODE), 0) * 1.0 +
                      (1.0/(1.0 + IFNULL(TIMESTAMPDIFF(DAY,kp.created_time,NOW()),99999)/90))
                    ) AS score
                ";
                $sqlBool = "
                  SELECT
                    kc.id AS chunk_id, kc.post_id, kc.chunk_idx,
                    kc.text, kc.text_clean,
                    kp.permalink_url, kp.created_time, kp.title,
                    ks.source_name,
                    (COALESCE(kp.trust_level,1.0) * COALESCE(kc.trust_level,1.0)) AS trust,
                    $baseScoreB
                  FROM kb_chunks kc
                  JOIN kb_posts   kp ON kp.id = kc.post_id
                  JOIN kb_sources ks ON ks.id = kp.source_id
                  WHERE (:wild=1 OR ks.source_name = :src)
                    AND COALESCE(kp.trust_level,1.0) >= :t
                    AND COALESCE(kc.trust_level,1.0) >= :t
                    AND (kp.created_time IS NULL OR kp.created_time >= :since)
                    AND ((kc.text IS NOT NULL AND kc.text <> '') OR (kc.text_clean IS NOT NULL AND kc.text_clean <> ''))
                  ORDER BY score DESC, kp.created_time DESC
                  LIMIT :k
                ";
                try {
                    $st2 = $pdo->prepare($sqlBool);
                    $st2->bindValue(':bb', $bool);
                    if (!$hasFTTitle) $st2->bindValue(':ql', '%' . $q . '%');
                    $st2->bindValue(':wild', $wild, PDO::PARAM_INT);
                    $st2->bindValue(':src',  $source);
                    $st2->bindValue(':t',    $trustMin);
                    $st2->bindValue(':since', $since);
                    $st2->bindValue(':k',    (int)$k, PDO::PARAM_INT);
                    $st2->execute();
                    $rows = $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } catch (PDOException $e2) {
                    $rows = [];
                }
            }
        }
    }

    // ===== 3) LIKE fallback =====
    if (!$rows) {
        return kb_search_chunks_like($pdo, $q, $k, $opts);
    }

    return $rows;
}
