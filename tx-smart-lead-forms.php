<?php
/*
Plugin Name: TX Smart Lead Forms
Plugin URI: https://github.com/JTechBiz-NaushadA
Description: Build unlimited lead capture forms with shortcode support, custom fields, SMTP email delivery, HTML email templates, lead management, CSV export, email previews, duplicate lead prevention, and GDPR-compliant unsubscribe/re-subscribe features.
Version: 2.0
Author: Naushad A.
Author URI: https://github.com/JTechBiz-NaushadA
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: tx-smart-lead-forms
*/

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'includes/class-tx-smtp.php';

new TX_SMTP();

/* --------------------------
   1. CREATE TABLES
-------------------------- */
register_activation_hook(__FILE__, function () {
    global $wpdb;

    $charset = $wpdb->get_charset_collate();

    $leads = $wpdb->prefix . 'tx_leads';
    $settings = $wpdb->prefix . 'tx_settings';
	
	$form_configs = $wpdb->prefix . 'tx_form_configs';

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // Leads Table (UPDATED)
    dbDelta("CREATE TABLE $leads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(100),
        last_name VARCHAR(100),
        email VARCHAR(150),
        organisation VARCHAR(150),
        role VARCHAR(100),
        country VARCHAR(100),
        interests TEXT,
		form_key VARCHAR(100),
        unsubscribe TINYINT(1) DEFAULT 0,
        unsubscribe_token VARCHAR(64),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset;");

    // Settings Table
	dbDelta("CREATE TABLE $settings (
		id INT AUTO_INCREMENT PRIMARY KEY,
		form_key VARCHAR(100) UNIQUE,
		sender_name VARCHAR(100),
		sender_email VARCHAR(150),
		subject VARCHAR(255),
		preview_line VARCHAR(255),
		message LONGTEXT,

		smtp_enable TINYINT(1) DEFAULT 0,
		smtp_host VARCHAR(150),
		smtp_port INT,
		smtp_encryption VARCHAR(10),
		smtp_username VARCHAR(150),
		smtp_password VARCHAR(150)

	) $charset;");
	
	dbDelta("CREATE TABLE $form_configs (
		id INT AUTO_INCREMENT PRIMARY KEY,
		form_key VARCHAR(100) UNIQUE,
		form_title VARCHAR(255),
		form_subtitle TEXT,
		button_text VARCHAR(100),
		fields LONGTEXT,
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP
	) $charset;");

});

/* --------------------------
   2. ENQUEUE ASSETS
-------------------------- */
add_action('admin_enqueue_scripts', function ($hook) {

    if ($hook === 'toplevel_page_tx-leads' || $hook === 'tx-leads_page_tx-settings' || $hook === 'tx-leads_page_tx-forms') {

        $css_version = filemtime(plugin_dir_path(__FILE__) . 'assets/admin-style.css');
        $js_version  = filemtime(plugin_dir_path(__FILE__) . 'assets/admin-script.js');

        wp_enqueue_style(
            'tx-admin-style',
            plugin_dir_url(__FILE__) . 'assets/admin-style.css',
            [],
            $css_version
        );

        wp_enqueue_script(
            'tx-admin-script',
            plugin_dir_url(__FILE__) . 'assets/admin-script.js',
            [],
            $js_version,
            true
        );

        wp_localize_script('tx-admin-script', 'txAdmin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('tx_admin_nonce'),
            'siteName'=> get_bloginfo('name')
        ]);
    }
});

add_action('wp_enqueue_scripts', function () {

    $css_version = filemtime(plugin_dir_path(__FILE__) . 'assets/style.css');
    $js_version  = filemtime(plugin_dir_path(__FILE__) . 'assets/script.js');

    wp_enqueue_style(
        'tx-style',
        plugin_dir_url(__FILE__) . 'assets/style.css',
        [],
        $css_version
    );

    wp_enqueue_script(
        'tx-script',
        plugin_dir_url(__FILE__) . 'assets/script.js',
        [],
        $js_version,
        true
    );

    wp_localize_script('tx-script', 'tx_ajax', [
        'url'   => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('tx_nonce_action')
    ]);
});

/* --------------------------
   3. SHORTCODE FORM
-------------------------- */
add_shortcode('tx_form', function ($atts) {
	ob_start();
		$atts = shortcode_atts([
			'form' => ''
		], $atts);
	
	if (empty($atts['form'])) {
		return '<p>No form selected.</p>';
	}

	$form_key = sanitize_text_field($atts['form']);
	
	global $wpdb;

	$form_table = $wpdb->prefix . 'tx_form_configs';

	$config = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM $form_table WHERE form_key=%s LIMIT 1",
			$form_key
		),
		ARRAY_A
	);

	if (!$config) {
		return '<p>Form not found.</p>';
	}

	$config_data = json_decode($config['fields'], true);

	$fields = $config_data['fields'] ?? [];

	$interests = [];

	if (!empty($config_data['interests']) && is_array($config_data['interests'])) {

		$interests = $config_data['interests'];

	} else {

		$interests = !empty($config_data['interests']) && is_array($config_data['interests'])
			? $config_data['interests']
			: [];
	}
?>

<div class="tx-form-wrapper">
<form id="txForm-<?= esc_attr($form_key); ?>" class="tx-form-smart-lead-plugin">

<div class="tx-header">
    <h2><?= esc_html($config['form_title']); ?></h2>
    <p><?= esc_html($config['form_subtitle']); ?></p>
</div>

<div class="tx-row">
    <div class="tx-field">
        <label>First Name *</label>
        <input type="text" name="first_name" placeholder="Jane" required>
    </div>

    <div class="tx-field">
        <label>Last Name *</label>
        <input type="text" name="last_name" placeholder="Smith" required>
    </div>
</div>

<div class="tx-field">
    <label>Work Email *</label>
    <input type="email" name="email" placeholder="jane@company.com" required>
</div>

<?php if (in_array('organisation', $fields)) : ?>
<div class="tx-field">
    <label>Organisation</label>
    <input type="text" name="organisation" placeholder="Your company name">
</div>
<?php endif; ?>

<div class="tx-row">
<?php if (in_array('role', $fields)) : ?>
    <div class="tx-field">
        <label>Your Role</label>
        <input type="text" name="role" placeholder="e.g. Product Manager">
    </div>
<?php endif; ?>
<?php if (in_array('country', $fields)) : ?>
    <div class="tx-field">
        <label>Country</label>
        <input type="text" name="country" placeholder="e.g. England">
    </div>
<?php endif; ?>
</div>

<?php if (in_array('interests', $fields)) : ?>

<div class="tx-field chips-parent">
    <label>Areas of Interest</label>

    <div class="chips">

        <?php foreach ($interests as $interest) : ?>

            <span class="chip"
                data-value="<?= esc_attr($interest); ?>">
                <?= esc_html($interest); ?>
            </span>

        <?php endforeach; ?>

    </div>

    <input type="hidden" name="interests" id="interests">
</div>

<?php endif; ?>

<input type="hidden" name="form_key" value="<?= esc_attr($form_key); ?>">

<!-- Privacy -->
<div class="tx-checkbox">
    <input type="checkbox" id="tx-agree" required>
    <label for="tx-agree">
        I agree to receive the whitepaper and relevant VoP content from TechnoXander. 
        You can unsubscribe at any time. 
        <a href="<?= esc_url('https://technoxander.com/privacy-policy/'); ?>" target="_blank">View our privacy policy</a>.
    </label>
</div>

<button type="submit" class="tx-btn">
    <?= esc_html($config['button_text']); ?>
</button>

<div class="tx-message"></div>

</form>
</div>

<?php return ob_get_clean();
});

/* --------------------------
   4. AJAX SUBMIT
-------------------------- */
add_action('wp_ajax_tx_submit', 'tx_submit');
add_action('wp_ajax_nopriv_tx_submit', 'tx_submit');

function tx_submit() {

    check_ajax_referer('tx_nonce_action','nonce');

    global $wpdb;
    $leads = $wpdb->prefix . 'tx_leads';
    $settings_table = $wpdb->prefix . 'tx_settings';

    $email = sanitize_email($_POST['email']);

    // Check if already unsubscribed
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $leads WHERE email=%s ORDER BY id DESC LIMIT 1",
        $email
    ));

    if ($existing && $existing->unsubscribe == 1) {

		// Generate re-subscribe URL
		$resubscribe_link = add_query_arg([
			'tx_resubscribe' => $existing->unsubscribe_token
		], site_url());

		$message = wp_kses_post(
        'You are unsubscribed — but don\'t worry, you can get your checklist by 
        <a href="' . $resubscribe_link . '">click here</a>.' );

		wp_send_json([
			'success' => true,
			'data' => $message
		]);
	}

    // Generate secure token
    $token = wp_generate_password(32, false);
	
	$interests = [];

	if (!empty($_POST['interests'])) {
		$decoded = json_decode(stripslashes($_POST['interests']), true);

		if (is_array($decoded)) {
			$interests = array_values(array_filter(array_map('sanitize_text_field', $decoded)));
		}
	}

	$data = [
		'form_key' => isset($_POST['form_key']) ? sanitize_text_field(wp_unslash($_POST['form_key'])) : '',
		'first_name' => isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '',
		'last_name' => isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '',
		'email' => $email,
		'organisation' => isset($_POST['organisation']) ? sanitize_text_field(wp_unslash($_POST['organisation'])) : '',
		'role' => isset($_POST['role']) ? sanitize_text_field(wp_unslash($_POST['role'])) : '',
		'country' => isset($_POST['country']) ? sanitize_text_field(wp_unslash($_POST['country'])) : '',
		'interests' => wp_json_encode($interests),
		'unsubscribe_token' => $token,
		'unsubscribe' => 0
	];
	
	$duplicate = $wpdb->get_var(
	$wpdb->prepare(
			"SELECT id
			FROM $leads
			WHERE email=%s
			AND form_key=%s
			LIMIT 1",
			$email,
			$data['form_key']
		)
	);

	if ($duplicate) {
		wp_send_json_success(
			"We have already received your request. Please check your email."
		);
	}
	
    $inserted = $wpdb->insert($leads, $data);

	if (!$inserted) {
		wp_send_json_error('Failed to save lead.');
	}

	$form_key = isset($_POST['form_key']) ? sanitize_text_field(wp_unslash($_POST['form_key'])) : '';

	$form_exists = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) 
			FROM {$wpdb->prefix}tx_form_configs 
			WHERE form_key=%s",
			$form_key
		)
	);

	if (!$form_exists) {
		wp_send_json_error('Invalid form.');
	}

	$settings = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM $settings_table WHERE form_key=%s LIMIT 1",
			$form_key
		)
	);
	
	if (!$settings) {
		wp_send_json_error('Form email settings not found.');
	}

    // Localhost skip
	if (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] === 'localhost') {
        wp_send_json_success("Saved (localhost mode)");
    }

    // Email headers
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: '.$settings->sender_name.' <'.$settings->sender_email.'>',
		'Reply-To: '.$settings->sender_email
    ];

    // Secure unsubscribe link
    $unsubscribe_link = add_query_arg('tx_unsub', $token, site_url());

    // Dynamic variables
    $variables = [
        '{{name}}' => $data['first_name'] . ' ' . $data['last_name'],
        '{{email}}' => $data['email'],
        '{{unsubscribe}}' => $unsubscribe_link
    ];

    $message = str_replace(
        array_keys($variables),
        array_values($variables),
        wp_unslash($settings->message)
    );

    // Send email
    wp_mail($data['email'], $settings->subject, $message, $headers);

    wp_send_json_success("We have sent you an email with downloadable link, please check it!");
}

/* --------------------------
   5. ADMIN MENU
-------------------------- */
add_action('admin_menu', function () {

    add_menu_page(
        'TX Leads',
        'TX Leads',
        'manage_options',
        'tx-leads',
        'tx_leads_page'
    );

    add_submenu_page(
        'tx-leads',
        'Forms',
        'Forms',
        'manage_options',
        'tx-forms',
        'tx_forms_page'
    );

    add_submenu_page(
        'tx-leads',
        'Settings',
        'Settings',
        'manage_options',
        'tx-settings',
        'tx_settings_page'
    );

});

/* --------------------------
   6. LEADS PAGE
-------------------------- */
function tx_leads_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'tx_leads';

    $form_filter = isset($_GET['form_key'])
		? sanitize_text_field(wp_unslash($_GET['form_key']))
		: '';

	if ($form_filter) {

		$data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE form_key=%s ORDER BY id DESC",
				$form_filter
			)
		);

	} else {

		$data = $wpdb->get_results(
			"SELECT * FROM $table ORDER BY id DESC"
		);

	}

    echo '<div class="wrap">';
    echo '<h1>Leads</h1>';
	$form_table = $wpdb->prefix . 'tx_form_configs';

	$forms = $wpdb->get_results(
		"SELECT form_key FROM $form_table ORDER BY id ASC"
	);

	echo '<p>';

	echo '<a class="button"
		href="?page=tx-leads">
		All
	</a> ';

	foreach ($forms as $f) {

		echo '<a class="button"
			href="?page=tx-leads&form_key=' .
			esc_attr($f->form_key) .
			'">
			' . esc_html(ucfirst($f->form_key)) . '
		</a> ';
	}

	echo '</p>';
	$export_url = wp_nonce_url('?page=tx-leads&export=1','tx_export_csv');

	if ($form_filter) {
		$export_url .= '&form_key=' . urlencode($form_filter);
	}

	echo '<a href="' . esc_url($export_url) . '" 
	class="button button-primary" 
	id="tx-export-btn" ' 
	. (empty($data) ? 'disabled style="opacity:0.5;pointer-events:none;"' : '') . 
	'>Export CSV</a><br><br>';
	
	echo '<table class="widefat fixed striped">';
    echo '<thead>
        <tr>
            <th>Full Name</th>
            <th>Email</th>
            <th>Organisation</th>
            <th>Role</th>
            <th>Country</th>
            <th>Interest</th>
			<th>Form</th>
            <th>Date</th>
            <th>Unsubscribed</th>
            <th>Action</th>
        </tr>
    </thead>';

    echo '<tbody>';

    foreach ($data as $d) {
        $full_name = esc_html($d->first_name . ' ' . $d->last_name);
        $unsub = $d->unsubscribe ? 'Yes' : 'No';
		
		$interests = json_decode($d->interests, true);
		if (!is_array($interests)) {
				$interests = [];
			}

        echo "<tr>
            <td>{$full_name}</td>
            <td>" . esc_html($d->email) . "</td>
            <td>" . esc_html($d->organisation) . "</td>
            <td>" . esc_html($d->role) . "</td>
            <td>" . esc_html($d->country) . "</td>
            <td>" . esc_html(implode(', ', $interests)) . "</td>
			<td>" . esc_html($d->form_key) . "</td>
            <td>" . esc_html($d->created_at) . "</td>
            <td>{$unsub}</td>
            <td>
                <button class='button button-small tx-delete-lead' data-id='{$d->id}'>Delete</button>
            </td>
        </tr>";
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}

add_action('wp_ajax_tx_delete_lead', function() {
	
    check_ajax_referer('tx_admin_nonce', 'nonce');
	
	if (!current_user_can('manage_options')) {
		wp_send_json_error('Unauthorized');
	}

    if (!isset($_POST['id'])) {
        wp_send_json_error('Invalid ID');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'tx_leads';
    $id = intval($_POST['id']);

    $deleted = $wpdb->delete($table, ['id' => $id]);

    if ($deleted) {
        wp_send_json_success('Lead deleted successfully');
    } else {
        wp_send_json_error('Failed to delete lead');
    }
});

/* --------------------------
   7. CSV EXPORT
-------------------------- */
add_action('init', function () {

	if (
		!isset($_GET['export']) ||
		!current_user_can('manage_options') ||
		!isset($_GET['_wpnonce']) ||
		!wp_verify_nonce(
			sanitize_text_field(wp_unslash($_GET['_wpnonce'])),
			'tx_export_csv'
		)
	) {
		return;
	}

    global $wpdb;
    $table = $wpdb->prefix . 'tx_leads';

    $form_filter = isset($_GET['form_key'])
		? sanitize_text_field(wp_unslash($_GET['form_key']))
		: '';

	if ($form_filter) {

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE form_key=%s",
				$form_filter
			),
			ARRAY_A
		);

	} else {

		$rows = $wpdb->get_results(
			"SELECT * FROM $table",
			ARRAY_A
		);

	}

    header('Content-Type:text/csv');
	
	$filename = $form_filter
		? 'tx-leads-' . sanitize_file_name($form_filter) . '-' . current_time('Y-m-d-H-i-s') . '.csv'
		: 'tx-leads-all-' . current_time('Y-m-d-H-i-s') . '.csv';

	header( 'Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output','w');

    if ($rows) {
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $r) {
			$r['interests'] = implode(', ', json_decode($r['interests'], true) ?: []);

			fputcsv($out, $r);
		}
    }

    fclose($out);
    exit;
});

/* --------------------------
   8. SETTINGS PAGE
-------------------------- */
function tx_settings_page() {

    global $wpdb;
    $table = $wpdb->prefix . 'tx_settings';
	$form_key = 'global_smtp';
	
	if (!current_user_can('manage_options')) {
		wp_die('Unauthorized');
	}

	if (isset($_POST['save'])) {

		if (
			!isset($_POST['tx_save_smtp_nonce']) ||
			!wp_verify_nonce(
				sanitize_text_field(wp_unslash($_POST['tx_save_smtp_nonce'])),
				'tx_save_smtp'
			)
		) {
			wp_die('Security check failed');
		}

		$data = [
			'form_key' => 'global_smtp',

			'smtp_enable'   => isset($_POST['smtp_enable']) ? 1 : 0,
			'smtp_host'     => sanitize_text_field(wp_unslash($_POST['smtp_host'])),
			'smtp_port'     => intval(wp_unslash($_POST['smtp_port'])),
			'smtp_encryption' => isset($_POST['smtp_encryption']) ? sanitize_text_field(wp_unslash($_POST['smtp_encryption'])) : '',
			'smtp_username' => sanitize_text_field(wp_unslash($_POST['smtp_username']))
		];

		// Only update password if user entered new one
		if (!empty($_POST['smtp_password'])) {
			$data['smtp_password'] = sanitize_text_field(wp_unslash($_POST['smtp_password']));
		}

		// Proper update (no need to pass id inside data)
		$updated = $wpdb->update($table, $data, ['form_key' => 'global_smtp']);

		// If no row updated, insert it
		if ($updated === false || $updated === 0) {
			$data['form_key'] = $form_key;
			$wpdb->insert($table, $data);
		}
	}

    $s = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM $table WHERE form_key = %s LIMIT 1",
			$form_key
		)
	);
	if (!$s) {

		$wpdb->insert($table, [
			'form_key' => $form_key
		]);

		$s = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE form_key = %s LIMIT 1",
				$form_key
			)
		);
	}

    ?>

<div class="wrap tx-settings-wrap">

    <h1>SMTP Settings</h1>
    <p class="tx-settings-desc">
		Configure global SMTP settings for sending emails from all forms.
	</p>

    <form method="post" class="tx-settings-form">
		<?php wp_nonce_field('tx_save_smtp', 'tx_save_smtp_nonce'); ?>
		<input type="hidden" name="form_key" value="<?= esc_attr($form_key); ?>">
	
		<h2>SMTP Settings</h2>

		<div class="tx-field">
			<label>
				<input type="checkbox" name="smtp_enable" value="1" <?= checked($s->smtp_enable, 1, false); ?>>
				Enable SMTP
			</label>
		</div>

		<div class="tx-field">
			<label>SMTP Host</label>
			<input type="text" name="smtp_host" value="<?= esc_attr($s->smtp_host) ?>" placeholder="smtp.hostinger.com">
		</div>

		<div class="tx-field">
			<label>SMTP Port</label>
			<input type="number" name="smtp_port" value="<?= esc_attr($s->smtp_port) ?>" placeholder="587">
		</div>

		<div class="tx-field">
			<label>Encryption</label>
			<select name="smtp_encryption">
				<option value="tls" <?= selected($s->smtp_encryption, 'tls', false); ?>>TLS</option>
				<option value="ssl" <?= selected($s->smtp_encryption, 'ssl', false); ?>>SSL</option>
			</select>
		</div>

		<div class="tx-field">
			<label>SMTP Username</label>
			<input type="text" name="smtp_username" value="<?= esc_attr($s->smtp_username) ?>">
		</div>

		<div class="tx-field">
			<label>SMTP Password</label>
			<input type="password" name="smtp_password" value="<?= esc_attr($s->smtp_password) ?>" placeholder="Leave blank to keep existing">
		</div>

		<br>

        <div class="tx-actions">
            <button name="save" class="button button-primary">Save SMTP Settings</button>
        </div>

    </form>
</div>
<!-- Footer -->
<div style="margin-top: 30px; padding: 15px; text-align: center; border-top: 1px solid #ddd;">
    <p style="margin: 0; font-size: 13px; color: #666;">
        Powered by <a href="mailto:naushadali.rj@gmail.com" style="text-decoration:none; color:#0073aa;">
            Naushad A.
        </a>
    </p>
</div>

    <?php
}
/* --------------------------
   8. TX_Forms
-------------------------- */

function tx_forms_page() {
	
	if (!current_user_can('manage_options')) {
		wp_die('Unauthorized');
	}

    global $wpdb;

    $forms_table = $wpdb->prefix . 'tx_form_configs';
    $settings_table = $wpdb->prefix . 'tx_settings';

    /*
    -------------------------
    ADD NEW FORM
    -------------------------
    */
	if (isset($_POST['add_new_form'])) {

		if (
			!isset($_POST['tx_add_form_nonce']) ||
			!wp_verify_nonce(
				sanitize_text_field(
					wp_unslash($_POST['tx_add_form_nonce'])
				),
				'tx_add_form'
			)
		) {
			wp_die('Security check failed');
		}

		$new_key = sanitize_title(wp_unslash($_POST['new_form_key']));

        if ($new_key) {

            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $forms_table WHERE form_key=%s",
                    $new_key
                )
            );

            if (!$exists) {

                $wpdb->insert($forms_table, [
                    'form_key' => $new_key,
                    'form_title' => 'New Form',
                    'form_subtitle' => '',
                    'button_text' => 'Submit',
                    'fields' => wp_json_encode([
						'fields' => [
							'organisation',
							'role',
							'country'
						],
						'interests' => []
					])
                ]);

                $wpdb->insert($settings_table, [
                    'form_key' => $new_key
                ]);
            }
        }
    }

    /*
    -------------------------
    DELETE FORM
    -------------------------
    */
	if (isset($_GET['delete_form'])) {

		$delete_key = sanitize_text_field(wp_unslash($_GET['delete_form']));

		if (
			!isset($_GET['_wpnonce']) ||
			!wp_verify_nonce(
				sanitize_text_field(wp_unslash($_GET['_wpnonce'])),
				'tx_delete_form_' . $delete_key
			)
		) {
			wp_die('Security check failed');
		}

		$wpdb->delete($forms_table, [
			'form_key' => $delete_key
		]);

		$wpdb->delete($settings_table, [
			'form_key' => $delete_key
		]);
	}

    /*
    -------------------------
    CURRENT FORM
    -------------------------
    */
	$forms = $wpdb->get_results(
		"SELECT * FROM $forms_table ORDER BY id ASC"
	);

    $current_form = isset($_GET['form'])
		? sanitize_text_field(wp_unslash($_GET['form']))
		: '';

	if (empty($current_form) && !empty($forms)) {
		$current_form = $forms[0]->form_key;
	}
	
    /*
    -------------------------
    SAVE FORM
    -------------------------
    */
	if (isset($_POST['save_form'])) {

		if (
			!isset($_POST['tx_save_form_nonce']) ||
			!wp_verify_nonce(
				sanitize_text_field(wp_unslash($_POST['tx_save_form_nonce'])),
				'tx_save_form'
			)
		) {
			wp_die('Security check failed');
		}

        $fields = [];

        if (!empty($_POST['fields'])) {
            $fields = array_map(
                'sanitize_text_field',
                $_POST['fields']
            );
        }
		
		$interests = [];

		if (!empty($_POST['interests'])) {

			$interests = array_filter(
				array_map(
					'sanitize_text_field',
					array_map(
						'trim',
						explode("\n", wp_unslash($_POST['interests']))
					)
				)
			);
		}

        $wpdb->update(
            $forms_table,
            [
                'form_title' => sanitize_text_field(wp_unslash($_POST['form_title'])),
                'form_subtitle' => wp_kses_post(wp_unslash($_POST['form_subtitle'])),
                'button_text' => sanitize_text_field(wp_unslash($_POST['button_text'])),
                'fields' => wp_json_encode([
					'fields' => $fields,
					'interests' => $interests
				])
            ],
            [
                'form_key' => $current_form
            ]
        );

		$settings_data = [
			'sender_name'   => isset($_POST['sender_name']) ? sanitize_text_field(wp_unslash($_POST['sender_name'])) : '',
			'sender_email'  => isset($_POST['sender_email']) ? sanitize_email(wp_unslash($_POST['sender_email'])) : '',
			'subject'       => isset($_POST['subject']) ? sanitize_text_field(wp_unslash($_POST['subject'])) : '',
			'preview_line'  => isset($_POST['preview_line']) ? sanitize_text_field(wp_unslash($_POST['preview_line'])) : '',
			'message'       => isset($_POST['message']) ? wp_unslash($_POST['message']) : '',
		];

		$settings_data['form_key'] = $current_form;

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $settings_table WHERE form_key=%s",
				$current_form
			)
		);

		if ($exists) {

			$result = $wpdb->update(
				$settings_table,
				$settings_data,
				['form_key' => $current_form]
			);

		} else {

			$result = $wpdb->insert(
				$settings_table,
				$settings_data
			);
			
		}
    }

    /*
    -------------------------
    GET FORMS
    -------------------------
    */
    $form = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $forms_table WHERE form_key=%s",
            $current_form
        )
    );
	
	$settings = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM $settings_table WHERE form_key=%s",
			$current_form
		)
	);

	if (!$settings) {

		$settings = (object) [
			'sender_name' => '',
			'sender_email' => '',
			'subject' => '',
			'preview_line' => '',
			'message' => ''
		];
	}

    $selected_fields = [];

	if (!empty($form->fields)) {
		$decoded = json_decode($form->fields, true);
		if (is_array($decoded)) {
			$selected_fields = isset($decoded['fields']) && is_array($decoded['fields'])
				? $decoded['fields']
				: [];
		}
	}
	
	$interests_selected = [];

	if (!empty($form->fields)) {
		$decoded = json_decode($form->fields, true);

		if (is_array($decoded) && !empty($decoded['interests'])) {
			$interests_selected = $decoded['interests'];
		}
	}

    echo '<div class="wrap">';
    echo '<h1>Forms</h1>';

    /*
    -------------------------
    FORM TABS
    -------------------------
    */

    echo '<div style="margin:20px 0;">';

    foreach ($forms as $f) {

        echo '<a class="button ' .
            ($current_form == $f->form_key
                ? 'button-primary'
                : '') .
            '" href="?page=tx-forms&form=' .
            esc_attr($f->form_key) .
            '">' .
            esc_html(ucfirst($f->form_key)) .
            '</a> ';
    }

    echo '</div>';

    /*
    -------------------------
    ADD NEW FORM
    -------------------------
    */

	echo '<form method="post" style="margin-bottom:25px;">';

	wp_nonce_field(
		'tx_add_form',
		'tx_add_form_nonce'
	);

    echo '<input type="text"
        name="new_form_key"
        placeholder="Enter form name"
        required>';

    echo ' <button class="button button-primary"
        name="add_new_form">
        Add New Form +
    </button>';

    echo '</form>';

    /*
    -------------------------
    FORM EDIT
    -------------------------
    */

    if ($form) {

		echo '<form method="post">';

		wp_nonce_field(
			'tx_save_form',
			'tx_save_form_nonce'
		);

        echo '<table class="form-table">';

        echo '<tr>
            <th>Form Name</th>
            <td>
                <input type="text"
                    value="' . esc_attr($form->form_key) . '"
                    disabled>
            </td>
        </tr>';

        echo '<tr>
            <th>Form Title</th>
            <td>
                <input type="text"
                    name="form_title"
                    value="' . esc_attr($form->form_title) . '"
                    class="regular-text">
            </td>
        </tr>';

        echo '<tr>
            <th>Form Subtitle</th>
            <td>
                <textarea
                    name="form_subtitle"
                    rows="3"
                    class="large-text">'
                    . esc_textarea($form->form_subtitle) .
                '</textarea>
            </td>
        </tr>';

        echo '<tr>
            <th>Button Text</th>
            <td>
                <input type="text"
                    name="button_text"
                    value="' . esc_attr($form->button_text) . '">
            </td>
        </tr>';

        echo '<tr>
            <th>Fields</th>
            <td>';

        $all_fields = [
            'organisation',
            'role',
            'country',
            'interests'
        ];

        foreach ($all_fields as $field) {

            echo '<label style="display:block;margin-bottom:8px;">
                <input type="checkbox"
                    name="fields[]"
                    value="' . esc_attr($field) . '" ' .
                    checked(
                        in_array($field, $selected_fields),
                        true,
                        false
                    ) . '>
                ' . ucfirst($field) . '
            </label>';
        }

        echo '</td></tr>';
		
		echo '<tr>
			<th>Interest Options</th>
			<td>
				<textarea
					name="interests"
					rows="6"
					class="large-text"
					placeholder="One option per line">'
					. esc_textarea(implode("\n", $interests_selected)) .
				'</textarea>

				<p class="description">
					Enter one interest per line.
				</p>
			</td>
		</tr>';

        /*
        -------------------------
        EMAIL SETTINGS
        -------------------------
        */

        echo '<tr>
            <th>Sender Name</th>
            <td>
                <input type="text"
                    name="sender_name"
                    value="' . esc_attr($settings->sender_name) . '"
                    class="regular-text">
            </td>
        </tr>';

        echo '<tr>
            <th>Sender Email</th>
            <td>
                <input type="email"
                    name="sender_email"
                    value="' . esc_attr($settings->sender_email) . '"
                    class="regular-text">
            </td>
        </tr>';

        echo '<tr>
            <th>Email Subject</th>
            <td>
                <input type="text"
                    name="subject"
                    value="' . esc_attr($settings->subject) . '"
                    class="large-text">
            </td>
        </tr>';

        echo '<tr>
            <th>Preview Line</th>
            <td>
                <input type="text"
                    name="preview_line"
                    value="' . esc_attr($settings->preview_line) . '"
                    class="large-text">
            </td>
        </tr>';

        echo '<tr>
            <th>
				<label>Email Message</label><br/>
				<small>(HTML Supported), Please, use <strong>{{unsubscribe}}</strong> in your email template with html anchor tag for GDPR Compliant.</small>
            </th>
            <td>
                <textarea
                    name="message"
                    rows="10"
                    class="large-text">'
                    . esc_textarea($settings->message) .
                '</textarea>
            </td>
        </tr>';

        echo '</table>';

        echo '<p>';

        echo '<button class="button button-primary"
            name="save_form">
            Save Form
        </button>';

		echo ' <a class="button button-secondary"
			onclick="return confirm(\'Delete this form?\')"
			href="' .
				esc_url(
					wp_nonce_url(
						admin_url(
							'admin.php?page=tx-forms&delete_form=' .
							rawurlencode($current_form)
						),
						'tx_delete_form_' . $current_form
					)
				) .
			'">
			Delete Form
		</a>';

        echo '</p>';

        /*
        -------------------------
        TEST EMAIL
        -------------------------
        */

        echo '<hr>';

        echo '<h2>Send Test Email</h2>';

        echo '<input type="email"
            id="tx-test-email"
            placeholder="Enter test email">';

        echo '<input type="hidden"
            id="tx-current-form"
            value="' . esc_attr($current_form) . '">';

        echo ' <button type="button"
            class="button"
            id="tx-send-test">
            Send Test
        </button>';

        echo '<div id="tx-test-msg"></div>';

        /*
        -------------------------
        PREVIEW
        -------------------------
        */

        echo '<hr>';

        echo '<h2 style="margin-bottom:0px!important;">Email Preview</h2>';
		echo '<p style="margin-top:0px!important; margin-bottom:10px!important;"><small>First you need to save the template with above save button, after that you can get a preview by click link below.</small></p>';
		echo '<a class="button"
            target="_blank"
            href="' .
				esc_url(
					admin_url(
						'?tx_preview=1&form=' .
						rawurlencode($current_form) .
						'&_wpnonce=' .
						wp_create_nonce('tx_preview_nonce')
					)
				) .
			'">
            Preview Template
        </a>';

        echo '<hr>';

        echo '<p>
            Shortcode:
            <code>[tx_form form="' .
            esc_html($current_form) .
            '"]</code>
        </p>';

        echo '</form>';
    }

    echo '</div>';
}

/* --------------------------
   9. UNSUBSCRIBE LINK
-------------------------- */

add_action('init', function () {

    if (!isset($_GET['tx_unsub'])) return;

    global $wpdb;
    $table = $wpdb->prefix . 'tx_leads';

    $token = sanitize_text_field(wp_unslash($_GET['tx_unsub']));

    // Find user by token
    $user = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table WHERE unsubscribe_token = %s", $token)
    );

    $status = 'invalid';

    if ($user) {

        if ($user->unsubscribe == 1) {
            $status = 'already';
        } else {
            $wpdb->update($table, ['unsubscribe' => 1], ['id' => $user->id]);
            $status = 'success';
        }
    }

    // Show custom UI instead of wp_die
    echo '<!DOCTYPE html>
		<html ' . get_language_attributes() . '>
		<head>
			<meta charset="' . esc_attr(get_bloginfo("charset")) . '">
			<title>
				Unsubscribe Successful | ' . esc_html(get_bloginfo("name")) . '
			</title>
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<meta name="robots" content="noindex, nofollow">
			<meta http-equiv="X-UA-Compatible" content="IE=edge">
			<meta name="referrer" content="strict-origin-when-cross-origin">
        <style>
            body {
                font-family: Arial, sans-serif;
                background: #f4f6f9;
                display: flex;
                align-items: center;
                justify-content: center;
                height: 100vh;
                margin: 0;
            }
            .box {
                background: #fff;
                padding: 30px;
                border-radius: 12px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                text-align: center;
                max-width: 400px;
            }
            .box h2 {
                margin-bottom: 10px;
                color: #1c3d7a;
            }
            .box p {
                color: #555;
                font-size: 14px;
            }
            .success { color: #2e7d32; }
            .error { color: #c62828; }
        </style>
    </head>
    <body>
        <div class="box">';

    if ($status === 'success') {
        echo '<h2 class="success">You have been unsubscribed</h2>
              <p>You will no longer receive emails from us.</p>';
    } elseif ($status === 'already') {
        echo '<h2>Already Unsubscribed</h2>
              <p>You are already removed from our mailing list.</p>';
    } else {
        echo '<h2 class="error">Invalid Link</h2>
              <p>This unsubscribe link is invalid or expired.</p>';
    }

    echo '</div>
    </body>
    </html>';

    exit;
});

/* --------------------------
   9A. RE-SUBSCRIBE HANDLER
-------------------------- */

add_action('init', function () {

    if (!isset($_GET['tx_resubscribe'])) return;

    global $wpdb;

    $table = $wpdb->prefix . 'tx_leads';
    $settings_table = $wpdb->prefix . 'tx_settings';

    $token = sanitize_text_field(wp_unslash($_GET['tx_resubscribe']));

    // Find lead by token
    $user = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table WHERE unsubscribe_token = %s",
            $token
        )
    );

    $status = 'invalid';

    if ($user) {

        // Re-subscribe user
		$new_token = wp_generate_password(32, false);
		$wpdb->update(
			$table,
			[
				'unsubscribe' => 0,
				'unsubscribe_token' => $new_token
			],
			['id' => $user->id]
		);

        $settings = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $settings_table WHERE form_key=%s LIMIT 1",
				$user->form_key
			)
		);

        // Email headers
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $settings->sender_name . ' <' . $settings->sender_email . '>',
            'Reply-To: ' . $settings->sender_email
        ];

        // New unsubscribe link
        $unsubscribe_link = add_query_arg(
            'tx_unsub',
            $new_token,
            site_url()
        );

        // Dynamic variables
        $variables = [
            '{{name}}' => $user->first_name . ' ' . $user->last_name,
            '{{email}}' => $user->email,
            '{{unsubscribe}}' => $unsubscribe_link
        ];

        $message = str_replace(
            array_keys($variables),
            array_values($variables),
            wp_unslash($settings->message)
        );

        // Send email again
        wp_mail(
            $user->email,
            $settings->subject,
            $message,
            $headers
        );

        $status = 'success';
    }

    ?>
    <!DOCTYPE html>
	<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo('charset'); ?>">
		<title>
			Successfully Re-Subscribed | <?php echo esc_html(get_bloginfo('name')); ?>
		</title>
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="robots" content="noindex, nofollow">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="referrer" content="strict-origin-when-cross-origin">
        <style>
            body{
                font-family:Arial,sans-serif;
                background:#f4f6f9;
                display:flex;
                align-items:center;
                justify-content:center;
                height:100vh;
                margin:0;
            }

            .box{
                background:#fff;
                padding:30px;
                border-radius:12px;
                box-shadow:0 10px 30px rgba(0,0,0,0.1);
                text-align:center;
                max-width:420px;
            }

            .success{
                color:#2e7d32;
            }

            .error{
                color:#c62828;
            }
        </style>
    </head>
    <body>

        <div class="box">

            <?php if ($status === 'success') : ?>

                <h2 class="success">
                    You are subscribed again!
                </h2>

                <p style="line-height: 25px";>
                    Your checklist has been sent successfully to your email, along with a downloadable link. Kindly check your inbox.
                </p>

            <?php else : ?>

                <h2 class="error">
                    Invalid Link
                </h2>

                <p>
                    This re-subscribe link is invalid or expired.
                </p>

            <?php endif; ?>

        </div>
		<script>
			setTimeout(function () {

				// Go back to previous page
				if (document.referrer) {
					window.location.href = document.referrer;
				} else {
					// Fallback homepage
					window.location.href = "<?php echo site_url(); ?>";
				}

			}, 11000);
		</script>
    </body>
    </html>
    <?php

    exit;
});

/* --------------------------
   10. Test Email Handler
-------------------------- */

add_action('wp_ajax_tx_send_test', function () {

    // Security check
    check_ajax_referer('tx_admin_nonce', 'nonce');
	
	if (!current_user_can('manage_options')) {
		wp_send_json_error('Unauthorized');
	}

    // Validate input exists
    if (empty($_POST['email'])) {
        wp_send_json_error("Please enter an email address.");
    }

    $email = sanitize_email(wp_unslash($_POST['email']));

    // Validate email format
    if (!is_email($email)) {
        wp_send_json_error("Please enter a valid email address.");
    }

    global $wpdb;
	$form_key = isset($_POST['form_key'])
		? sanitize_text_field(wp_unslash($_POST['form_key']))
		: '';

	$settings = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}tx_settings WHERE form_key=%s LIMIT 1",
			$form_key
		)
	);

    if (!$settings) {
        wp_send_json_error("Settings not found.");
    }

    // Prepare message safely
    $message = str_replace(
        '{{name}}',
        'Test User',
        wp_unslash($settings->message)
    );

    $headers = [
        'Content-Type: text/html; charset=UTF-8'
    ];

    // Send email and check result
    $sent = wp_mail($email, $settings->subject, $message, $headers);

    if (!$sent) {
		wp_send_json_error("Failed to send email. Check SMTP settings OR email address if it exists or not.");        
    } else {
		wp_send_json_success("Test email sent successfully!");
    }
});

/* --------------------------
   11. Preview Handler
-------------------------- */
add_action('init', function () {

    if (!isset($_GET['tx_preview'])) return;

    // Admin check
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die('Access denied');
    }

    // Nonce check
	if (
		!isset($_GET['_wpnonce']) ||
		!wp_verify_nonce(
			sanitize_text_field(wp_unslash($_GET['_wpnonce'])),
			'tx_preview_nonce'
		)
	) {
        wp_die('Invalid request');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'tx_settings';

	$form_key = isset($_GET['form'])
		? sanitize_text_field(wp_unslash($_GET['form']))
		: '';
		
	if (empty($form_key)) {
		wp_die('Form not specified.');
	}

	$s = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT message FROM $table WHERE form_key=%s LIMIT 1",
			$form_key
		)
	);

    if (!$s || empty($s->message)) {
        wp_die('No email template found.');
    }

	$dummy_token = 'preview_j54fd6fe6ewe12d6ere32ff13df2e6efdff3f2d';

	$unsubscribe_link = add_query_arg('tx_unsub', $dummy_token, site_url());

	$variables = [
		'{{unsubscribe}}' => $unsubscribe_link
	];

	$message = str_replace(
		array_keys($variables),
		array_values($variables),
		wp_unslash($s->message)
	);

	echo wp_kses_post($message);

    exit;
});