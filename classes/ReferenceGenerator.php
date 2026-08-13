<?php
// classes/ReferenceGenerator.php - Centralized Format-Driven Reference Generator

require_once __DIR__ . '/Database.php';

class ReferenceGenerator {
    
    /**
     * Generate next sequential reference code for a given domain entity
     * 
     * @param string $entity Type ('person', 'resident', 'property', 'visitor', 'invoice', 'receipt', 'ticket', 'staff', 'vehicle', 'payment')
     * @return string
     */
    public static function generate($entity) {
        $db = Database::getInstance();
        
        switch ($entity) {
            case 'person':
                $prefix = self::getSetting('person_id_prefix', 'PRS-');
                $count = $db->fetchOne("SELECT COUNT(*) as c FROM persons")['c'] ?? 0;
                return $prefix . sprintf('%06d', $count + 1);

            case 'resident':
                $prefix = self::getSetting('resident_id_prefix', 'RES-');
                $count = $db->fetchOne("SELECT COUNT(*) as c FROM residents")['c'] ?? 0;
                return $prefix . sprintf('%06d', $count + 1);

            case 'property':
                $prefix = self::getSetting('property_code_prefix', 'PROP-');
                $count = $db->fetchOne("SELECT COUNT(*) as c FROM property_units")['c'] ?? 0;
                return $prefix . sprintf('%06d', $count + 1);

            case 'visitor':
                $prefix = self::getSetting('visitor_pass_prefix', 'VST-');
                $randomCode = strtoupper(bin2hex(random_bytes(3))); // 6 char random hex
                return [
                    'access_code' => rand(100000, 999999),
                    'formatted' => $prefix . $randomCode
                ];

            case 'invoice':
                $prefix = self::getSetting('invoice_prefix', 'INV-');
                $year = date('Y');
                $count = $db->fetchOne("SELECT COUNT(*) as c FROM invoices")['c'] ?? 0;
                return $prefix . $year . '-' . sprintf('%05d', $count + 1);

            case 'receipt':
                $prefix = self::getSetting('receipt_prefix', 'REC-');
                $year = date('Y');
                $count = $db->fetchOne("SELECT COUNT(*) as c FROM payments")['c'] ?? 0;
                return $prefix . $year . '-' . sprintf('%05d', $count + 1);

            case 'ticket':
                $prefix = self::getSetting('ticket_prefix', 'TKT-');
                $count = $db->fetchOne("SELECT COUNT(*) as c FROM maintenance_tickets")['c'] ?? 0;
                return $prefix . sprintf('%05d', $count + 1);

            case 'staff':
                $prefix = self::getSetting('staff_prefix', 'STF-');
                $count = $db->fetchOne("SELECT COUNT(*) as c FROM estate_staff")['c'] ?? 0;
                return $prefix . sprintf('%05d', $count + 1);

            case 'vehicle':
                $prefix = self::getSetting('vehicle_prefix', 'VEH-');
                $count = $db->fetchOne("SELECT COUNT(*) as c FROM vehicles")['c'] ?? 0;
                return $prefix . sprintf('%05d', $count + 1);

            case 'payment':
                $prefix = 'PAY-';
                $count = $db->fetchOne("SELECT COUNT(*) as c FROM payments")['c'] ?? 0;
                return $prefix . date('Ymd') . '-' . sprintf('%04d', $count + 1);

            default:
                return 'REF-' . time() . rand(100, 999);
        }
    }

    private static function getSetting($key, $default = '') {
        $db = Database::getInstance();
        $res = $db->fetchOne("SELECT setting_value FROM system_settings WHERE setting_key = ?", [$key]);
        return (!empty($res['setting_value'])) ? $res['setting_value'] : $default;
    }
}
