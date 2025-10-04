<?php
/**
 * Legacy settings page wrapper for Content Protect Pro options.
 *
 * @package ContentProtectPro
 */

if (!defined('ABSPATH')) {
    exit;
}

$active_tab = $active_tab ?? 'general';
$option_group = class_exists('CPP_Settings_AI') ? CPP_Settings_AI::get_option_group() : 'cpp_settings_group';
?>

<div class="wrap">
    <h1><?php echo esc_html__('Content Protect Pro Settings', 'content-protect-pro'); ?></h1>

    <h2 class="nav-tab-wrapper">
        <a href="?page=content-protect-pro-settings&amp;tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
            <?php echo esc_html__('General', 'content-protect-pro'); ?>
        </a>
        <a href="?page=content-protect-pro-settings&amp;tab=integrations" class="nav-tab <?php echo $active_tab === 'integrations' ? 'nav-tab-active' : ''; ?>">
            <?php echo esc_html__('Integrations', 'content-protect-pro'); ?>
        </a>
        <a href="?page=content-protect-pro-settings&amp;tab=ai" class="nav-tab <?php echo $active_tab === 'ai' ? 'nav-tab-active' : ''; ?>">
            <?php echo esc_html__('AI Assistant', 'content-protect-pro'); ?>
        </a>
    </h2>

    <form method="post" action="options.php">
        <?php settings_fields($option_group); ?>
        <?php
        switch ($active_tab) {
            case 'ai':
                include plugin_dir_path(__FILE__) . 'admin/partials/cpp-settings-ai-integration.php';
                break;
            // ...existing cases...
        }

        submit_button();
        ?>
    </form>
</div>