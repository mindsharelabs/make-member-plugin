<?php
/**
 * Configuration Management for Make Santa Fe Membership Plugin
 * 
 * This file provides centralized configuration management and default settings
 * for the plugin, improving maintainability and reducing technical debt.
 * 
 * @version 1.4.0
 * @author Make Santa Fe
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Make Santa Fe Plugin Configuration Class
 */
class MakeSF_Config {
    
    /**
     * Default configuration values
     */
    private static $defaults = array(
        // Sign-in system configuration
        'signin_strategy' => 'hybrid', // 'full', 'hybrid', 'search'
        'enable_performance_logging' => true,
        'search_debounce_ms' => 300,
        'min_search_length' => 2,
        'max_search_results' => 20,
        'auto_return_delay' => 8000,
        'error_display_delay' => 5000,
        
        // Security settings
        'enable_nonce_verification' => true,
        'enable_input_sanitization' => true,
        'enable_capability_checks' => true,
        
        // Performance settings
        'enable_member_caching' => true,
        'cache_expiration' => 300, // 5 minutes
        'enable_query_optimization' => true,
        
        // Volunteer system integration
        'enable_volunteer_integration' => true,
        'volunteer_auto_signout' => false,
        'volunteer_task_preview_count' => 8,
        
        // UI/UX settings
        'enable_keyboard_shortcuts' => true,
        'enable_search_feedback' => true,
        'enable_loading_animations' => true,
        
        // Debug and logging
        'enable_debug_logging' => false,
        'log_performance_metrics' => true,
        'log_security_events' => true,
    );
    
    /**
     * Get configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $default Default value if not found
     * @return mixed Configuration value
     */
    public static function get($key, $default = null) {
        $option_key = 'makesf_' . $key;
        $default_value = $default !== null ? $default : (self::$defaults[$key] ?? null);
        
        return get_option($option_key, $default_value);
    }
    
    /**
     * Set configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     * @return bool True on success, false on failure
     */
    public static function set($key, $value) {
        $option_key = 'makesf_' . $key;
        return update_option($option_key, $value);
    }
    
    /**
     * Get all configuration values
     * 
     * @return array All configuration values
     */
    public static function get_all() {
        $config = array();
        
        foreach (self::$defaults as $key => $default_value) {
            $config[$key] = self::get($key, $default_value);
        }
        
        return $config;
    }
    
    /**
     * Get JavaScript configuration object
     * 
     * @return array Configuration for JavaScript localization
     */
    public static function get_js_config() {
        return array(
            'loadingStrategy' => self::get('signin_strategy'),
            'enablePerformanceLogging' => self::get('enable_performance_logging'),
            'searchDebounceMs' => self::get('search_debounce_ms'),
            'minSearchLength' => self::get('min_search_length'),
            'maxSearchResults' => self::get('max_search_results'),
            'autoReturnDelay' => self::get('auto_return_delay'),
            'errorDisplayDelay' => self::get('error_display_delay'),
            'enableVolunteerIntegration' => self::get('enable_volunteer_integration'),
            'enableKeyboardShortcuts' => self::get('enable_keyboard_shortcuts'),
            'enableSearchFeedback' => self::get('enable_search_feedback'),
            'enableLoadingAnimations' => self::get('enable_loading_animations'),
        );
    }
    
    /**
     * Reset configuration to defaults
     * 
     * @return bool True on success
     */
    public static function reset_to_defaults() {
        $success = true;
        
        foreach (self::$defaults as $key => $value) {
            if (!self::set($key, $value)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Validate configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $value Value to validate
     * @return bool True if valid
     */
    public static function validate($key, $value) {
        switch ($key) {
            case 'signin_strategy':
                return in_array($value, array('full', 'hybrid', 'search'));
                
            case 'search_debounce_ms':
            case 'min_search_length':
            case 'max_search_results':
            case 'auto_return_delay':
            case 'error_display_delay':
            case 'cache_expiration':
            case 'volunteer_task_preview_count':
                return is_numeric($value) && $value >= 0;
                
            case 'enable_performance_logging':
            case 'enable_nonce_verification':
            case 'enable_input_sanitization':
            case 'enable_capability_checks':
            case 'enable_member_caching':
            case 'enable_query_optimization':
            case 'enable_volunteer_integration':
            case 'volunteer_auto_signout':
            case 'enable_keyboard_shortcuts':
            case 'enable_search_feedback':
            case 'enable_loading_animations':
            case 'enable_debug_logging':
            case 'log_performance_metrics':
            case 'log_security_events':
                return is_bool($value);
                
            default:
                return true; // Allow unknown keys for extensibility
        }
    }
    
    /**
     * Get default values
     * 
     * @return array Default configuration values
     */
    public static function get_defaults() {
        return self::$defaults;
    }
    
    /**
     * Check if debug mode is enabled
     * 
     * @return bool True if debug mode is enabled
     */
    public static function is_debug_enabled() {
        return self::get('enable_debug_logging') || (defined('WP_DEBUG') && WP_DEBUG);
    }
    
    /**
     * Log debug message if debug is enabled
     * 
     * @param string $message Debug message
     * @param string $context Context for the message
     */
    public static function debug_log($message, $context = 'MakeSF') {
        if (self::is_debug_enabled()) {
            error_log($context . ': ' . $message);
        }
    }
    
    /**
     * Log security event
     * 
     * @param string $event Security event description
     * @param array $data Additional event data
     */
    public static function log_security_event($event, $data = array()) {
        if (self::get('log_security_events')) {
            $log_data = array(
                'event' => $event,
                'timestamp' => current_time('mysql'),
                'user_id' => get_current_user_id(),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'data' => $data
            );
            
            error_log('MakeSF Security Event: ' . json_encode($log_data));
        }
    }
    
    /**
     * Log performance metric
     * 
     * @param string $operation Operation name
     * @param float $duration Duration in milliseconds
     * @param array $metadata Additional metadata
     */
    public static function log_performance($operation, $duration, $metadata = array()) {
        if (self::get('log_performance_metrics')) {
            $log_data = array(
                'operation' => $operation,
                'duration_ms' => round($duration, 2),
                'timestamp' => current_time('mysql'),
                'metadata' => $metadata
            );
            
            error_log('MakeSF Performance: ' . json_encode($log_data));
        }
    }
}

/**
 * Convenience functions for configuration access
 */

/**
 * Get Make Santa Fe configuration value
 * 
 * @param string $key Configuration key
 * @param mixed $default Default value
 * @return mixed Configuration value
 */
function makesf_config($key, $default = null) {
    return MakeSF_Config::get($key, $default);
}

/**
 * Set Make Santa Fe configuration value
 * 
 * @param string $key Configuration key
 * @param mixed $value Configuration value
 * @return bool Success status
 */
function makesf_set_config($key, $value) {
    return MakeSF_Config::set($key, $value);
}

/**
 * Log debug message for Make Santa Fe
 * 
 * @param string $message Debug message
 * @param string $context Context
 */
function makesf_debug_log($message, $context = 'MakeSF') {
    MakeSF_Config::debug_log($message, $context);
}

/**
 * Log security event for Make Santa Fe
 * 
 * @param string $event Event description
 * @param array $data Event data
 */
function makesf_log_security($event, $data = array()) {
    MakeSF_Config::log_security_event($event, $data);
}

/**
 * Log performance metric for Make Santa Fe
 * 
 * @param string $operation Operation name
 * @param float $duration Duration in milliseconds
 * @param array $metadata Additional metadata
 */
function makesf_log_performance($operation, $duration, $metadata = array()) {
    MakeSF_Config::log_performance($operation, $duration, $metadata);
}