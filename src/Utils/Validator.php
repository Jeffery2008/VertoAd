<?php
namespace Utils;

class Validator {
    private $errors = [];
    private $data = [];
    private static $instance = null;

    private function __construct() {}

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function validate($data, $rules) {
        $this->data = $data;
        $this->errors = [];

        foreach ($rules as $field => $fieldRules) {
            if (!is_array($fieldRules)) {
                $fieldRules = explode('|', $fieldRules);
            }

            foreach ($fieldRules as $rule) {
                $parameters = [];
                
                if (strpos($rule, ':') !== false) {
                    list($rule, $parameter) = explode(':', $rule);
                    $parameters = explode(',', $parameter);
                }

                $value = isset($this->data[$field]) ? $this->data[$field] : null;
                $methodName = 'validate' . ucfirst($rule);

                if (method_exists($this, $methodName)) {
                    if (!$this->$methodName($field, $value, $parameters)) {
                        $this->addError($field, $rule, $parameters);
                    }
                }
            }
        }

        return empty($this->errors);
    }

    private function validateRequired($field, $value) {
        if (is_array($value)) {
            return !empty($value);
        }
        return $value !== null && $value !== '';
    }

    private function validateEmail($field, $value) {
        return empty($value) || filter_var($value, FILTER_VALIDATE_EMAIL);
    }

    private function validateMin($field, $value, $parameters) {
        $min = $parameters[0];
        if (is_numeric($value)) {
            return $value >= $min;
        }
        return mb_strlen($value) >= $min;
    }

    private function validateMax($field, $value, $parameters) {
        $max = $parameters[0];
        if (is_numeric($value)) {
            return $value <= $max;
        }
        return mb_strlen($value) <= $max;
    }

    private function validateNumeric($field, $value) {
        return empty($value) || is_numeric($value);
    }

    private function validateInt($field, $value) {
        return empty($value) || filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    private function validateFloat($field, $value) {
        return empty($value) || filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
    }

    private function validateUrl($field, $value) {
        return empty($value) || filter_var($value, FILTER_VALIDATE_URL);
    }

    private function validateDate($field, $value) {
        if (empty($value)) {
            return true;
        }
        
        $date = date_parse($value);
        return $date['error_count'] === 0 && $date['warning_count'] === 0;
    }

    private function validateDateFormat($field, $value, $parameters) {
        if (empty($value)) {
            return true;
        }
        
        $format = $parameters[0];
        $dateTime = \DateTime::createFromFormat($format, $value);
        return $dateTime && $dateTime->format($format) === $value;
    }

    private function validateRegex($field, $value, $parameters) {
        return empty($value) || preg_match($parameters[0], $value);
    }

    private function validateUnique($field, $value, $parameters) {
        if (empty($value)) {
            return true;
        }

        $table = $parameters[0];
        $column = $parameters[1] ?? $field;
        $exceptId = $parameters[2] ?? null;

        global $db;
        
        try {
            $sql = "SELECT COUNT(*) FROM $table WHERE $column = ?";
            $params = [$value];

            if ($exceptId !== null) {
                $sql .= " AND id != ?";
                $params[] = $exceptId;
            }

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn() === '0';
        } catch (\Exception $e) {
            Logger::error("Validation error (unique): " . $e->getMessage());
            return false;
        }
    }

    private function validateIn($field, $value, $parameters) {
        return empty($value) || in_array($value, $parameters);
    }

    private function validateJson($field, $value) {
        if (empty($value)) {
            return true;
        }
        
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    private function validateBoolean($field, $value) {
        return empty($value) || in_array($value, [true, false, 0, 1, '0', '1'], true);
    }

    private function validateArray($field, $value) {
        return is_array($value);
    }

    private function addError($field, $rule, $parameters = []) {
        $message = $this->getErrorMessage($field, $rule, $parameters);
        $this->errors[$field][] = $message;
    }

    private function getErrorMessage($field, $rule, $parameters) {
        $messages = [
            'required' => 'The :field field is required.',
            'email' => 'The :field must be a valid email address.',
            'min' => 'The :field must be at least :param characters.',
            'max' => 'The :field may not be greater than :param characters.',
            'numeric' => 'The :field must be a number.',
            'int' => 'The :field must be an integer.',
            'float' => 'The :field must be a decimal number.',
            'url' => 'The :field must be a valid URL.',
            'date' => 'The :field must be a valid date.',
            'dateFormat' => 'The :field must match the format :param.',
            'regex' => 'The :field format is invalid.',
            'unique' => 'The :field has already been taken.',
            'in' => 'The selected :field is invalid.',
            'json' => 'The :field must be a valid JSON string.',
            'boolean' => 'The :field must be true or false.',
            'array' => 'The :field must be an array.'
        ];

        $message = $messages[$rule] ?? 'The :field field is invalid.';
        $message = str_replace(':field', $field, $message);
        
        if (!empty($parameters)) {
            $message = str_replace(':param', $parameters[0], $message);
        }

        return $message;
    }

    public function getErrors() {
        return $this->errors;
    }

    public function getFirstError() {
        return !empty($this->errors) ? reset($this->errors)[0] : null;
    }
}
