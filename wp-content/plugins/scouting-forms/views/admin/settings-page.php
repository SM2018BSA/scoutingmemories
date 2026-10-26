<?php
/**
 * Settings (administrators): how the forms' submit buttons look.
 */

use ScoutingMemories\Forms\Ui\ButtonStyle;

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e('Scouting Forms Settings', 'scouting-forms'); ?></h1>
    <?php settings_errors(); ?>

    <form method="post" action="<?php echo esc_url(admin_url('options.php')); ?>">
        <?php settings_fields('sm_forms_settings'); ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e('Submit buttons', 'scouting-forms'); ?></th>
                <td>
                    <label for="sm_forms_green_buttons">
                        <input type="checkbox" id="sm_forms_green_buttons" name="<?php echo esc_attr(ButtonStyle::OPTION); ?>" value="1" <?php checked(ButtonStyle::green()); ?>>
                        <?php esc_html_e('Use the site green for submit buttons', 'scouting-forms'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Covers Submit, Next, Login and Reset Password. When unticked, the buttons keep the colours they had under Formidable (its form style, light blue by default).', 'scouting-forms'); ?></p>
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
</div>
