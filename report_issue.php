<?php
// report_issue.php - Resident Issue & Maintenance Request Submission
require_once __DIR__ . '/config.php';

$message = "";
$error = "";

$user_id = $_SESSION['user_id'] ?? 1;
$estate_id = get_estate_id();
$db = Database::getInstance();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        verifyCsrfToken();
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $priority = sanitize($_POST['priority'] ?? 'medium');
        $flat_id = !empty($_POST['flat_id']) ? intval($_POST['flat_id']) : null;

        if (empty($title) || empty($description)) {
            $error = "Title and description are required.";
        } else {
            $db->query("INSERT INTO maintenance_requests (estate_id, user_id, flat_id, title, description, priority, status) 
                        VALUES (?, ?, ?, ?, ?, ?, 'open')", [
                $estate_id, $user_id, $flat_id, $title, $description, $priority
            ]);

            $message = "Maintenance request submitted successfully! Our facility team will inspect the issue shortly.";
            logAudit($conn, "Submit Maintenance Request", "Maintenance", "Issue reported: " . $title);
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

$flats = $db->fetchAll("SELECT f.id, f.number, b.name as building_name 
                        FROM flats f 
                        JOIN buildings b ON f.building_id = b.id 
                        WHERE f.estate_id = ?
                        ORDER BY b.name, f.number ASC", [$estate_id]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Issue - <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= APP_VERSION ?>">
    <link rel="stylesheet" href="assets/css/components.css?v=<?= APP_VERSION ?>">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 1.5rem;
        }
        .container-card {
            background: #ffffff;
            padding: 3rem 2.5rem;
            border-radius: var(--radius-xl);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 520px;
            position: relative;
            overflow: hidden;
        }
        .container-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 6px;
            background: var(--primary);
        }
    </style>
</head>
<body>
    <div class="container-card">
        <div style="width: 56px; height: 56px; background: var(--primary-light); color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem;">
            <i data-lucide="wrench" style="width: 32px; height: 32px;"></i>
        </div>

        <h1 style="font-size: 1.75rem; font-weight: 700; text-align: center; color: var(--text-primary); margin-bottom: 0.5rem;">Report Facility Issue</h1>
        <p style="text-align: center; color: var(--text-muted); font-size: 0.95rem; margin-bottom: 2rem;">Submit a ticket for maintenance or repair work</p>

        <?php if (!empty($message)): ?>
            <div class="badge badge-success" style="width: 100%; padding: 0.85rem; font-size: 0.9rem; margin-bottom: 1.5rem; text-align: center;">
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="badge badge-danger" style="width: 100%; padding: 0.85rem; font-size: 0.9rem; margin-bottom: 1.5rem; text-align: center;">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <?= csrfField() ?>
            <div class="form-group">
                <label class="form-label">Select Unit / Flat</label>
                <select name="flat_id" class="form-control" required>
                    <option value="">Select Unit...</option>
                    <?php foreach ($flats as $f): ?>
                        <option value="<?= $f['id'] ?>"><?= e($f['building_name']) ?> - Unit <?= e($f['number']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Issue Title</label>
                <input type="text" name="title" class="form-control" placeholder="e.g. Water leak in bathroom" required>
            </div>

            <div class="form-group">
                <label class="form-label">Priority Level</label>
                <select name="priority" class="form-control">
                    <option value="low">Low Priority</option>
                    <option value="medium" selected>Medium Priority</option>
                    <option value="high">High Priority</option>
                    <option value="emergency">Emergency Repair</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Detailed Description</label>
                <textarea name="description" class="form-control" rows="4" placeholder="Please describe the issue in detail..." required></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.85rem; font-size: 1rem; margin-top: 1rem;">
                Submit Request <i data-lucide="send" style="width: 18px; height: 18px;"></i>
            </button>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (window.lucide) window.lucide.createIcons();
        });
    </script>
</body>
</html>
