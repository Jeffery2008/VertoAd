<?php

namespace App\Utils;

use Exception;
use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Basic Quill Delta to HTML Renderer with HTML Purifier sanitization.
 *
 * Handles simple formats: paragraphs, bold, italic, links, images.
 * Uses HTML Purifier to sanitize the final output for security.
 */
class QuillRenderer
{
    private static $purifierInstance = null;

    /**
     * Get or create an HTML Purifier instance with a safe configuration.
     *
     * @return HTMLPurifier
     */
    private static function getPurifier(): HTMLPurifier
    {
        if (self::$purifierInstance === null) {
            $config = HTMLPurifier_Config::createDefault();
            
            // Configure allowed elements and attributes (adjust based on needs)
            // Allow basic block tags, inline formatting, links, images
            $config->set('HTML.Allowed', 'p,br,strong,em,b,i,a[href|target|title],img[src|alt|style|width|height]');
            $config->set('HTML.TargetBlank', true); // Ensure links open in new tabs
            $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
            $config->set('URI.DisableExternalResources', false); // Allow external images, but validate URLs
             $config->set('CSS.AllowedProperties', 'text-decoration,max-width,height'); // Allow limited inline styles

            // Setup cache directory (ensure it exists and is writable by the web server)
            $cachePath = ROOT_PATH . '/data/cache/htmlpurifier'; // Assuming ROOT_PATH is defined
            if (!file_exists($cachePath)) {
                @mkdir($cachePath, 0775, true);
            }
            if (is_writable($cachePath)) { // Only set cache path if writable
                $config->set('Cache.SerializerPath', $cachePath);
                $config->set('Cache.SerializerPermissions', 0775);
            } else {
                 error_log("HTML Purifier cache directory not writable: {$cachePath}");
                 // Consider throwing an exception or logging a more severe warning
            }

            self::$purifierInstance = new HTMLPurifier($config);
        }
        return self::$purifierInstance;
    }

    /**
     * Renders Quill Delta JSON to sanitized HTML.
     *
     * @param string $deltaJson Quill Delta operations as a JSON string.
     * @return string Sanitized HTML representation.
     * @throws Exception If JSON decoding fails.
     */
    public static function render(string $deltaJson): string
    {
        $delta = json_decode($deltaJson);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid Quill Delta JSON input.");
        }

        if (!isset($delta->ops) || !is_array($delta->ops)) {
            return ''; 
        }

        // Step 1: Convert Delta to basic, potentially unsafe HTML
        $unsafeHtml = self::deltaToUnsafeHtml($delta->ops);

        // Step 2: Sanitize the generated HTML using HTML Purifier
        $purifier = self::getPurifier();
        $safeHtml = $purifier->purify($unsafeHtml);

        return $safeHtml;
    }

    /**
     * Internal method to convert delta ops to potentially unsafe HTML.
     * (Separated logic for clarity)
     *
     * @param array $ops Array of Quill delta operations.
     * @return string Raw HTML.
     */
    private static function deltaToUnsafeHtml(array $ops): string
    {
        $html = '';
        $currentBlockTag = null;

        foreach ($ops as $op) {
            if (!isset($op->insert)) {
                continue;
            }

            $text = $op->insert;
            $attributes = $op->attributes ?? null;
            $isBlock = strpos($text, "\n") !== false;
            $text = str_replace("\n", '', $text);

            // --- Generate HTML (No escaping here, Purifier handles it) --- 
            $processedContent = $text; // Use raw text initially

            // Handle images
            if (is_object($op->insert) && isset($op->insert->image)) {
                $imgSrc = $op->insert->image;
                // Basic check if it's a potentially valid URL structure
                if (filter_var($imgSrc, FILTER_VALIDATE_URL) !== false) {
                    $altText = $attributes->alt ?? 'Advert image';
                    // Add basic style for responsiveness (Purifier needs CSS.AllowedProperties)
                    $processedContent = sprintf('<img src="%s" alt="%s" style="max-width: 100%%; height: auto;">', $imgSrc, $altText);
                 } else {
                    $processedContent = '[Invalid Image Source]';
                }
            }

            // Inline Formatting
            $inlineTags = [];
            if ($attributes) {
                if (isset($attributes->bold) && $attributes->bold) $inlineTags[] = 'strong';
                if (isset($attributes->italic) && $attributes->italic) $inlineTags[] = 'em';
                if (isset($attributes->link) && filter_var($attributes->link, FILTER_VALIDATE_URL)) {
                    $processedContent = sprintf('<a href="%s">%s</a>', $attributes->link, $processedContent);
                }
            }
            foreach (array_reverse($inlineTags) as $tag) {
                $processedContent = sprintf('<%1$s>%2$s</%1$s>', $tag, $processedContent);
            }
            
            // Block Formatting (Simplified)
            if ($currentBlockTag === null && $processedContent !== '') { // Avoid empty paragraphs
                 $html .= "<p>"; 
                 $currentBlockTag = "p";
            }
           
            $html .= $processedContent;

            if ($isBlock && $currentBlockTag !== null) {
                 $html .= "</". $currentBlockTag .">\n";
                 $currentBlockTag = null; 
            }
        }

        if ($currentBlockTag !== null) {
            $html .= "</". $currentBlockTag .">\n";
        }

        return $html;
    }
} 