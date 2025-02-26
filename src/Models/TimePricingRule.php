<?php
namespace HFI\UtilityCenter\Models;

/**
 * TimePricingRule - Model for time-based pricing rules
 */
class TimePricingRule extends BaseModel
{
    /**
     * @var string $tableName The database table name
     */
    protected $tableName = 'time_pricing_rules';
    
    /**
     * Day of week constants
     */
    const DAY_SUNDAY = 0;
    const DAY_MONDAY = 1;
    const DAY_TUESDAY = 2;
    const DAY_WEDNESDAY = 3;
    const DAY_THURSDAY = 4;
    const DAY_FRIDAY = 5;
    const DAY_SATURDAY = 6;
    
    /**
     * Get all time pricing rules
     * 
     * @param bool $activeOnly Whether to get only active rules
     * @return array Time pricing rules
     */
    public function getAll($activeOnly = true) 
    {
        $query = "SELECT * FROM {$this->tableName}";
        
        if ($activeOnly) {
            $query .= " WHERE is_active = 1";
        }
        
        $query .= " ORDER BY position_id, day_of_week, start_hour";
        
        return $this->db->query($query);
    }
    
    /**
     * Get rules for a specific position
     * 
     * @param int $positionId The ad position ID
     * @param bool $activeOnly Whether to get only active rules
     * @return array Position pricing rules
     */
    public function getByPosition($positionId, $activeOnly = true) 
    {
        $query = "
            SELECT tpr.*, ap.name as position_name 
            FROM {$this->tableName} tpr
            JOIN ad_positions ap ON tpr.position_id = ap.id
            WHERE tpr.position_id = ?
        ";
        
        if ($activeOnly) {
            $query .= " AND tpr.is_active = 1";
        }
        
        $query .= " ORDER BY tpr.day_of_week, tpr.start_hour";
        
        return $this->db->query($query, [$positionId]);
    }
    
    /**
     * Get rules for a specific day of week
     * 
     * @param int $dayOfWeek Day of week (0-6, Sunday-Saturday)
     * @param bool $activeOnly Whether to get only active rules
     * @return array Time pricing rules for the specified day
     */
    public function getByDayOfWeek($dayOfWeek, $activeOnly = true) 
    {
        $query = "
            SELECT tpr.*, ap.name as position_name 
            FROM {$this->tableName} tpr
            JOIN ad_positions ap ON tpr.position_id = ap.id
            WHERE tpr.day_of_week = ?
        ";
        
        if ($activeOnly) {
            $query .= " AND tpr.is_active = 1";
        }
        
        $query .= " ORDER BY tpr.position_id, tpr.start_hour";
        
        return $this->db->query($query, [$dayOfWeek]);
    }
    
    /**
     * Get a rule by ID
     * 
     * @param int $id The rule ID
     * @return array|null Rule data or null if not found
     */
    public function getById($id) 
    {
        return $this->db->queryOne("
            SELECT tpr.*, ap.name as position_name 
            FROM {$this->tableName} tpr
            JOIN ad_positions ap ON tpr.position_id = ap.id
            WHERE tpr.id = ?
        ", [$id]);
    }
    
    /**
     * Create a new time pricing rule
     * 
     * @param array $data The rule data
     * @return int|false The ID of the new rule or false if creation failed
     */
    public function create($data) 
    {
        $this->validateData([
            'position_id' => 'required|integer',
            'day_of_week' => 'required|integer|min:0|max:6',
            'start_hour' => 'required|integer|min:0|max:23',
            'end_hour' => 'required|integer|min:1|max:24',
            'multiplier' => 'required|numeric|min:0'
        ], $data);
        
        // Check if the position exists
        $positionExists = $this->db->queryOne(
            "SELECT 1 FROM ad_positions WHERE id = ?", 
            [$data['position_id']]
        );
        
        if (!$positionExists) {
            throw new \InvalidArgumentException('Position does not exist');
        }
        
        // Validate that start_hour is less than end_hour
        if ($data['start_hour'] >= $data['end_hour']) {
            throw new \InvalidArgumentException('Start hour must be less than end hour');
        }
        
        // Check for overlapping time rules for the same position and day
        $overlapping = $this->db->query("
            SELECT * FROM {$this->tableName}
            WHERE position_id = ? 
            AND day_of_week = ?
            AND (
                (start_hour < ? AND end_hour > ?) OR
                (start_hour < ? AND end_hour > ?) OR
                (start_hour >= ? AND end_hour <= ?)
            )
        ", [
            $data['position_id'], 
            $data['day_of_week'],
            $data['end_hour'], $data['start_hour'],
            $data['start_hour'], $data['start_hour'],
            $data['start_hour'], $data['end_hour']
        ]);
        
        if (!empty($overlapping)) {
            throw new \InvalidArgumentException('Overlapping time rules exist for this position and day');
        }
        
        return $this->db->insert($this->tableName, [
            'position_id' => $data['position_id'],
            'day_of_week' => $data['day_of_week'],
            'start_hour' => $data['start_hour'],
            'end_hour' => $data['end_hour'],
            'multiplier' => $data['multiplier'],
            'is_active' => $data['is_active'] ?? 1
        ]);
    }
    
    /**
     * Update an existing time pricing rule
     * 
     * @param int $id The rule ID
     * @param array $data The rule data to update
     * @return bool Whether the update was successful
     */
    public function update($id, $data) 
    {
        $this->validateData([
            'position_id' => 'integer',
            'day_of_week' => 'integer|min:0|max:6',
            'start_hour' => 'integer|min:0|max:23',
            'end_hour' => 'integer|min:1|max:24',
            'multiplier' => 'numeric|min:0',
            'is_active' => 'boolean'
        ], $data);
        
        // Get current rule data
        $currentRule = $this->getById($id);
        if (!$currentRule) {
            throw new \InvalidArgumentException('Rule not found');
        }
        
        // Prepare data for validation
        $positionId = $data['position_id'] ?? $currentRule['position_id'];
        $dayOfWeek = $data['day_of_week'] ?? $currentRule['day_of_week'];
        $startHour = $data['start_hour'] ?? $currentRule['start_hour'];
        $endHour = $data['end_hour'] ?? $currentRule['end_hour'];
        
        // Validate that start_hour is less than end_hour
        if ($startHour >= $endHour) {
            throw new \InvalidArgumentException('Start hour must be less than end hour');
        }
        
        // Check for overlapping time rules for the same position and day (excluding this rule)
        $overlapping = $this->db->query("
            SELECT * FROM {$this->tableName}
            WHERE position_id = ? 
            AND day_of_week = ?
            AND id != ?
            AND (
                (start_hour < ? AND end_hour > ?) OR
                (start_hour < ? AND end_hour > ?) OR
                (start_hour >= ? AND end_hour <= ?)
            )
        ", [
            $positionId, 
            $dayOfWeek,
            $id,
            $endHour, $startHour,
            $startHour, $startHour,
            $startHour, $endHour
        ]);
        
        if (!empty($overlapping)) {
            throw new \InvalidArgumentException('Overlapping time rules exist for this position and day');
        }
        
        $updateData = [];
        
        if (isset($data['position_id'])) {
            $updateData['position_id'] = $data['position_id'];
        }
        
        if (isset($data['day_of_week'])) {
            $updateData['day_of_week'] = $data['day_of_week'];
        }
        
        if (isset($data['start_hour'])) {
            $updateData['start_hour'] = $data['start_hour'];
        }
        
        if (isset($data['end_hour'])) {
            $updateData['end_hour'] = $data['end_hour'];
        }
        
        if (isset($data['multiplier'])) {
            $updateData['multiplier'] = $data['multiplier'];
        }
        
        if (isset($data['is_active'])) {
            $updateData['is_active'] = (bool)$data['is_active'] ? 1 : 0;
        }
        
        return $this->db->update(
            $this->tableName,
            $updateData,
            ['id' => $id]
        );
    }
    
    /**
     * Delete a time pricing rule
     * 
     * @param int $id The rule ID
     * @return bool Whether the deletion was successful
     */
    public function delete($id) 
    {
        return $this->db->delete($this->tableName, ['id' => $id]);
    }
    
    /**
     * Get a list of days of the week as options for dropdowns
     * 
     * @return array Days of the week
     */
    public function getDaysOfWeek() 
    {
        return [
            self::DAY_SUNDAY => 'Sunday',
            self::DAY_MONDAY => 'Monday',
            self::DAY_TUESDAY => 'Tuesday',
            self::DAY_WEDNESDAY => 'Wednesday',
            self::DAY_THURSDAY => 'Thursday',
            self::DAY_FRIDAY => 'Friday',
            self::DAY_SATURDAY => 'Saturday'
        ];
    }
    
    /**
     * Get hourly time options for dropdowns
     * 
     * @return array Hours in 24-hour format
     */
    public function getHourOptions() 
    {
        $hours = [];
        for ($i = 0; $i < 24; $i++) {
            $hours[$i] = sprintf("%02d:00", $i);
        }
        // Add the 24:00 option for end hour
        $hours[24] = "24:00";
        
        return $hours;
    }
    
    /**
     * Get the current applicable time pricing rule for a position
     * 
     * @param int $positionId The ad position ID
     * @return array|null The applicable rule or null if none found
     */
    public function getCurrentRule($positionId) 
    {
        $now = time();
        $dayOfWeek = date('w', $now); // 0 (Sunday) to 6 (Saturday)
        $hour = date('G', $now); // 0 to 23
        
        return $this->db->queryOne("
            SELECT * FROM {$this->tableName}
            WHERE position_id = ? 
            AND day_of_week = ? 
            AND start_hour <= ? 
            AND end_hour > ? 
            AND is_active = 1
        ", [$positionId, $dayOfWeek, $hour, $hour]);
    }
} 