<?php
/**
 * Plugin Name: JWOP Scripture Copyright Footer
 * Description: Automatically appends translation copyright notices to single blog posts that contain scripture markers (NIV®, AMP, ESV, MSG, NKJV, NLT). Includes a per-post disable toggle and a global on/off switch under Settings → Reading.
 * Version: 1.0.0
 * Author: JWOP + ChatGPT
 */

if (!defined('ABSPATH')) { exit; }

function jwop_scf_get_enabled() {
    $val = get_option('jwop_scf_enabled', '1');
    return $val === '1';
}
register_activation_hook(__FILE__, function() {
    if (get_option('jwop_scf_enabled', null) === null) {
        add_option('jwop_scf_enabled', '1');
    }
});

add_action('admin_init', function() {
    add_settings_section(
        'jwop_scf_section',
        'Scripture Copyright Footer',
        function(){ echo '<p>Automatically add translation copyright notices at the end of single posts that contain scripture markers.</p>'; },
        'reading'
    );
    add_settings_field(
        'jwop_scf_enabled',
        'Enable automatic footer',
        function(){
            $enabled = jwop_scf_get_enabled();
            echo '<label><input type="checkbox" name="jwop_scf_enabled" value="1" '.checked($enabled, true, false).' /> Show footer on posts when scripture markers are detected</label>';
        },
        'reading',
        'jwop_scf_section'
    );
    register_setting('reading', 'jwop_scf_enabled', array(
        'type' => 'string',
        'sanitize_callback' => function($v){ return $v === '1' ? '1' : '0'; },
        'default' => '1'
    ));
});

add_action('add_meta_boxes', function() {
    add_meta_box(
        'jwop_scf_metabox',
        'Scripture Copyright Footer',
        function($post){
            $val = get_post_meta($post->ID, '_jwop_scf_disable', true);
            wp_nonce_field('jwop_scf_mb', 'jwop_scf_mb_nonce');
            echo '<p><label><input type="checkbox" name="jwop_scf_disable" value="1" '.checked($val, '1', false).' /> Disable footer for this post</label></p>';
            echo '<p class="description">Tick if this post (e.g., poetry) should not show translation notices, even if scripture markers are detected.</p>';
        },
        'post',
        'side',
        'default'
    );
});
add_action('save_post', function($post_id){
    if (!isset($_POST['jwop_scf_mb_nonce']) || !wp_verify_nonce($_POST['jwop_scf_mb_nonce'], 'jwop_scf_mb')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    $val = isset($_POST['jwop_scf_disable']) ? '1' : '0';
    update_post_meta($post_id, '_jwop_scf_disable', $val);
});

function jwop_scf_detect_translations($content) {
    $found = array();
    $map = array('NIV'=>'NIV','AMP'=>'AMP','ESV'=>'ESV','MSG'=>'MSG','NKJV'=>'NKJV','NLT'=>'NLT');
    foreach ($map as $key => $abbr) {
        $pattern = ($key === 'NIV') ? '/\bNIV(?:®|&reg;)?\b/i' : '/\b' . preg_quote($abbr, '/') . '\b/i';
        if (preg_match($pattern, $content)) $found[] = $key;
    }
    return array_unique($found);
}

function jwop_scf_build_footer_html($codes) {
    if (empty($codes)) return '';
    $notices = array(
        'NIV'  => "Scripture quotations marked NIV® are taken from the Holy Bible, New International Version®, NIV®. Copyright © 1973, 1978, 1984, 2011 by Biblica, Inc.™ Used by permission. All rights reserved worldwide.",
        'AMP'  => "Scripture quotations marked AMP are taken from the Amplified® Bible, Copyright © 2015 by The Lockman Foundation, La Habra, CA 90631, www.lockman.org. All rights reserved.",
        'ESV'  => "Scripture quotations marked ESV are from The Holy Bible, English Standard Version®. Copyright © 2001 by Crossway, a publishing ministry of Good News Publishers. Used by permission. All rights reserved.",
        'MSG'  => "Scripture quotations marked MSG are from THE MESSAGE. Copyright © by Eugene H. Peterson. Used by permission. All rights reserved.",
        'NKJV' => "Scripture quotations marked NKJV are from the New King James Version®. Copyright © 1982 by Thomas Nelson. Used by permission. All rights reserved.",
        'NLT'  => "Scripture quotations marked NLT are from the Holy Bible, New Living Translation, Copyright © 1996, 2004, 2015 by Tyndale House Foundation. Used by permission of Tyndale House Publishers, Inc., Carol Stream, Illinois 60188. All rights reserved.",
    );
    $lines = array();
    foreach ($codes as $code) if (isset($notices[$code])) $lines[] = esc_html($notices[$code]);
    if (empty($lines)) return '';
    $style = 'margin-top:1.25rem;padding:0.75rem 1rem;border:1px solid #e6e6e6;border-radius:8px;background:#fafafa;color:#666;font-size:0.9em;line-height:1.5;';
    $html  = '<div class="jwop-scf-footer" style="'.esc_attr($style).'"><strong>Scripture Copyright:</strong><br>';
    $html .= implode('<br><br>', $lines);
    $html .= '</div>';
    return $html;
}

add_filter('the_content', function($content){
    if (!is_singular('post') || !in_the_loop() || !is_main_query()) return $content;
    if (!jwop_scf_get_enabled()) return $content;
    $post_id = get_the_ID();
    $disabled = get_post_meta($post_id, '_jwop_scf_disable', true) === '1';
    if ($disabled) return $content;
    $codes = jwop_scf_detect_translations($content);
    if (empty($codes)) return $content;
    $footer = jwop_scf_build_footer_html($codes);
    if (!$footer) return $content;
    return $content . "\n\n" . $footer;
}, 50);
