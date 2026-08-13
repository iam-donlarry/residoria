<?php
// classes/EstateConfig.php - Estate Configuration Manager

require_once __DIR__ . '/Database.php';

class EstateConfig {
    private $db;
    private $estate_id;

    public function __construct($estate_id = 1) {
        $this->db = Database::getInstance();
        $this->estate_id = $estate_id;
    }

    public function getSettings() {
        $rows = $this->db->fetchAll("SELECT setting_key, setting_value FROM system_settings WHERE estate_id = ?", [$this->estate_id]);
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }

    public function updateSettings($settingsArray) {
        foreach ($settingsArray as $key => $val) {
            $this->db->query("INSERT INTO system_settings (estate_id, setting_key, setting_value) 
                              VALUES (?, ?, ?) 
                              ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", 
                              [$this->estate_id, $key, $val]);
        }
        return true;
    }

    public function getTariffs() {
        return $this->db->fetchAll("SELECT * FROM tariffs WHERE estate_id = ? ORDER BY id ASC", [$this->estate_id]);
    }

    public function saveTariff($data) {
        if (!empty($data['id'])) {
            $sql = "UPDATE tariffs SET name = ?, category = ?, amount = ?, frequency = ?, due_days = ?, penalty_percent = ? WHERE id = ? AND estate_id = ?";
            return $this->db->execute($sql, [
                $data['name'], $data['category'], $data['amount'], $data['frequency'], $data['due_days'], $data['penalty_percent'], $data['id'], $this->estate_id
            ]);
        } else {
            $sql = "INSERT INTO tariffs (estate_id, name, category, amount, frequency, due_days, penalty_percent) VALUES (?, ?, ?, ?, ?, ?, ?)";
            return $this->db->execute($sql, [
                $this->estate_id, $data['name'], $data['category'], $data['amount'], $data['frequency'], $data['due_days'], $data['penalty_percent']
            ]);
        }
    }

    public function deleteTariff($id) {
        return $this->db->execute("DELETE FROM tariffs WHERE id = ? AND estate_id = ?", [$id, $this->estate_id]);
    }
}
