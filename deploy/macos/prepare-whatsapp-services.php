<?php

// Run on the deployment Mac. Generates plists only; does not install/start services.
$root = realpath(__DIR__.'/../..');
$directory = $argv[1] ?? null;
if (! $directory || ! is_dir($directory)) {
    fwrite(STDERR, "Usage: php deploy/macos/prepare-whatsapp-services.php EXISTING_OUTPUT_DIRECTORY\n");
    exit(1);
}
function xmlValue(string $value): string
{
    return htmlspecialchars($value, ENT_XML1, 'UTF-8');
}
foreach ([
    'com.churchadmin.whatsapp' => ['queue:work', 'database', '--queue=whatsapp', '--sleep=1', '--tries=5', '--timeout=65', '--max-time=3600'],
    'com.churchadmin.scheduler' => ['schedule:work'],
] as $label => $arguments) {
    $args = array_merge([PHP_BINARY, $root.'/artisan'], $arguments);
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">'."\n";
    $xml .= '<plist version="1.0"><dict><key>Label</key><string>'.$label.'</string><key>ProgramArguments</key><array>';
    foreach ($args as $arg) {
        $xml .= '<string>'.xmlValue($arg).'</string>';
    }
    $xml .= '</array><key>WorkingDirectory</key><string>'.xmlValue($root).'</string><key>RunAtLoad</key><true/><key>KeepAlive</key><true/><key>ThrottleInterval</key><integer>10</integer>';
    foreach (['StandardOutPath', 'StandardErrorPath'] as $key) {
        $xml .= '<key>'.$key.'</key><string>'.xmlValue($root.'/storage/logs/'.$label.'.log').'</string>';
    }
    $xml .= '</dict></plist>';
    $path = rtrim($directory, '/').'/'.$label.'.plist';
    if (file_exists($path)) {
        fwrite(STDERR, "Refusing to overwrite $path\n");
        exit(1);
    }
    file_put_contents($path, $xml);
    chmod($path, 0600);
    echo "Prepared $path\n";
}
