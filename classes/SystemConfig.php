<?php
// classes/SystemConfig.php - Standalone Single-Estate Configuration Manager

require_once __DIR__ . '/Database.php';

class SystemConfig {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Fetch all system settings as key-value pairs
     */
    public function getSettings() {
        $rows = $this->db->fetchAll("SELECT setting_key, setting_value FROM system_settings");
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }

    /**
     * Get single setting value with fallback
     */
    public function get($key, $default = '') {
        $row = $this->db->fetchOne("SELECT setting_value FROM system_settings WHERE setting_key = ?", [$key]);
        return ($row && isset($row['setting_value'])) ? $row['setting_value'] : $default;
    }

    /**
     * Update system settings array
     */
    public function updateSettings(array $settings) {
        $stmt = $this->db->getPdo()->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($settings as $key => $value) {
            $stmt->execute([$key, $value]);
        }
        return true;
    }

    /**
     * Fetch active tariffs
     */
    public function getTariffs($activeOnly = true) {
        $sql = "SELECT * FROM tariffs";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY name ASC";
        return $this->db->fetchAll($sql);
    }

    /**
     * Save or update tariff
     */
    public function saveTariff(array $data) {
        if (!empty($data['id']) && intval($data['id']) > 0) {
            return $this->db->query("UPDATE tariffs SET name = ?, category = ?, amount = ?, frequency = ?, due_days = ?, penalty_percent = ?, is_active = ? WHERE id = ?", [
                $data['name'], $data['category'], $data['amount'], $data['frequency'], $data['due_days'], $data['penalty_percent'], $data['is_active'] ?? 1, $data['id']
            ]);
        } else {
            return $this->db->query("INSERT INTO tariffs (name, category, amount, frequency, due_days, penalty_percent, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)", [
                $data['name'], $data['category'], $data['amount'], $data['frequency'], $data['due_days'], $data['penalty_percent'], $data['is_active'] ?? 1
            ]);
        }
    }

    /**
     * Remove tariff
     */
    public function deleteTariff($id) {
        return $this->db->query("DELETE FROM tariffs WHERE id = ?", [$id]);
    }
}
