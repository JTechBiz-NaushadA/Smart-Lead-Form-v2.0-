<?php
if (!defined('ABSPATH')) exit;

class TX_SMTP {

    public function __construct() {
        add_action('phpmailer_init', [$this, 'configure_smtp']);
    }

    public function configure_smtp($phpmailer) {
		
		// Prevent conflict with WP Mail SMTP plugin
        if (defined('WPMS_ON')) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'tx_settings';
        $settings = $wpdb->get_row(
			"SELECT * FROM $table 
			 WHERE form_key='default' 
			 LIMIT 1"
		);

        // If SMTP not enabled ? fallback to default wp_mail
        if (empty($settings->smtp_enable)) {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host       = $settings->smtp_host;
        $phpmailer->SMTPAuth = (
			!empty($settings->smtp_username)
			&& !empty($settings->smtp_password)
		);
        $phpmailer->Port       = $settings->smtp_port;
        $phpmailer->Username   = $settings->smtp_username;
        $phpmailer->Password   = $settings->smtp_password;
        if (!empty($settings->smtp_encryption)) {
			$phpmailer->SMTPSecure = $settings->smtp_encryption;
		}

        $phpmailer->From       = $settings->sender_email;
        $phpmailer->FromName   = $settings->sender_name;
    }
}