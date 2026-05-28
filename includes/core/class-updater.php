<?php
/**
 * GitHub-based auto/manual updater for XEN Inventory.
 *
 * Hooks into WordPress's built-in plugin update infrastructure and checks
 * the public GitHub repository for new release tags. Supports:
 *
 *  - Automatic background checks (every 12 hours, same cycle as core WP checks).
 *  - Manual "Check for updates" link on the Plugins list page.
 *  - Full plugin-info popup (changelog, version, etc.) when clicking the
 *    "View version X.X.X details" link in the update notice.
 *  - Optional GitHub Personal Access Token (stored in Settings) for higher
 *    API rate limits or future private-repository support.
 *
 * Release packaging convention
 * ─────────────────────────────
 * When you publish a GitHub Release, tag it as "v1.2.3" (semver with a "v"
 * prefix). WordPress will be offered the zip archive that GitHub auto-generates
 * at:  https://github.com/xenroth/xen-inventory/archive/refs/tags/v1.2.3.zip
 *
 * That zip extracts to a folder named "xen-inventory-1.2.3/". The
 * fix_source_dir() method renames it to "xen-inventory/" so WordPress
 * overwrites the correct plugin folder.
 *
 * @package XenInventory\Core
 */

namespace XenInventory\Core;

use stdClass;
use WP_Upgrader;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Updater
 */
class Updater {

    // GitHub repo in "owner/repo" format.
    private const GITHUB_REPO = 'xenroth/xen-inventory';

    // GitHub REST API endpoint for the latest release.
    private const GITHUB_API = 'https://api.github.com/repos/xenroth/xen-inventory/releases/latest';

    // Site-transient key used to cache the API response.
    private const TRANSIENT = 'xen_inventory_update_data';

    // How long to cache the GitHub API response (12 hours).
    private const CACHE_TTL = 12 * HOUR_IN_SECONDS;

    /** @var string  "plugin-folder/plugin-file.php" plugin basename. */
    private string $plugin_file;

    /** @var string  Folder slug: "xen-inventory". */
    private string $plugin_slug;

    /** @var string  Currently installed version, e.g. "1.0.0". */
    private string $version;

    // ------------------------------------------------------------------

    /**
     * @param string $plugin_file  Absolute path to the main plugin file.
     * @param string $version      Currently installed version string.
     */
    public function __construct( string $plugin_file, string $version ) {
        $this->plugin_file = plugin_basename( $plugin_file );
        $this->plugin_slug = dirname( $this->plugin_file );
        $this->version     = $version;
    }

    /**
     * Register all WordPress hooks.
     *
     * @return void
     */
    public function init(): void {
        // Inject update data into WP's plugin update transient.
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'inject_update' ] );

        // Populate the "View details" popup in the update notice row.
        add_filter( 'plugins_api', [ $this, 'plugin_info' ], 10, 3 );

        // Rename the extracted GitHub zip folder to match our plugin slug.
        add_filter( 'upgrader_source_selection', [ $this, 'fix_source_dir' ], 10, 4 );

        // Bust the release cache after any plugin update completes.
        add_action( 'upgrader_process_complete', [ $this, 'purge_cache' ], 10, 2 );

        // Add "Check for updates" to the plugin row on the Plugins page.
        add_filter( 'plugin_action_links_' . $this->plugin_file, [ $this, 'add_check_link' ] );

        // Handle the manual check request (before output).
        add_action( 'admin_init', [ $this, 'handle_manual_check' ] );
    }

    // ======================================================================
    // WordPress update transient — automatic check
    // ======================================================================

    /**
     * Called by WP when it refreshes the update_plugins transient.
     * Injects our release data so WP shows (or suppresses) the update row.
     *
     * @param  object $transient The update_plugins transient value.
     * @return object
     */
    public function inject_update( object $transient ): object {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $release = $this->get_cached_release();
        if ( ! $release ) {
            return $transient;
        }

        $remote_version = ltrim( $release->tag_name, 'v' );

        if ( version_compare( $remote_version, $this->version, '>' ) ) {
            // An update is available — add to the "response" map.
            $transient->response[ $this->plugin_file ] = (object) [
                'slug'         => $this->plugin_slug,
                'plugin'       => $this->plugin_file,
                'new_version'  => $remote_version,
                'url'          => 'https://github.com/' . self::GITHUB_REPO,
                'package'      => $this->build_zip_url( $release->tag_name ),
                'icons'        => [],
                'banners'      => [],
                'banners_rtl'  => [],
                'requires'     => '6.0',
                'tested'       => '6.5',
                'requires_php' => '8.0',
            ];

            // Remove from no_update in case it was there from a prior check.
            unset( $transient->no_update[ $this->plugin_file ] );
        } else {
            // Up to date — register in no_update so WP doesn't flag it.
            $transient->no_update[ $this->plugin_file ] = (object) [
                'slug'         => $this->plugin_slug,
                'plugin'       => $this->plugin_file,
                'new_version'  => $this->version,
                'url'          => 'https://github.com/' . self::GITHUB_REPO,
                'package'      => '',
                'icons'        => [],
                'banners'      => [],
                'banners_rtl'  => [],
                'requires'     => '6.0',
                'tested'       => '6.5',
                'requires_php' => '8.0',
            ];
        }

        return $transient;
    }

    // ======================================================================
    // Plugin information popup
    // ======================================================================

    /**
     * Provides release information for the "View version X.X.X details" modal
     * that appears in the plugin update row.
     *
     * @param  mixed   $result  Existing result (false if none).
     * @param  string  $action  API action being requested.
     * @param  object  $args    Arguments (slug, etc.).
     * @return mixed   stdClass on match, $result otherwise.
     */
    public function plugin_info( mixed $result, string $action, object $args ): mixed {
        if ( 'plugin_information' !== $action ) {
            return $result;
        }

        if ( ! isset( $args->slug ) || $args->slug !== $this->plugin_slug ) {
            return $result;
        }

        $release = $this->get_cached_release();
        if ( ! $release ) {
            return $result;
        }

        $remote_version = ltrim( $release->tag_name, 'v' );
        $changelog      = ! empty( $release->body )
            ? '<pre style="white-space:pre-wrap">' . esc_html( $release->body ) . '</pre>'
            : '<p>' . esc_html__( 'See GitHub for the full changelog.', 'xen-inventory' ) . '</p>';

        $info               = new stdClass();
        $info->name         = 'XEN Inventory';
        $info->slug         = $this->plugin_slug;
        $info->version      = $remote_version;
        $info->author       = '<a href="https://github.com/xenroth">xenroth</a>';
        $info->homepage     = 'https://github.com/' . self::GITHUB_REPO;
        $info->requires     = '6.0';
        $info->tested       = '6.5';
        $info->requires_php = '8.0';
        $info->last_updated = $release->published_at ?? '';
        $info->download_link = $this->build_zip_url( $release->tag_name );
        $info->sections     = [
            'description' => '<p>' . esc_html__( 'XEN Inventory — staff-facing inventory management for WordPress.', 'xen-inventory' ) . '</p>',
            'changelog'   => $changelog,
        ];

        return $info;
    }

    // ======================================================================
    // Source-folder fix after extraction
    // ======================================================================

    /**
     * GitHub's archive zip extracts to a folder named "{repo}-{version}/".
     * WordPress needs the folder to be named "{plugin-slug}/" so it can
     * overwrite the existing plugin directory. This filter renames it.
     *
     * @param  string      $source         Full path to the extracted source directory.
     * @param  string      $remote_source  Temp directory path.
     * @param  WP_Upgrader $upgrader       The upgrader instance.
     * @param  array       $hook_extra     Extra data passed by the upgrader.
     * @return string  Corrected source path.
     */
    public function fix_source_dir( string $source, string $remote_source, WP_Upgrader $upgrader, array $hook_extra ): string {
        global $wp_filesystem;

        // Only act on updates for our plugin.
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_file ) {
            return $source;
        }

        $corrected = trailingslashit( $remote_source ) . $this->plugin_slug . '/';

        if ( $source === $corrected ) {
            return $source;
        }

        if ( $wp_filesystem->move( $source, $corrected ) ) {
            return $corrected;
        }

        // If the rename failed, return the original so WP can surface the error.
        return $source;
    }

    // ======================================================================
    // Cache invalidation after upgrade
    // ======================================================================

    /**
     * Delete the cached release data after any plugin update so the next
     * background check fetches fresh data from GitHub.
     *
     * @param  WP_Upgrader $upgrader   The upgrader instance.
     * @param  array       $hook_extra Extra data about what was upgraded.
     * @return void
     */
    public function purge_cache( WP_Upgrader $upgrader, array $hook_extra ): void {
        if (
            isset( $hook_extra['type'], $hook_extra['action'] ) &&
            'plugin' === $hook_extra['type'] &&
            'update' === $hook_extra['action']
        ) {
            delete_site_transient( self::TRANSIENT );
        }
    }

    // ======================================================================
    // Manual update check
    // ======================================================================

    /**
     * Append a "Check for updates" action link to this plugin's row on
     * the Plugins list page.
     *
     * @param  array<string, string> $links Existing action links.
     * @return array<string, string>
     */
    public function add_check_link( array $links ): array {
        $url = wp_nonce_url(
            add_query_arg( 'xen_check_update', '1', admin_url( 'plugins.php' ) ),
            'xen_check_update'
        );

        $links['check_update'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url( $url ),
            esc_html__( 'Check for updates', 'xen-inventory' )
        );

        return $links;
    }

    /**
     * Handle the manual "Check for updates" GET request.
     *
     * Deletes the cached release data and the core update_plugins transient,
     * then redirects back to plugins.php so WordPress performs a fresh check.
     *
     * @return void
     */
    public function handle_manual_check(): void {
        if (
            ! isset( $_GET['xen_check_update'] ) ||
            ! isset( $_GET['_wpnonce'] ) ||
            ! current_user_can( 'update_plugins' ) ||
            ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'xen_check_update' )
        ) {
            return;
        }

        // Delete our cache and WP's update_plugins transient.
        delete_site_transient( self::TRANSIENT );
        delete_site_transient( 'update_plugins' );

        wp_safe_redirect( admin_url( 'plugins.php' ) );
        exit;
    }

    // ======================================================================
    // Internal helpers
    // ======================================================================

    /**
     * Return cached release data, fetching from GitHub if the cache is stale.
     *
     * Returns null when the API call fails or returns no usable data so callers
     * can bail out gracefully without hammering the API.
     *
     * @return stdClass|null  The decoded GitHub release object, or null.
     */
    private function get_cached_release(): ?stdClass {
        $cached = get_site_transient( self::TRANSIENT );

        // An empty string means "we checked and there was nothing useful" —
        // treat it as a valid (negative) cache hit to avoid API spam.
        if ( false !== $cached ) {
            return ( $cached instanceof stdClass ) ? $cached : null;
        }

        $release = $this->fetch_latest_release();

        // Store even on failure (as empty string) so we back off for CACHE_TTL.
        set_site_transient( self::TRANSIENT, $release ?? '', self::CACHE_TTL );

        return $release;
    }

    /**
     * Fetch the latest release from the GitHub API.
     *
     * @return stdClass|null  Decoded release object, or null on any error.
     */
    private function fetch_latest_release(): ?stdClass {
        $args = [
            'timeout'    => 10,
            'user-agent' => 'XenInventory/' . $this->version . '; WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
        ];

        $response = wp_remote_get( self::GITHUB_API, $args );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            return null;
        }

        $release = json_decode( wp_remote_retrieve_body( $response ) );

        if ( ! ( $release instanceof stdClass ) || empty( $release->tag_name ) ) {
            return null;
        }

        return $release;
    }

    /**
     * Build the direct zip download URL for a given release tag.
     *
     * Uses GitHub's /archive/refs/tags/ endpoint which is always available for
     * public repositories without authentication.
     *
     * @param  string $tag  Tag name, e.g. "v1.2.3".
     * @return string
     */
    private function build_zip_url( string $tag ): string {
        return sprintf(
            'https://github.com/%s/archive/refs/tags/%s.zip',
            self::GITHUB_REPO,
            rawurlencode( $tag )
        );
    }
}
