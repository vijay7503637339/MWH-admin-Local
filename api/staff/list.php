<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/helpers.php';

if (!in_array(($_SERVER['REQUEST_METHOD'] ?? ''), ['GET','POST'], true)) {
    localJson(['success'=>false,'message'=>'GET or POST request required'],405);
}

try {
    $user = localRequireUser($pdo);
    if ((string)$user['role'] !== 'contractor') {
        localJson(['success'=>false,'message'=>'Contractor access is required'],403);
    }

    $input = localInput();
    $q = trim((string)($input['q'] ?? $_GET['q'] ?? ''));
    $city = trim((string)($input['city'] ?? $_GET['city'] ?? ''));
    $skills = trim((string)($input['skills'] ?? $_GET['skills'] ?? ''));
    $gender = trim((string)($input['gender'] ?? $_GET['gender'] ?? ''));
    $minExperience = (float)($input['min_experience'] ?? $_GET['min_experience'] ?? 0);
    $limit = (int)($input['limit'] ?? $_GET['limit'] ?? 30);
    $limit = max(1, min(100, $limit));

    if ($minExperience < 0 || $minExperience > 60) {
        localJson(['success'=>false,'message'=>'Invalid minimum experience'],422);
    }

    $where = [
        "u.role='staff'",
        "u.account_status='verified'",
        "u.deleted_at IS NULL",
    ];
    $params = [];

    if ($q !== '') {
        $where[] = '(u.name LIKE ? OR s.city LIKE ? OR s.skills LIKE ?)';
        $term = '%' . $q . '%';
        array_push($params, $term, $term, $term);
    }
    if ($city !== '') {
        $where[] = 's.city LIKE ?';
        $params[] = '%' . $city . '%';
    }
    if ($skills !== '') {
        $where[] = 's.skills LIKE ?';
        $params[] = '%' . $skills . '%';
    }
    if ($gender !== '' && $gender !== 'all') {
        $where[] = 's.gender = ?';
        $params[] = $gender;
    }
    if ($minExperience > 0) {
        $where[] = 's.experience_years >= ?';
        $params[] = $minExperience;
    }

    $sql = 'SELECT
                u.id,
                u.name,
                s.gender,
                s.city,
                s.experience_years,
                s.skills
            FROM local_users u
            INNER JOIN local_staff_profiles s ON s.user_id=u.id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY s.experience_years DESC, u.name ASC
            LIMIT ' . $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $citiesStmt = $pdo->query(
        "SELECT DISTINCT s.city
         FROM local_users u
         INNER JOIN local_staff_profiles s ON s.user_id=u.id
         WHERE u.role='staff'
           AND u.account_status='verified'
           AND u.deleted_at IS NULL
           AND s.city IS NOT NULL
           AND TRIM(s.city) <> ''
         ORDER BY s.city ASC
         LIMIT 100"
    );
    $cities = array_values(array_filter(array_map(
        static fn($row) => trim((string)($row['city'] ?? '')),
        $citiesStmt->fetchAll(PDO::FETCH_ASSOC)
    )));

    localJson([
        'success'=>true,
        'data'=>[
            'items'=>$items,
            'total'=>count($items),
            'cities'=>$cities,
            'filters'=>[
                'q'=>$q,
                'city'=>$city,
                'skills'=>$skills,
                'gender'=>$gender,
                'min_experience'=>$minExperience,
            ],
        ],
    ]);
} catch (Throwable $e) {
    error_log('MWH Local staff directory: ' . $e->getMessage());
    localJson(['success'=>false,'message'=>'Unable to load staff directory'],500);
}
