<?php
/**
 * GitHub-based plugin update checker for DashD.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('DashD_Github_Updater')) {
    class DashD_Github_Updater {
        /** @var string */
        private $plugin_file;
        /** @var string */
        private $plugin_basename;
        /** @var string */
        private $plugin_slug;
        /** @var string */
        private $current_version;
        /** @var string */
        private $repo;
        /** @var string */
        private $branch;
        /** @var string */
        private $token;

        /**
         * @param string $plugin_file
         * @param string $current_version
         * @param string $repo
         * @param string $branch
         * @param string $token
         */
        public function __construct($plugin_file, $current_version, $repo, $branch = 'main', $token = '') {
            $this->plugin_file = (string) $plugin_file;
            $this->plugin_basename = plugin_basename($this->plugin_file);
            $this->plugin_slug = dirname($this->plugin_basename);
            if ($this->plugin_slug === '.' || $this->plugin_slug === '') {
                $this->plugin_slug = basename((string) $this->plugin_basename, '.php');
            }

            $this->current_version = (string) $current_version;
            $this->repo = trim((string) $repo);
            $this->branch = trim((string) $branch) !== '' ? trim((string) $branch) : 'main';
            $this->token = trim((string) $token);
        }

        /**
         * Register hooks.
         */
        public function hooks() {
            add_filter('pre_set_site_transient_update_plugins', [$this, 'filter_update_transient']);
            add_filter('plugins_api', [$this, 'filter_plugins_api'], 20, 3);
            add_filter('http_request_args', [$this, 'filter_http_request_args'], 10, 2);
            add_action('upgrader_process_complete', [$this, 'action_upgrader_process_complete'], 10, 2);
        }

        /**
         * @param mixed $transient
         * @return mixed
         */
        public function filter_update_transient($transient) {
            if (!is_object($transient) || empty($transient->checked) || !is_array($transient->checked)) {
                return $transient;
            }

            $release = $this->get_release_data();
            if (is_wp_error($release) || empty($release['version'])) {
                return $transient;
            }

            $new_version = (string) $release['version'];
            if (version_compare($new_version, $this->current_version, '<=')) {
                if (isset($transient->response[$this->plugin_basename])) {
                    unset($transient->response[$this->plugin_basename]);
                }
                return $transient;
            }

            $item = (object) [
                'slug' => $this->plugin_slug,
                'plugin' => $this->plugin_basename,
                'new_version' => $new_version,
                'url' => (string) ($release['html_url'] ?? ''),
                'package' => (string) ($release['package'] ?? ''),
            ];

            if (!empty($release['requires'])) {
                $item->requires = (string) $release['requires'];
            }
            if (!empty($release['requires_php'])) {
                $item->requires_php = (string) $release['requires_php'];
            }
            if (!empty($release['tested'])) {
                $item->tested = (string) $release['tested'];
            }

            $transient->response[$this->plugin_basename] = $item;
            return $transient;
        }

        /**
         * @param false|object|array $result
         * @param string             $action
         * @param object             $args
         * @return false|object|array
         */
        public function filter_plugins_api($result, $action, $args) {
            if ($action !== 'plugin_information' || !is_object($args) || empty($args->slug)) {
                return $result;
            }

            if ((string) $args->slug !== (string) $this->plugin_slug) {
                return $result;
            }

            $release = $this->get_release_data();
            if (is_wp_error($release) || empty($release['version'])) {
                return $result;
            }

            $plugin_headers = get_file_data($this->plugin_file, [
                'Name' => 'Plugin Name',
                'Author' => 'Author',
                'AuthorURI' => 'Author URI',
                'RequiresWP' => 'Requires at least',
                'RequiresPHP' => 'Requires PHP',
            ]);

            $sections = [
                'description' => !empty($plugin_headers['Name'])
                    ? sprintf(__('Updates are delivered from GitHub repository %s.', 'dashd-analytics-pro'), esc_html($this->repo))
                    : '',
                'changelog' => $this->format_changelog((string) ($release['body'] ?? '')),
            ];

            $info = (object) [
                'name' => (string) ($plugin_headers['Name'] ?? 'DashD Analytics Pro Engine'),
                'slug' => $this->plugin_slug,
                'version' => (string) $release['version'],
                'author' => (string) ($plugin_headers['Author'] ?? ''),
                'author_profile' => (string) ($plugin_headers['AuthorURI'] ?? ''),
                'homepage' => (string) ($release['html_url'] ?? ''),
                'download_link' => (string) ($release['package'] ?? ''),
                'requires' => (string) ($release['requires'] ?? ($plugin_headers['RequiresWP'] ?? '')),
                'requires_php' => (string) ($release['requires_php'] ?? ($plugin_headers['RequiresPHP'] ?? '')),
                'tested' => (string) ($release['tested'] ?? ''),
                'last_updated' => (string) ($release['published_at'] ?? ''),
                'sections' => $sections,
            ];

            return $info;
        }

        /**
         * Add auth headers for private GitHub endpoints/download links.
         *
         * @param array<string,mixed> $args
         * @param string              $url
         * @return array<string,mixed>
         */
        public function filter_http_request_args($args, $url) {
            if ($this->token === '' || !$this->is_repo_related_url((string) $url)) {
                return $args;
            }

            if (!isset($args['headers']) || !is_array($args['headers'])) {
                $args['headers'] = [];
            }

            $args['headers']['Authorization'] = 'Bearer ' . $this->token;
            if (!isset($args['headers']['Accept'])) {
                $args['headers']['Accept'] = 'application/vnd.github+json';
            }

            return $args;
        }

        /**
         * @param mixed                $upgrader
         * @param array<string,mixed>  $hook_extra
         */
        public function action_upgrader_process_complete($upgrader, $hook_extra) {
            if (!is_array($hook_extra) || empty($hook_extra['type']) || empty($hook_extra['action'])) {
                return;
            }
            if ($hook_extra['type'] !== 'plugin' || $hook_extra['action'] !== 'update') {
                return;
            }
            if (empty($hook_extra['plugins']) || !is_array($hook_extra['plugins'])) {
                return;
            }
            if (!in_array($this->plugin_basename, $hook_extra['plugins'], true)) {
                return;
            }

            delete_transient($this->release_cache_key());
        }

        /**
         * @return array<string,mixed>|WP_Error
         */
        private function get_release_data() {
            $cache_key = $this->release_cache_key();
            $cached = get_transient($cache_key);
            if (is_array($cached) && !empty($cached['version'])) {
                return $cached;
            }

            $repo_parts = explode('/', $this->repo, 2);
            if (count($repo_parts) !== 2) {
                return new WP_Error('dashd_github_release_repo', 'Invalid GitHub repository format.');
            }
            $api_url = sprintf(
                'https://api.github.com/repos/%s/%s/releases/latest',
                rawurlencode((string) $repo_parts[0]),
                rawurlencode((string) $repo_parts[1])
            );
            $response = wp_remote_get($api_url, $this->build_request_args(true));
            if (is_wp_error($response)) {
                return $response;
            }

            $status = (int) wp_remote_retrieve_response_code($response);
            if ($status !== 200) {
                return new WP_Error(
                    'dashd_github_release_http_' . $status,
                    sprintf('GitHub release request failed with status code %d.', $status)
                );
            }

            $payload = json_decode((string) wp_remote_retrieve_body($response), true);
            if (!is_array($payload) || empty($payload['tag_name'])) {
                return new WP_Error('dashd_github_release_invalid', 'Invalid GitHub release payload.');
            }

            $version = ltrim((string) $payload['tag_name'], "vV \t\n\r\0\x0B");
            if ($version === '') {
                return new WP_Error('dashd_github_release_version', 'GitHub release version is empty.');
            }

            $package = $this->pick_release_package_url($payload, $version);
            $data = [
                'version' => $version,
                'package' => $package,
                'html_url' => (string) ($payload['html_url'] ?? ''),
                'body' => (string) ($payload['body'] ?? ''),
                'published_at' => (string) ($payload['published_at'] ?? ''),
                'tested' => '',
                'requires' => '',
                'requires_php' => '',
            ];

            set_transient($cache_key, $data, (int) apply_filters('dashd_github_updater_cache_ttl', 6 * HOUR_IN_SECONDS));
            return $data;
        }

        /**
         * @param array<string,mixed> $payload
         * @param string              $version
         * @return string
         */
        private function pick_release_package_url(array $payload, $version) {
            $assets = isset($payload['assets']) && is_array($payload['assets']) ? $payload['assets'] : [];
            $zip_assets = [];
            foreach ($assets as $asset) {
                if (!is_array($asset)) {
                    continue;
                }
                $name = strtolower((string) ($asset['name'] ?? ''));
                $url = (string) ($asset['browser_download_url'] ?? '');
                if ($url === '' || $name === '' || substr($name, -4) !== '.zip') {
                    continue;
                }
                $zip_assets[] = ['name' => $name, 'url' => $url];
            }

            if (!empty($zip_assets)) {
                $normalized_version = strtolower((string) $version);
                $normalized_slug = strtolower((string) $this->plugin_slug);

                foreach ($zip_assets as $asset) {
                    if (strpos($asset['name'], $normalized_slug) !== false && strpos($asset['name'], $normalized_version) !== false) {
                        return (string) $asset['url'];
                    }
                }
                foreach ($zip_assets as $asset) {
                    if (strpos($asset['name'], $normalized_slug) !== false) {
                        return (string) $asset['url'];
                    }
                }

                return (string) $zip_assets[0]['url'];
            }

            return (string) ($payload['zipball_url'] ?? '');
        }

        /**
         * @return string
         */
        private function release_cache_key() {
            return 'dashd_gh_release_' . md5($this->repo . '|' . $this->branch);
        }

        /**
         * @param bool $api
         * @return array<string,mixed>
         */
        private function build_request_args($api = false) {
            $headers = [
                'User-Agent' => 'DashD-Analytics-Pro/' . $this->current_version,
            ];
            if ($api) {
                $headers['Accept'] = 'application/vnd.github+json';
            }
            if ($this->token !== '') {
                $headers['Authorization'] = 'Bearer ' . $this->token;
            }

            return [
                'timeout' => 15,
                'redirection' => 3,
                'reject_unsafe_urls' => true,
                'headers' => $headers,
            ];
        }

        /**
         * @param string $url
         * @return bool
         */
        private function is_repo_related_url($url) {
            $url = (string) $url;
            if ($url === '') {
                return false;
            }

            $repo_path = strtolower('/' . trim($this->repo, '/'));
            $lower = strtolower($url);

            if (strpos($lower, 'api.github.com/repos' . $repo_path) !== false) {
                return true;
            }
            if (strpos($lower, 'github.com' . $repo_path . '/releases') !== false) {
                return true;
            }
            if (strpos($lower, 'codeload.github.com' . $repo_path . '/zip/') !== false) {
                return true;
            }

            return false;
        }

        /**
         * @param string $content
         * @return string
         */
        private function format_changelog($content) {
            $content = trim((string) $content);
            if ($content === '') {
                return __('No changelog provided in the latest GitHub release.', 'dashd-analytics-pro');
            }

            return wp_kses_post(wpautop($content));
        }
    }
}

if (!function_exists('dashd_register_github_updater')) {
    /**
     * Bootstrap GitHub update checker.
     *
     * @param string $plugin_file
     * @param string $current_version
     */
    function dashd_register_github_updater($plugin_file, $current_version) {
        $repo = defined('DASHD_GITHUB_REPO') ? (string) DASHD_GITHUB_REPO : '';
        $repo = (string) apply_filters('dashd_github_repo', $repo);
        $repo = trim($repo);
        if ($repo === '' || preg_match('/^[a-z0-9_.-]+\/[a-z0-9_.-]+$/i', $repo) !== 1) {
            return;
        }

        $branch = defined('DASHD_GITHUB_BRANCH') ? (string) DASHD_GITHUB_BRANCH : 'main';
        $branch = (string) apply_filters('dashd_github_branch', $branch);
        $branch = trim($branch) !== '' ? trim($branch) : 'main';

        $token = defined('DASHD_GITHUB_TOKEN') ? (string) DASHD_GITHUB_TOKEN : '';
        $token = (string) apply_filters('dashd_github_token', $token);

        $enabled = (bool) apply_filters('dashd_github_updater_enabled', true, $repo);
        if (!$enabled) {
            return;
        }

        $updater = new DashD_Github_Updater($plugin_file, (string) $current_version, $repo, $branch, $token);
        $updater->hooks();
    }
}

if (!function_exists('dashd_github_updater_cache_key')) {
    /**
     * Build cache key for GitHub release metadata.
     *
     * @param string $repo
     * @param string $branch
     * @return string
     */
    function dashd_github_updater_cache_key($repo, $branch = 'main') {
        $repo = trim((string) $repo);
        $branch = trim((string) $branch);
        if ($branch === '') {
            $branch = 'main';
        }
        return 'dashd_gh_release_' . md5($repo . '|' . $branch);
    }
}

if (!function_exists('dashd_github_updater_check_now')) {
    /**
     * Force update check for plugins table and clear updater caches.
     *
     * @return true|WP_Error
     */
    function dashd_github_updater_check_now() {
        $repo = defined('DASHD_GITHUB_REPO') ? (string) DASHD_GITHUB_REPO : '';
        $repo = trim((string) apply_filters('dashd_github_repo', $repo));
        if ($repo === '' || preg_match('/^[a-z0-9_.-]+\/[a-z0-9_.-]+$/i', $repo) !== 1) {
            return new WP_Error('dashd_github_repo_invalid', 'Invalid GitHub repository configuration.');
        }

        $branch = defined('DASHD_GITHUB_BRANCH') ? (string) DASHD_GITHUB_BRANCH : 'main';
        $branch = trim((string) apply_filters('dashd_github_branch', $branch));
        if ($branch === '') {
            $branch = 'main';
        }

        delete_transient(dashd_github_updater_cache_key($repo, $branch));
        delete_site_transient('update_plugins');
        if (function_exists('wp_clean_plugins_cache')) {
            wp_clean_plugins_cache(true);
        }

        if (!function_exists('wp_update_plugins')) {
            require_once ABSPATH . 'wp-includes/update.php';
        }
        if (function_exists('wp_update_plugins')) {
            wp_update_plugins();
            return true;
        }

        return new WP_Error('dashd_github_update_unavailable', 'Unable to run WordPress update checker.');
    }
}
