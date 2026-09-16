<?php
/**
 * Hospital Workflow Simulation Module Configuration
 * Path: simulation/config.php
 */

if (!defined('SIMULATION_INIT')) {
    define('SIMULATION_INIT', true);
}

// Load main hospital configuration
require_once __DIR__ . '/../includes/config.php';
if (file_exists(__DIR__ . '/../includes/email_helper.php')) {
    require_once __DIR__ . '/../includes/email_helper.php';
}
if (file_exists(__DIR__ . '/../includes/razorpay_helper.php')) {
    require_once __DIR__ . '/../includes/razorpay_helper.php';
}

// Global Simulation Mode Flag (OFF by default)
$envSimMode = getenv('SIMULATION_MODE');
if ($envSimMode === false && defined('SIMULATION_MODE')) {
    $envSimMode = SIMULATION_MODE;
}
define('SIM_MODE_ACTIVE', ($envSimMode === 'true' || $envSimMode === true || $envSimMode === '1'));

// Paths
define('SIM_MODULE_DIR', __DIR__);
define('SIM_LOG_DIR', __DIR__ . '/logs');
define('SIM_LOG_FILE', SIM_LOG_DIR . '/simulation.log');

// Ensure log directory exists
if (!file_exists(SIM_LOG_DIR)) {
    @mkdir(SIM_LOG_DIR, 0755, true);
}

// Autoload helper for simulation classes
spl_autoload_register(function ($class) {
    $paths = [
        SIM_MODULE_DIR . '/services/' . $class . '.php',
        SIM_MODULE_DIR . '/generators/' . $class . '.php',
        SIM_MODULE_DIR . '/engines/' . $class . '.php',
    ];
    foreach ($paths as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});
