<?php
// Clear signal files utility

$signalsDir = dirname(__FILE__) . '/Signals/';
$files = glob($signalsDir . '*');

$cleared = 0;
foreach ($files as $file) {
    if (is_file($file) && basename($file) !== '.gitkeep') {
        unlink($file);
        $cleared++;
    }
}

echo "Cleared $cleared signal files";
?>