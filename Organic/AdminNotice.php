<?php

namespace Organic;

class AdminNotice {
    const TYPE_SUCCESS = 'success';
    const TYPE_ERROR = 'error';
    const TYPE_WARNING = 'warning';
    const TYPE_INFO = 'info';

    private static $notices = [];
    private static $hook_added = null;

    public static function registerHook() {
        if ( static::$hook_added ) {
            return;
        }
        add_action( 'admin_notices', [ static::class, 'showNotices' ] );
        static::$hook_added = true;
    }

    public static function showNotices() {
        foreach ( static::$notices as $notice ) {
            $is_dismissible = $notice['is_dismissible'] ? 'is-dismissible' : '';
            $div = "
            <div class=\"notice notice-{$notice['type']} {$is_dismissible}'>
            <p>{$notice['message']}</p>
            </div>";
            echo esc_html( $div );
        }
    }

    protected static function notice( $message = '', $type = self::TYPE_SUCCESS, $is_dismissible = true ) {
        static::$notices[] = [
            'message' => $message,
            'type' => $type,
            'is_dismissible' => $is_dismissible,
        ];
    }

    public static function success( $message, $is_dismissible = true ) {
        static::notice( $message, self::TYPE_SUCCESS, $is_dismissible );
    }

    public static function error( $message, $is_dismissible = true ) {
        static::notice( $message, self::TYPE_ERROR, $is_dismissible );
    }

    public static function warning( $message, $is_dismissible = true ) {
        static::notice( $message, self::TYPE_WARNING, $is_dismissible );
    }
}
