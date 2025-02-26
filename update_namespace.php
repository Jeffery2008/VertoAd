<?php
/**
 * Namespace Update Script
 * 
 * This script updates all references to HFI\UtilityCenter to VertoAD\Core
 * and App to VertoAD\Core throughout the codebase
 */

// Configuration
$searchDir = __DIR__;
$oldNamespaces = [
    'HFI\UtilityCenter',
    'App'
];
$newNamespace = 'VertoAD\Core';
$oldJsPrefix = 'HFI';
$newJsPrefix = 'VertoAD';
$oldAppName = 'HFI Utility Center';
$newAppName = 'VertoAD';

// File extensions to check
$fileExtensions = [
    'php', 'js', 'html', 'md', 'css', 'json', 'sql'
];

// Files to skip (relative to search directory)
$skipFiles = [
    'update_namespace.php', // Skip this script
    'vendor'                // Skip vendor directory
];

// Counter
$filesModified = 0;
$filesScanned = 0;
$replacements = 0;

echo "Starting namespace update to '{$newNamespace}'...\n";
foreach ($oldNamespaces as $oldNamespace) {
    echo "- Converting '{$oldNamespace}' to '{$newNamespace}'\n";
}
echo "Starting JavaScript prefix update from '{$oldJsPrefix}' to '{$newJsPrefix}'...\n";
echo "Starting app name update from '{$oldAppName}' to '{$newAppName}'...\n";

/**
 * Recursive directory scanner
 */
function scanDirectory($dir, $extensions, $skipFiles) {
    global $filesScanned, $filesModified, $replacements;
    global $oldNamespaces, $newNamespace, $oldJsPrefix, $newJsPrefix, $oldAppName, $newAppName;
    
    $files = scandir($dir);
    
    foreach ($files as $file) {
        // Skip dots and excluded files/directories
        if ($file === '.' || $file === '..' || in_array($file, $skipFiles)) {
            continue;
        }
        
        $path = $dir . '/' . $file;
        
        // If directory, scan recursively
        if (is_dir($path)) {
            scanDirectory($path, $extensions, $skipFiles);
            continue;
        }
        
        // Check file extension
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if (!in_array($extension, $extensions)) {
            continue;
        }
        
        $filesScanned++;
        
        // Read file content
        $content = file_get_contents($path);
        if ($content === false) {
            echo "Error reading file: {$path}\n";
            continue;
        }
        
        // Perform replacements
        $newContent = $content;
        $fileReplacements = 0;
        
        foreach ($oldNamespaces as $oldNamespace) {
            // Replace namespace declarations
            $newContent = str_replace(
                "namespace {$oldNamespace}",
                "namespace {$newNamespace}",
                $newContent
            );
            
            // Replace use statements
            $newContent = str_replace(
                "use {$oldNamespace}\\",
                "use {$newNamespace}\\",
                $newContent
            );
            
            // Replace fully qualified class names
            $newContent = str_replace(
                "\\{$oldNamespace}\\",
                "\\{$newNamespace}\\",
                $newContent
            );
            
            // Handle the case where App is used directly without a leading backslash
            if ($oldNamespace === 'App') {
                $newContent = str_replace(
                    "new App\\",
                    "new {$newNamespace}\\",
                    $newContent
                );
                
                $newContent = str_replace(
                    "extends App\\",
                    "extends {$newNamespace}\\",
                    $newContent
                );
                
                $newContent = str_replace(
                    "implements App\\",
                    "implements {$newNamespace}\\",
                    $newContent
                );
                
                // Replace App:: static calls
                $newContent = str_replace(
                    "App::",
                    "{$newNamespace}::",
                    $newContent
                );
            }
        }
        
        // Replace JavaScript prefixes
        $newContent = str_replace(
            $oldJsPrefix . 'AdClient',
            $newJsPrefix . 'AdClient',
            $newContent
        );
        
        $newContent = str_replace(
            $oldJsPrefix . 'Pixel',
            $newJsPrefix . 'Pixel',
            $newContent
        );
        
        $newContent = str_replace(
            $oldJsPrefix . 'Track',
            $newJsPrefix . 'Track',
            $newContent
        );
        
        $newContent = str_replace(
            $oldJsPrefix . '_TRACKING_URL',
            $newJsPrefix . '_TRACKING_URL',
            $newContent
        );
        
        $newContent = str_replace(
            $oldJsPrefix . ' Ad:',
            $newJsPrefix . ' Ad:',
            $newContent
        );
        
        $newContent = str_replace(
            $oldJsPrefix . ' Pixel Error:',
            $newJsPrefix . ' Pixel Error:',
            $newContent
        );
        
        // Replace app name
        $newContent = str_replace(
            $oldAppName,
            $newAppName,
            $newContent
        );
        
        // Replace CSS classes and IDs
        $newContent = str_replace(
            'hfi-ad',
            'vertoad-ad',
            $newContent
        );
        
        $newContent = str_replace(
            'hfi-pixel.js',
            'vertoad-pixel.js',
            $newContent
        );
        
        $newContent = str_replace(
            'hfi_visitor_id',
            'vertoad_visitor_id',
            $newContent
        );
        
        $newContent = str_replace(
            'hfi_click_id',
            'vertoad_click_id',
            $newContent
        );
        
        // Check if file was modified
        if ($newContent !== $content) {
            // Count replacements for each namespace
            foreach ($oldNamespaces as $oldNamespace) {
                $fileReplacements += substr_count($newContent, str_replace($oldNamespace, $newNamespace, $content));
            }
            
            // Count other replacements
            $fileReplacements += substr_count($newContent, $newJsPrefix) +
                               substr_count($newContent, $newAppName) +
                               substr_count($newContent, 'vertoad-ad') +
                               substr_count($newContent, 'vertoad-pixel.js') +
                               substr_count($newContent, 'vertoad_visitor_id') +
                               substr_count($newContent, 'vertoad_click_id');
            
            $replacements += $fileReplacements;
            $filesModified++;
            
            // Write new content
            $result = file_put_contents($path, $newContent);
            if ($result === false) {
                echo "Error writing file: {$path}\n";
            } else {
                $relPath = str_replace(__DIR__ . '/', '', $path);
                echo "Updated: {$relPath} ({$fileReplacements} replacements)\n";
            }
        }
    }
}

// Start the scan
scanDirectory($searchDir, $fileExtensions, $skipFiles);

// Display summary
echo "\nNamespace update completed!\n";
echo "Files scanned: {$filesScanned}\n";
echo "Files modified: {$filesModified}\n";
echo "Total replacements: {$replacements}\n";

// Special file handling for renamed JS files
$jsFilesToRename = [
    'static/js/hfi-pixel.js' => 'static/js/vertoad-pixel.js',
    'static/js/adclient.js' => 'static/js/vertoad-adclient.js'
];

foreach ($jsFilesToRename as $oldPath => $newPath) {
    if (file_exists($oldPath)) {
        if (rename($oldPath, $newPath)) {
            echo "Renamed: {$oldPath} to {$newPath}\n";
        } else {
            echo "Error renaming: {$oldPath}\n";
        }
    }
}

echo "Done!\n"; 