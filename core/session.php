<?php
/**
 * Session Management
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Set a flash message
 * 
 * @param string $type ('success', 'error', 'info', 'warning')
 * @param string $message
 */
function set_flash_message($type, $message) {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash messages
 * 
 * @return array
 */
function get_flash_messages() {
    $messages = isset($_SESSION['flash_messages']) ? $_SESSION['flash_messages'] : [];
    
    // Support legacy single message if it exists
    if (isset($_SESSION['flash_msg'])) {
        $messages[] = $_SESSION['flash_msg'];
        unset($_SESSION['flash_msg']);
    }
    
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Display flash messages if they exist (Legacy support)
 */
function display_flash_message() {
    $messages = get_flash_messages();
    foreach ($messages as $msg) {
        $type = $msg['type'] === 'error' ? 'danger' : $msg['type'];
        echo '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">
                ' . $msg['message'] . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
    }
}
