<?php

namespace VertoAD\Core\Models;

use VertoAD\Core\Models\BaseModel;

class AdTargeting extends BaseModel
{
    protected $tableName = 'ad_targeting';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Add a targeting criterion to an advertisement
     * 
     * @param int $adId Advertisement ID
     * @param string $targetType Type of targeting (location, device, time)
     * @param string $targetValue Value to target
     * @return int|bool The new targeting ID or false on failure
     */
    public function addTargeting($adId, $targetType, $targetValue)
    {
        return $this->db->insert($this->tableName, [
            'ad_id' => $adId,
            'target_type' => $targetType,
            'target_value' => $targetValue,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Add multiple targeting criteria to an advertisement
     * 
     * @param int $adId Advertisement ID
     * @param string $targetType Type of targeting (location, device, time)
     * @param array $targetValues Values to target
     * @return bool Success status
     */
    public function addMultipleTargeting($adId, $targetType, array $targetValues)
    {
        $success = true;
        foreach ($targetValues as $value) {
            if (!empty($value)) {
                $result = $this->addTargeting($adId, $targetType, $value);
                if (!$result) {
                    $success = false;
                }
            }
        }
        return $success;
    }

    /**
     * Get all targeting criteria for an advertisement
     * 
     * @param int $adId Advertisement ID
     * @return array Targeting criteria
     */
    public function getByAdId($adId)
    {
        $query = "SELECT * FROM {$this->tableName} WHERE ad_id = :ad_id";
        return $this->db->fetchAll($query, ['ad_id' => $adId]);
    }

    /**
     * Get targeting criteria for an advertisement by type
     * 
     * @param int $adId Advertisement ID
     * @param string $targetType Type of targeting (location, device, time)
     * @return array Targeting criteria
     */
    public function getByAdIdAndType($adId, $targetType)
    {
        $query = "SELECT * FROM {$this->tableName} WHERE ad_id = :ad_id AND target_type = :target_type";
        return $this->db->fetchAll($query, [
            'ad_id' => $adId,
            'target_type' => $targetType
        ]);
    }

    /**
     * Delete all targeting criteria for an advertisement
     * 
     * @param int $adId Advertisement ID
     * @return bool Success status
     */
    public function deleteByAdId($adId)
    {
        $query = "DELETE FROM {$this->tableName} WHERE ad_id = :ad_id";
        return $this->db->execute($query, ['ad_id' => $adId]);
    }

    /**
     * Delete specific targeting criteria for an advertisement
     * 
     * @param int $adId Advertisement ID
     * @param string $targetType Type of targeting
     * @return bool Success status
     */
    public function deleteByAdIdAndType($adId, $targetType)
    {
        $query = "DELETE FROM {$this->tableName} WHERE ad_id = :ad_id AND target_type = :target_type";
        return $this->db->execute($query, [
            'ad_id' => $adId,
            'target_type' => $targetType
        ]);
    }

    /**
     * Check if an advertisement matches targeting criteria
     * 
     * @param int $adId Advertisement ID
     * @param array $criteria Array of criteria to match against (key = type, value = value)
     * @return bool Whether the ad matches the criteria
     */
    public function matchesTargeting($adId, array $criteria)
    {
        $targetingData = $this->getByAdId($adId);
        
        // If no targeting is specified, the ad matches all criteria
        if (empty($targetingData)) {
            return true;
        }
        
        // Group targeting by type
        $targetingByType = [];
        foreach ($targetingData as $targeting) {
            $type = $targeting['target_type'];
            if (!isset($targetingByType[$type])) {
                $targetingByType[$type] = [];
            }
            $targetingByType[$type][] = $targeting['target_value'];
        }
        
        // Check each criteria type
        foreach ($criteria as $type => $value) {
            // Skip if this type is not targeted
            if (!isset($targetingByType[$type])) {
                continue;
            }
            
            // Special handling for time targeting
            if ($type === 'time') {
                // Get time range
                if (isset($targetingByType['time_start']) && isset($targetingByType['time_end'])) {
                    $startTime = $targetingByType['time_start'][0] ?? '00:00';
                    $endTime = $targetingByType['time_end'][0] ?? '23:59';
                    
                    // Check if current time is within the range
                    if ($value < $startTime || $value > $endTime) {
                        return false;
                    }
                }
                continue;
            }
            
            // For this type, at least one value must match
            $matches = false;
            foreach ($targetingByType[$type] as $targetValue) {
                // For location, check if the value starts with the target
                if ($type === 'location') {
                    if (strpos($value, $targetValue) === 0) {
                        $matches = true;
                        break;
                    }
                } else {
                    // For other types, exact match
                    if ($value === $targetValue) {
                        $matches = true;
                        break;
                    }
                }
            }
            
            // If this type doesn't match, the ad doesn't match
            if (!$matches) {
                return false;
            }
        }
        
        return true;
    }
} 