<?php
/**
 * Plugin Name: WP Template Sniffer
 * Plugin URI: https://github.com/TABARC-Code/wp-template-sniffer
 * Description: Audits the active theme for missing core templates, child overrides, unused page templates and general template hierarchy nonsense.
 * Version: 1.0.0
 * Author: TABARC-Code
 * Author URI: https://github.com/TABARC-Code
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * Why this exists:
 * WordPress has a very polite template hierarchy and absolutely no interest in telling you when
 * your theme is missing key templates, overriding things badly, or shipping page templates nobody uses.
 *
 * This plugin sniffs the active theme and parent theme and reports:
 * - Missing or fallback critical templates (index, single, page and friends).
 * - Child theme template overrides and what they shadow in the parent.
 * - Page templates that exist but are never used.
 * - Page templates that are used in content but missing from disk.
 * - Basic awareness of block templates so I remember they exist.
 *
 * It does not edit any files. No regeneration, no auto repair, no clever guessing.
 * Just a blunt report so I know where the problems are hiding.
 *
 * TODO: add export as JSON so I can paste results into bug reports.
 * TODO: add optional checks for Woo templates and other plugin specific overrides.
 * FIXME: block theme support is intentionally shallow in this version.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_Template_Sniffer' ) ) {

    class WP_Template_Sniffer {

        private $screen_slug = 'wp-template-sniffer';

        public function __construct() {
            add_action( 'admin_menu', array( $this, 'add_tools_page' ) );
            add_action( 'admin_head-plugins.php', array( $this, 'inject_plugin_list_icon_css' ) );
        }

        /**
         * Shared icon location I use across my plugins.
         */
        private function get_brand_icon_url() {
            return plugin_dir_url( __FILE__ ) . '.branding/tabarc-icon.svg';
        }

        public function add_tools_page() {
            add_management_page(
                __( 'Template Sniffer', 'wp-template-sniffer' ),
                __( 'Template Sniffer', 'wp-template-sniffer' ),
                'manage_options',
                $this->screen_slug,
                array( $this, 'render_screen' )
            );
        }

        public function render_screen() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-template-sniffer' ) );
            }

            $theme       = wp_get_theme();
            $parent      = $theme->parent();
            $is_child    = ( $parent instanceof WP_Theme );
            $stylesheet_dir = get_stylesheet_directory();
            $template_dir   = get_template_directory();

            $scan = $this->scan_templates( $stylesheet_dir, $template_dir, $is_child );
            $page_template_info = $this->scan_page_templates( $theme );
            $block_templates    = $this->scan_block_templates( $stylesheet_dir, $template_dir );

            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'WP Template Sniffer', 'wp-template-sniffer' ); ?></h1>
                <p>
                    This looks at the currently active theme and, if present, its parent, then quietly reports where
                    your template hierarchy is being held together by wishful thinking.
                </p>

                <?php $this->render_theme_overview( $theme, $parent, $is_child, $stylesheet_dir, $template_dir, $block_templates ); ?>

                <h2><?php esc_html_e( 'Core template coverage', 'wp-template-sniffer' ); ?></h2>
                <p>
                    These are the usual suspects in the template hierarchy. Missing ones are not instant bugs,
                    but they tell you where WordPress is forced to fall back to more generic templates.
                </p>
                <?php $this->render_core_coverage( $scan ); ?>

                <h2><?php esc_html_e( 'Child overrides and parent only templates', 'wp-template-sniffer' ); ?></h2>
                <p>
                    If you are using a child theme, this shows which templates override the parent and which ones
                    only exist in the parent theme.
                </p>
                <?php $this->render_overrides( $scan, $is_child ); ?>

                <h2><?php esc_html_e( 'Page templates: used, unused, missing', 'wp-template-sniffer' ); ?></h2>
                <p>
                    These are the fancy templates you pick in the Page edit screen. This section shows which ones
                    exist but nobody uses, and which ones are referenced by content but missing on disk.
                </p>
                <?php $this->render_page_template_info( $page_template_info ); ?>

                <h2><?php esc_html_e( 'Miscellaneous template files', 'wp-template-sniffer' ); ?></h2>
                <p>
                    Random PHP files under the theme directory. Some are harmless partials, some are abandoned experiments.
                    This list is here to remind me they exist.
                </p>
                <?php $this->render_misc_templates( $scan ); ?>
            </div>
            <?php
        }

        /**
         * Scan child and parent theme directories and build a map of core and other templates.
         */
        private function scan_templates( $stylesheet_dir, $template_dir, $is_child ) {
            $expected_core = array(
                'index.php',
                'front-page.php',
                'home.php',
                'single.php',
                'page.php',
                'archive.php',
                'category.php',
                'tag.php',
                'author.php',
                'date.php',
                'search.php',
                '404.php',
                'attachment.php',
                'taxonomy.php',
                'singular.php',
            );

            $child_files  = $this->list_php_files( $stylesheet_dir );
            $parent_files = $is_child ? $this->list_php_files( $template_dir ) : $child_files;

            $child_relative = array();
            foreach ( $child_files as $file ) {
                $rel = ltrim( str_replace( $stylesheet_dir, '', $file ), DIRECTORY_SEPARATOR );
                $child_relative[ $rel ] = true;
            }

            $parent_relative = array();
            foreach ( $parent_files as $file ) {
                $rel = ltrim( str_replace( $template_dir, '', $file ), DIRECTORY_SEPARATOR );
                $parent_relative[ $rel ] = true;
            }

            $core = array();
            foreach ( $expected_core as $basename ) {
                $in_child  = isset( $child_relative[ $basename ] );
                $in_parent = isset( $parent_relative[ $basename ] );
                $core[ $basename ] = array(
                    'in_child'  => $in_child,
                    'in_parent' => $in_parent,
                );
            }

            // Overridden templates (child shadows parent).
            $overrides = array();
            if ( $is_child ) {
                foreach ( $child_relative as $rel => $_ ) {
                    if ( isset( $parent_relative[ $rel ] ) ) {
                        $overrides[] = $rel;
                    }
                }
            }

            // Parent only templates.
            $parent_only = array();
            if ( $is_child ) {
                foreach ( $parent_relative as $rel => $_ ) {
                    if ( ! isset( $child_relative[ $rel ] ) ) {
                        $parent_only[] = $rel;
                    }
                }
            }

            // Misc files: anything not in the core list and at root, plus anything in template-parts.
            $child_misc = array();
            foreach ( $child_relative as $rel => $_ ) {
                if ( $this->is_core_template_name( $rel, $expected_core ) ) {
                    continue;
                }
                $child_misc[] = $rel;
            }

            $parent_misc = array();
            foreach ( $parent_relative as $rel => $_ ) {
                if ( $this->is_core_template_name( $rel, $expected_core ) ) {
                    continue;
                }
                $parent_misc[] = $rel;
            }

            return array(
                'core'         => $core,
                'child_files'  => $child_relative,
                'parent_files' => $parent_relative,
                'overrides'    => $overrides,
                'parent_only'  => $parent_only,
                'child_misc'   => $child_misc,
                'parent_misc'  => $parent_misc,
            );
        }

        private function is_core_template_name( $rel, $expected_core ) {
            // Only treat a file as core when it sits at the root, not in subdirectories.
            $basename = basename( $rel );
            if ( in_array( $basename, $expected_core, true ) && dirname( $rel ) === '.' ) {
                return true;
            }
            return false;
        }

        private function list_php_files( $base_dir ) {
            $files = array();
            if ( ! is_dir( $base_dir ) ) {
                return $files;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator( $base_dir, FilesystemIterator::SKIP_DOTS )
            );

            foreach ( $iterator as $file ) {
                /** @var SplFileInfo $file */
                if ( ! $file->isFile() ) {
                    continue;
                }
                if ( strtolower( $file->getExtension() ) !== 'php' ) {
                    continue;
                }

                $path = $file->getPathname();

                // Skip common junk directories if someone left tooling in the theme.
                if ( strpos( $path, DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR ) !== false ) {
                    continue;
                }
                if ( strpos( $path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR ) !== false ) {
                    continue;
                }

                $files[] = $path;
            }

            return $files;
        }

        /**
         * Page templates via core theme API and which ones content actually uses.
         */
        private function scan_page_templates( WP_Theme $theme ) {
            global $wpdb;

            $templates = $theme->get_page_templates(); // [ name => filename ]
            $used      = $wpdb->get_col(
                "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_page_template'"
            );

            $available_paths = array_values( $templates );
            $used_set        = array();
            foreach ( $used as $value ) {
                $used_set[ $value ] = true;
            }

            $unused_templates = array();
            foreach ( $templates as $name => $path ) {
                if ( $path === 'default' ) {
                    continue;
                }
                if ( empty( $used_set[ $path ] ) ) {
                    $unused_templates[] = array(
                        'name' => $name,
                        'path' => $path,
                    );
                }
            }

            // Templates that content asks for but which are not available.
            $missing_templates = array();
            foreach ( $used as $value ) {
                if ( $value === 'default' ) {
                    continue;
                }
                if ( ! in_array( $value, $available_paths, true ) ) {
                    $missing_templates[] = $value;
                }
            }

            return array(
                'templates'        => $templates,
                'used_values'      => $used,
                'unused_templates' => $unused_templates,
                'missing_templates'=> array_unique( $missing_templates ),
            );
        }

        /**
         * Minimal awareness of block templates, mostly so I remember if the theme is block based.
         */
        private function scan_block_templates( $stylesheet_dir, $template_dir ) {
            $paths = array();

            $dirs_to_check = array(
                $stylesheet_dir . '/templates',
                $stylesheet_dir . '/parts',
            );

            if ( $template_dir !== $stylesheet_dir ) {
                $dirs_to_check[] = $template_dir . '/templates';
                $dirs_to_check[] = $template_dir . '/parts';
            }

            foreach ( $dirs_to_check as $dir ) {
                if ( ! is_dir( $dir ) ) {
                    continue;
                }
                $entries = glob( trailingslashit( $dir ) . '*.html' );
                if ( $entries ) {
                    foreach ( $entries as $entry ) {
                        $paths[] = $entry;
                    }
                }
            }

            return array(
                'count' => count( $paths ),
                'paths' => $paths,
            );
        }

        private function render_theme_overview( WP_Theme $theme, $parent, $is_child, $stylesheet_dir, $template_dir, $block_templates ) {
            ?>
            <h2><?php esc_html_e( 'Theme overview', 'wp-template-sniffer' ); ?></h2>
            <table class="widefat striped" style="max-width:900px;">
                <tbody>
                    <tr>
                        <th><?php esc_html_e( 'Active theme', 'wp-template-sniffer' ); ?></th>
                        <td>
                            <?php echo esc_html( $theme->get( 'Name' ) ); ?>
                            <br>
                            <span style="font-size:12px;opacity:0.8;">
                                <?php esc_html_e( 'Stylesheet directory:', 'wp-template-sniffer' ); ?>
                                <code><?php echo esc_html( $stylesheet_dir ); ?></code>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Parent theme', 'wp-template-sniffer' ); ?></th>
                        <td>
                            <?php
                            if ( $is_child && $parent instanceof WP_Theme ) {
                                echo esc_html( $parent->get( 'Name' ) );
                                echo '<br><span style="font-size:12px;opacity:0.8;">';
                                esc_html_e( 'Template directory:', 'wp-template-sniffer' );
                                echo ' <code>' . esc_html( $template_dir ) . '</code>';
                                echo '</span>';
                            } else {
                                esc_html_e( 'None. This theme is not a child theme.', 'wp-template-sniffer' );
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Block templates detected', 'wp-template-sniffer' ); ?></th>
                        <td>
                            <?php
                            if ( $block_templates['count'] > 0 ) {
                                echo esc_html( $block_templates['count'] ) . ' ';
                                esc_html_e( 'block template files found under templates or parts.', 'wp-template-sniffer' );
                            } else {
                                esc_html_e( 'No block template files detected. This looks like a classic theme, or block templates live elsewhere.', 'wp-template-sniffer' );
                            }
                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php
        }

        private function render_core_coverage( $scan ) {
            $core = $scan['core'];

            if ( empty( $core ) ) {
                echo '<p>' . esc_html__( 'No core template data available. This should not happen.', 'wp-template-sniffer' ) . '</p>';
                return;
            }

            ?>
            <table class="widefat striped" style="max-width:900px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Template', 'wp-template-sniffer' ); ?></th>
                        <th><?php esc_html_e( 'In child theme', 'wp-template-sniffer' ); ?></th>
                        <th><?php esc_html_e( 'In parent theme', 'wp-template-sniffer' ); ?></th>
                        <th><?php esc_html_e( 'Notes', 'wp-template-sniffer' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $core as $name => $where ) : ?>
                    <?php
                    $in_child  = $where['in_child'];
                    $in_parent = $where['in_parent'];

                    $notes = array();

                    if ( ! $in_child && ! $in_parent ) {
                        if ( $name === 'index.php' ) {
                            $notes[] = __( 'This is mandatory. If this is truly missing, the theme is broken.', 'wp-template-sniffer' );
                        } else {
                            $notes[] = __( 'Missing. WordPress will fall back to a more generic template.', 'wp-template-sniffer' );
                        }
                    } elseif ( $in_child && $in_parent ) {
                        $notes[] = __( 'Child theme overrides parent version.', 'wp-template-sniffer' );
                    } elseif ( $in_child ) {
                        $notes[] = __( 'Only present in child theme.', 'wp-template-sniffer' );
                    } elseif ( $in_parent ) {
                        $notes[] = __( 'Only present in parent. Child falls back to this.', 'wp-template-sniffer' );
                    }
                    ?>
                    <tr>
                        <td><code><?php echo esc_html( $name ); ?></code></td>
                        <td>
                            <?php echo $in_child ? '<span style="color:#46b450;">yes</span>' : '<span style="color:#dc3232;">no</span>'; ?>
                        </td>
                        <td>
                            <?php echo $in_parent ? '<span style="color:#46b450;">yes</span>' : '<span style="color:#dc3232;">no</span>'; ?>
                        </td>
                        <td>
                            <?php
                            if ( empty( $notes ) ) {
                                esc_html_e( 'Nothing interesting to report.', 'wp-template-sniffer' );
                            } else {
                                echo esc_html( implode( ' ', $notes ) );
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p style="font-size:12px;opacity:0.8;">
                <?php esc_html_e( 'Missing templates are not automatically a bug. They just mean index.php or other generic templates carry more weight than they should.', 'wp-template-sniffer' ); ?>
            </p>
            <?php
        }

        private function render_overrides( $scan, $is_child ) {
            if ( ! $is_child ) {
                echo '<p>' . esc_html__( 'This site is not using a child theme. There are no child theme overrides to report.', 'wp-template-sniffer' ) . '</p>';
                return;
            }

            $overrides   = $scan['overrides'];
            $parent_only = $scan['parent_only'];

            ?>
            <h3><?php esc_html_e( 'Child overrides', 'wp-template-sniffer' ); ?></h3>
            <?php
            if ( empty( $overrides ) ) {
                echo '<p>' . esc_html__( 'No templates in the child theme are shadowing files in the parent.', 'wp-template-sniffer' ) . '</p>';
            } else {
                echo '<table class="widefat striped" style="max-width:900px;"><thead><tr><th>Relative path</th></tr></thead><tbody>';
                foreach ( $overrides as $rel ) {
                    echo '<tr><td><code>' . esc_html( $rel ) . '</code></td></tr>';
                }
                echo '</tbody></table>';
                echo '<p style="font-size:12px;opacity:0.8;">';
                esc_html_e( 'These files in the child theme completely replace the parent versions with the same path.', 'wp-template-sniffer' );
                echo '</p>';
            }

            ?>
            <h3><?php esc_html_e( 'Parent only templates', 'wp-template-sniffer' ); ?></h3>
            <?php
            if ( empty( $parent_only ) ) {
                echo '<p>' . esc_html__( 'No parent only templates detected. Child and parent are roughly aligned.', 'wp-template-sniffer' ) . '</p>';
            } else {
                echo '<table class="widefat striped" style="max-width:900px;"><thead><tr><th>Relative path</th></tr></thead><tbody>';
                foreach ( $parent_only as $rel ) {
                    echo '<tr><td><code>' . esc_html( $rel ) . '</code></td></tr>';
                }
                echo '</tbody></table>';
                echo '<p style="font-size:12px;opacity:0.8;">';
                esc_html_e( 'These templates only exist in the parent theme. The child theme falls back to them as needed.', 'wp-template-sniffer' );
                echo '</p>';
            }
        }

        private function render_page_template_info( $info ) {
            $templates         = $info['templates'];
            $unused_templates  = $info['unused_templates'];
            $missing_templates = $info['missing_templates'];

            ?>
            <h3><?php esc_html_e( 'Templates defined by the theme', 'wp-template-sniffer' ); ?></h3>
            <?php
            if ( empty( $templates ) ) {
                echo '<p>' . esc_html__( 'This theme does not define any page templates. Everything uses the default.', 'wp-template-sniffer' ) . '</p>';
            } else {
                echo '<table class="widefat striped" style="max-width:900px;"><thead><tr><th>Template name</th><th>Relative path</th></tr></thead><tbody>';
                foreach ( $templates as $name => $path ) {
                    echo '<tr><td>' . esc_html( $name ) . '</td><td><code>' . esc_html( $path ) . '</code></td></tr>';
                }
                echo '</tbody></table>';
            }

            ?>
            <h3><?php esc_html_e( 'Templates that exist but nobody is using', 'wp-template-sniffer' ); ?></h3>
            <?php
            if ( empty( $unused_templates ) ) {
                echo '<p>' . esc_html__( 'No unused templates detected. Either they are all in use or nobody is using page templates at all.', 'wp-template-sniffer' ) . '</p>';
            } else {
                echo '<table class="widefat striped" style="max-width:900px;"><thead><tr><th>Template name</th><th>Path</th></tr></thead><tbody>';
                foreach ( $unused_templates as $row ) {
                    echo '<tr><td>' . esc_html( $row['name'] ) . '</td><td><code>' . esc_html( $row['path'] ) . '</code></td></tr>';
                }
                echo '</tbody></table>';
                echo '<p style="font-size:12px;opacity:0.8;">';
                esc_html_e( 'These can sometimes be removed to reduce confusion, once you are sure they are not used by older content.', 'wp-template-sniffer' );
                echo '</p>';
            }

            ?>
            <h3><?php esc_html_e( 'Templates that content asks for but are missing on disk', 'wp-template-sniffer' ); ?></h3>
            <?php
            if ( empty( $missing_templates ) ) {
                echo '<p>' . esc_html__( 'No missing page templates detected. Every template referenced by pages appears to exist.', 'wp-template-sniffer' ) . '</p>';
            } else {
                echo '<table class="widefat striped" style="max-width:900px;"><thead><tr><th>Meta value</th></tr></thead><tbody>';
                foreach ( $missing_templates as $value ) {
                    echo '<tr><td><code>' . esc_html( $value ) . '</code></td></tr>';
                }
                echo '</tbody></table>';
                echo '<p style="font-size:12px;opacity:0.8;">';
                esc_html_e( 'These values are stored in page meta, but the corresponding template file is missing. Those pages are falling back to the default template.', 'wp-template-sniffer' );
                echo '</p>';
            }
        }

        private function render_misc_templates( $scan ) {
            $child_misc  = $scan['child_misc'];
            $parent_misc = $scan['parent_misc'];

            ?>
            <h3><?php esc_html_e( 'Child theme miscellaneous PHP files', 'wp-template-sniffer' ); ?></h3>
            <?php
            if ( empty( $child_misc ) ) {
                echo '<p>' . esc_html__( 'No non core template files detected in the child theme.', 'wp-template-sniffer' ) . '</p>';
            } else {
                echo '<table class="widefat striped" style="max-width:900px;"><thead><tr><th>Relative path</th></tr></thead><tbody>';
                foreach ( $child_misc as $rel ) {
                    echo '<tr><td><code>' . esc_html( $rel ) . '</code></td></tr>';
                }
                echo '</tbody></table>';
            }

            ?>
            <h3><?php esc_html_e( 'Parent theme miscellaneous PHP files', 'wp-template-sniffer' ); ?></h3>
            <?php
            if ( empty( $parent_misc ) ) {
                echo '<p>' . esc_html__( 'No non core template files detected in the parent theme.', 'wp-template-sniffer' ) . '</p>';
            } else {
                echo '<table class="widefat striped" style="max-width:900px;"><thead><tr><th>Relative path</th></tr></thead><tbody>';
                foreach ( $parent_misc as $rel ) {
                    echo '<tr><td><code>' . esc_html( $rel ) . '</code></td></tr>';
                }
                echo '</tbody></table>';
                echo '<p style="font-size:12px;opacity:0.8;">';
                esc_html_e( 'Some of these will be harmless partials. Others might be relics. This list is a prompt, not a to do list.', 'wp-template-sniffer' );
                echo '</p>';
            }
        }

        public function inject_plugin_list_icon_css() {
            $icon_url = esc_url( $this->get_brand_icon_url() );
            ?>
            <style>
                .wp-list-table.plugins tr[data-slug="wp-template-sniffer"] .plugin-title strong::before {
                    content: '';
                    display: inline-block;
                    vertical-align: middle;
                    width: 18px;
                    height: 18px;
                    margin-right: 6px;
                    background-image: url('<?php echo $icon_url; ?>');
                    background-repeat: no-repeat;
                    background-size: contain;
                }
            </style>
            <?php
        }
    }

    new WP_Template_Sniffer();
}
