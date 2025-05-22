<?php

// Set memory limit for tests
ini_set('memory_limit', '2G');

// Disable Carbon's test mode which can cause memory issues
if (class_exists('\Carbon\Carbon')) {
    \Carbon\Carbon::setTestNow(null);
    
    // Disable Carbon's testing helpers
    $reflectionClass = new ReflectionClass('\Carbon\Carbon');
    if ($reflectionClass->hasProperty('testingAidsEnabled')) {
        $property = $reflectionClass->getProperty('testingAidsEnabled');
        $property->setAccessible(true);
        $property->setValue(false);
    }
    
    // Clear Carbon macros
    if ($reflectionClass->hasProperty('macros')) {
        $property = $reflectionClass->getProperty('macros');
        $property->setAccessible(true);
        $property->setValue([]);
    }
}

// Force aggressive garbage collection
gc_enable();
gc_collect_cycles();

// Optimize memory usage
ini_set('zend.enable_gc', 1);
ini_set('max_execution_time', 300);

// Include Composer's autoloader
require __DIR__ . '/../vendor/autoload.php';

// Register a shutdown function to clean up memory
register_shutdown_function(function() {
    gc_collect_cycles();
});
