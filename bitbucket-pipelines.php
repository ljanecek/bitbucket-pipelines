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

        $access_token = $this->get_access_token();

        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => [
                    // 'Content-Type: application/json',
                    'Authorization: Bearer ' . $access_token,
                ],
            ],
        ];

        $url = sprintf($this->bitbucket_branches_url, $this->options['workspace'], $this->options['repo_slug']);

        $context = stream_context_create($opts);
        $reponse = file_get_contents($url, false, $context);
        $output = json_decode($reponse);

        if (isset($output->values) && !empty($output->values)) {
            return $output->values;
        }

        return false;
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
                'content' => http_build_query([
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