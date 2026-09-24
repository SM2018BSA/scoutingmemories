<?php

namespace ScoutingMemories\Forms\Forms;

/**
 * FormHandler
 *
 * Base abstract class for form rendering and validation.
 */
abstract class FormHandler {

    /**
     * Render a view template with extracted variables
     *
     * @param string $viewPath Relative path to view (e.g. 'forms/memory-form')
     * @param array $data Variables to pass to the view
     * @return string
     */
    protected static function renderView(string $viewPath, array $data = []): string {
        $file = SM_FORMS_PLUGIN_DIR . 'views/' . ltrim($viewPath, '/') . '.php';

        if (!file_exists($file)) {
            return "<!-- View not found: {$viewPath} -->";
        }

        extract($data);
        ob_start();
        include $file;
        return ob_get_clean();
    }

    /**
     * Verify nonce
     *
     * @param string $action
     * @param string $name
     * @return bool
     */
    protected static function verifyNonce(string $action, string $name = '_sm_nonce'): bool {
        return isset($_POST[$name]) && wp_verify_nonce($_POST[$name], $action);
    }
}
