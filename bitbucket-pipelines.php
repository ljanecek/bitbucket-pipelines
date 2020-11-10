<?php
/**
 * Plugin Name:     Bitbucket Pipelines
 * Plugin URI:      https://disruptive.cz
 * Description:     Bitbucket Pipelines Connection
 * Author:          Ladislav Janeček
 * Text Domain:     bitbucket-pipelines
 * Version:         0.1.0
 * Domain Path: /languages/
 *
 * @package         Bitbucket_Pipelines
 */

// Your code starts here.

if (!defined('ABSPATH')) {
    exit;
}

class Bitbucket_Pipelines
{

    private static $instance = null;

    private $options;
    private $networkactive;

    private $bitbucket_auth_url = 'https://bitbucket.org/site/oauth2/access_token';
    private $bitbucket_pipeline_url = 'https://api.bitbucket.org/2.0/repositories/%s/%s/pipelines/';
    private $bitbucket_branches_url = 'https://api.bitbucket.org/2.0/repositories/%s/%s/refs/branches';

    public static function get_instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self;
        }
        return self::$instance;

    }

    public function __construct()
    {
        // are we network activated?
        $this->networkactive = (is_multisite() && array_key_exists(plugin_basename(__FILE__), (array) get_site_option('active_sitewide_plugins')));

        // Load options.
        if ($this->networkactive) {
            $this->options = get_site_option('bitbucket_pipelines_options', array());
        } else {
            $this->options = get_option('bitbucket_pipelines_options', array());
        }

        // If it looks like first run, check compat.
        if (empty($this->options)) {
            $this->check_compatibility();
        }

        $this->init_filters();
    }

    private function check_compatibility()
    {
        if (version_compare($GLOBALS['wp_version'], '4.7', '<')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            deactivate_plugins(__FILE__);
            if (isset($_GET['action']) && ($_GET['action'] == 'activate' || $_GET['action'] == 'error_scrape')) {
                exit(sprintf(__('Bitbucket Pipelines requires WordPress version %s or greater.', 'bitbucket-pipelines'), '4.7'));
            }
        }
    }

    private function init_filters()
    {

        if (is_admin()) {
            if ($this->networkactive) {
                add_action('network_admin_menu', array($this, 'settings_menu'));
            } else {
                add_action('admin_menu', array($this, 'settings_menu'));
            }
        }

        add_action('draft_to_publish', array($this, 'post_published_notification'));
        add_action('future_to_publish', array($this, 'post_published_notification'));
        add_action('private_to_publish', array($this, 'post_published_notification'));
        add_action('post_updated', array($this, 'post_published_notification'));
        add_action('admin_bar_menu', array($this, 'add_admin_bar_control'), 999);

        add_action('admin_notices', array($this, 'process_build_request'));

    }

    public function post_published_notification()
    {
        if ($this->options['publishing']) {
            $this->options['branch'] && $this->trigger_pipeline($this->options['branch']);
        }
    }

    private function get_access_token()
    {

        $auth = base64_encode($this->options['key'] . ':' . $this->options['secret']);

        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Authorization: Basic ' . $auth,
                ],
                'content' => http_build_query([
                    'grant_type' => 'client_credentials',
                ]),
            ],
        ];

        $context = stream_context_create($opts);
        $reponse = file_get_contents($this->bitbucket_auth_url, false, $context);
        $output = json_decode($reponse);

        if (!isset($output->type) && $output->type !== 'error') {
            return $output->access_token;
        }

        return false;
    }

    private function get_branches()
    {

        if (false === ($result = get_transient('bitbucket_pipelines_branches'))) {

            $access_token = $this->get_access_token();

            $opts = [
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $access_token,
                    ],
                ],
            ];

            $url = sprintf($this->bitbucket_branches_url, $this->options['workspace'], $this->options['repo_slug']);

            $context = stream_context_create($opts);
            $reponse = file_get_contents($url, false, $context);
            $output = json_decode($reponse);

            if (isset($output->values) && !empty($output->values)) {

                $result = $output->values;

                set_transient('bitbucket_pipelines_branches', $result, HOUR_IN_SECONDS);

                return $result;
            }
        }

        return $result;

    }

    private function trigger_pipeline($branch)
    {

        $access_token = $this->get_access_token();

        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $access_token,
                ],
                'content' => json_encode([
                    "target" => [
                        "ref_type" => "branch",
                        "type" => "pipeline_ref_target",
                        "ref_name" => $branch,
                    ],
                ]),
            ],
        ];

        $url = sprintf($this->bitbucket_pipeline_url, $this->options['workspace'], $this->options['repo_slug']);

        $context = stream_context_create($opts);
        $reponse = file_get_contents($url, false, $context);
        $output = json_decode($reponse);

        if (isset($output->type) && $output->type !== 'error') {
            return true;
        }

        return false;
    }

    public function settings_page()
    {
        include dirname(__FILE__) . '/includes/settings-page.php';
    }

    private function settings_page_url()
    {
        $base = $this->networkactive ? network_admin_url('settings.php') : admin_url('options-general.php');
        return add_query_arg('page', 'bitbucket_pipelines_settings', $base);
    }

    public function settings_menu()
    {
        $title = __('Bitbucket Pipelines', 'settings menu title', 'bitbucket-pipelines');
        if ($this->networkactive) {
            add_submenu_page('settings.php', $title, $title, 'manage_network_plugins', 'bitbucket_pipelines_settings', array($this, 'settings_page'));
        } else {
            add_submenu_page('options-general.php', $title, $title, 'manage_options', 'bitbucket_pipelines_settings', array($this, 'settings_page'));

            if (is_multisite()) {
                register_deactivation_hook(__FILE__, array($this, 'single_site_deactivate'));
            }
        }
    }

    public function add_admin_bar_control(WP_Admin_Bar $wp_admin_bar)
    {

        $current_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' .
            $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        $goto_url = get_admin_url();

        if (stristr($current_url, get_admin_url())) {
            $goto_url = $current_url;
        }

        $args = array(
            'id' => 'bitbucket-pipelines',
            'title' => '<span class="custom-icon" style="
                    background-image: url(\'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiA/PjwhRE9DVFlQRSBzdmcgIFBVQkxJQyAnLS8vVzNDLy9EVEQgU1ZHIDEuMS8vRU4nICAnaHR0cDovL3d3dy53My5vcmcvR3JhcGhpY3MvU1ZHLzEuMS9EVEQvc3ZnMTEuZHRkJz48c3ZnIGhlaWdodD0iNTEycHgiIHN0eWxlPSJlbmFibGUtYmFja2dyb3VuZDpuZXcgMCAwIDUxMiA1MTI7IiB2ZXJzaW9uPSIxLjEiIHZpZXdCb3g9IjAgMCA1MTIgNTEyIiB3aWR0aD0iNTEycHgiIHhtbDpzcGFjZT0icHJlc2VydmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiPjxnIGlkPSJfeDM0XzQtYml0YnVja2V0Ij48Zz48cGF0aCBkPSJNNDAuOTI5LDQ5LjE3OGMtOC4xMDgtMC4wOTItMTQuODM1LDYuMzU4LTE0LjkyOCwxNC41NTljMCwwLjgyOSwwLjA5MywxLjc1LDAuMTg2LDIuNTggICAgbDYyLjU2NiwzNzkuNzM1YzEuNTY2LDkuNTgyLDkuODYsMTYuNjgsMTkuNjI3LDE2Ljc3aDMwMC4xMThjNy4yODEsMC4wOTMsMTMuNTQ2LTUuMTU5LDE0Ljc0NS0xMi4zNDdMNDg1LjgxLDY2LjQwOSAgICBjMS4yOS04LjAxNi00LjE0Ni0xNS41NzItMTIuMTYzLTE2Ljg2MmMtMC44MjktMC4wOTEtMS42NTgtMC4xODQtMi41NzgtMC4xODRMNDAuOTI5LDQ5LjE3OEw0MC45MjksNDkuMTc4eiBNMzA0LjM3NSwzMjMuNTkgICAgaC05NS44MzFsLTI1Ljg5NS0xMzUuNDU2aDE0NC45NDVMMzA0LjM3NSwzMjMuNTlMMzA0LjM3NSwzMjMuNTl6IiBzdHlsZT0iZmlsbDojMjY4NEZGOyIvPjwvZz48L2c+PGcgaWQ9IkxheWVyXzEiLz48L3N2Zz4=\');
                    float:left;
                    width:22px !important;
                    height:22px !important;
                    margin-left: 5px !important;
                    margin-top: 5px !important;
                    margin-right: 5px !important;
                    background-size: contain;
                    background-repeat: no-repeat;
                "></span>Bitbucket Pipelines',
            'href' => admin_url('admin.php?page=bitbucket_pipelines_settings'),
        );

        $wp_admin_bar->add_menu($args);

        foreach ($this->get_branches() as $branch) {
            $wp_admin_bar->add_menu(array(
                'parent' => 'bitbucket-pipelines',
                'title' => 'Run <strong>' . $branch->name . '</strong> pipeline',
                'href' => wp_nonce_url(add_query_arg('_bitbucket-pipelines__build', $branch->name, $goto_url), '_bitbucket-pipelines__build_nonce'),
            ));
        }
    }

    public function process_build_request()
    {

        // check if clear request
        if (empty($_GET['_bitbucket-pipelines__build'])) {
            return;
        }

        // validate nonce
        if (empty($_GET['_wpnonce']) or !wp_verify_nonce($_GET['_wpnonce'], '_bitbucket-pipelines__build_nonce')) {
            return;
        }

        // check user role
        if (!is_admin_bar_showing()) {
            return;
        }

        // load if network
        if (!function_exists('is_plugin_active_for_network')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $branch = $_GET['_bitbucket-pipelines__build'];

        $this->trigger_pipeline($branch);

    }

    public function single_site_deactivate()
    {
        delete_option('bitbucket_pipelines_options');
    }

    private function update_options()
    {
        if ($this->networkactive) {
            update_site_option('bitbucket_pipelines_options', $this->options);
        } else {
            update_option('bitbucket_pipelines_options', $this->options);
        }
    }

}

Bitbucket_Pipelines::get_instance();
