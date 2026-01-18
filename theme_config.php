<?php
/**
 * Castor Plugin - Theme Configuration
 *
 * This file contains theme-specific configurations for the Castor plugin.
 * It helps ensure compatibility across different Piwigo themes.
 *
 * Configuration options per theme:
 * - toolbar_selector: CSS selector to find the toolbar where Cast button should be injected
 * - uses_plugin_actions: Whether the theme supports PLUGIN_PICTURE_ACTIONS variable
 * - menubar_selector: CSS selector for menubar status icon placement
 * - extra_classes: Additional CSS classes to add to buttons for theme compatibility
 */

defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

/**
 * Get all theme configurations
 *
 * @return array All theme configurations
 */
function castor_get_all_theme_configs() {
    static $configs = null;

    if ($configs === null) {
        $configs = [
            // Bootstrap themes - full compatibility
            'bootstrap_darkroom' => [
                'toolbar_selector' => '.navButtons, .navbar-nav',
                'uses_plugin_actions' => true,
                'menubar_selector' => '.navbar-nav',
                'extra_classes' => '',
            ],
            'bootstrapdefault' => [
                'toolbar_selector' => '.navButtons, .navbar-nav',
                'uses_plugin_actions' => true,
                'menubar_selector' => '.navbar-nav',
                'extra_classes' => '',
            ],

            // Elegant theme - needs fallback injection
            'elegant' => [
                'toolbar_selector' => '.imageToolBar, #imageToolBar, .browsePath',
                'uses_plugin_actions' => false,
                'menubar_selector' => '#menubar',
                'extra_classes' => '',
            ],

            // Modus theme
            'modus' => [
                'toolbar_selector' => '.imageToolBar, .browsePath',
                'uses_plugin_actions' => true,
                'menubar_selector' => '#menubar',
                'extra_classes' => '',
            ],

            // Smart Pocket (mobile theme)
            'smartpocket' => [
                'toolbar_selector' => '.actionButtons, .navButtons',
                'uses_plugin_actions' => true,
                'menubar_selector' => '',
                'extra_classes' => '',
            ],

            // Default configuration for unknown themes
            'default' => [
                'toolbar_selector' => '.imageToolBar, #imageToolBar, .actionButtons, .navButtons, .browsePath, .pictureToolbar',
                'uses_plugin_actions' => true,
                'menubar_selector' => '#menubar, .menubar',
                'extra_classes' => '',
            ],
        ];
    }

    return $configs;
}

/**
 * Get default theme configuration
 *
 * @return array Default theme configuration
 */
function castor_get_default_config() {
    return [
        'toolbar_selector' => '.imageToolBar, #imageToolBar, .actionButtons, .navButtons, .browsePath, .pictureToolbar',
        'uses_plugin_actions' => true,
        'menubar_selector' => '#menubar, .menubar',
        'extra_classes' => '',
    ];
}

/**
 * Get theme configuration
 *
 * @param string|null $theme_name The current theme name
 * @return array Theme configuration array
 */
function castor_get_theme_config($theme_name) {
    $configs = castor_get_all_theme_configs();
    $default = castor_get_default_config();

    // Handle null or empty theme name
    if (empty($theme_name)) {
        return $default;
    }

    if (isset($configs[$theme_name])) {
        return $configs[$theme_name];
    }

    return $default;
}

/**
 * Check if current theme needs JavaScript fallback for button injection
 *
 * @param string|null $theme_name The current theme name
 * @return bool True if fallback is needed
 */
function castor_needs_js_fallback($theme_name) {
    $config = castor_get_theme_config($theme_name);
    return !$config['uses_plugin_actions'];
}

/**
 * Get toolbar selector for JavaScript fallback
 *
 * @param string|null $theme_name The current theme name
 * @return string CSS selector string
 */
function castor_get_toolbar_selector($theme_name) {
    $config = castor_get_theme_config($theme_name);
    return $config['toolbar_selector'];
}

/**
 * Get current user theme name safely
 *
 * @return string Theme name or empty string if not available
 */
function castor_get_current_theme() {
    global $user;

    if (isset($user) && is_array($user) && isset($user['theme'])) {
        return $user['theme'];
    }

    return '';
}
