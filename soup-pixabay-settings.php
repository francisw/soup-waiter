<?php


add_action('admin_menu', 'pixabay_images_add_settings_menu');
function pixabay_images_add_settings_menu() {
    add_options_page(__('Pixabay Images Settings', 'pixabay_images'), __('Pixabay Images', 'pixabay_images'), 'manage_options', 'pixabay_images_settings', 'pixabay_images_settings_page');
    add_action('admin_init', 'register_pixabay_images_options');
}


function register_pixabay_images_options(){
    register_setting('pixabay_images_options', 'pixabay_images_options', 'pixabay_images_options_validate');
    add_settings_section('pixabay_images_options_section', '', '', 'pixabay_images_settings');
    add_settings_field('language-id', __('Language', 'pixabay_images'), 'pixabay_images_render_language', 'pixabay_images_settings', 'pixabay_images_options_section');
    add_settings_field('attribution-id', __('Attribution', 'pixabay_images'), 'pixabay_images_render_attribution', 'pixabay_images_settings', 'pixabay_images_options_section');
    add_settings_field('button-id', __('Button', 'pixabay_images'), 'pixabay_images_render_button', 'pixabay_images_settings', 'pixabay_images_options_section');
}


function pixabay_images_render_language(){
    global $pixabay_images_gallery_languages;
    $options = get_option('pixabay_images_options');
    $set_lang = substr(get_locale(), 0, 2);
    if (!$options['language']) $options['language'] = $pixabay_images_gallery_languages[$set_lang]?$set_lang:'en';
    echo '<select name="pixabay_images_options[language]">';
    foreach ($pixabay_images_gallery_languages as $k => $v) { echo '<option value="'.$k.'"'.($options['language']==$k?' selected="selected"':'').'>'.$v.'</option>'; }
    echo '</select>';
}

function pixabay_images_render_attribution(){
    $options = get_option('pixabay_images_options');
    echo '<label><input name="pixabay_images_options[attribution]" value="true" type="checkbox"'.(!$options['attribution'] | $options['attribution']=='true'?' checked="checked"':'').'> '.__('Insert image credits', 'pixabay_images').'</label>';
}

function pixabay_images_render_button(){
    $options = get_option('pixabay_images_options');
    echo '<label><input name="pixabay_images_options[button]" value="true" type="checkbox"'.(!$options['button'] | $options['button']=='true'?' checked="checked"':'').'> '.__('Show Pixabay button next to "Add Media"', 'pixabay_images').'</label>';
}


function pixabay_images_settings_page() { ?>
    <div class="wrap">
    <h2><?= _e('Pixabay Images', 'pixabay_images'); ?></h2>
    <form method="post" action="options.php">
        <?php
            settings_fields('pixabay_images_options');
            do_settings_sections('pixabay_images_settings');
            submit_button();
        ?>
    </form>
    <hr style="margin-bottom:20px">
    <p>
        Official <a href="https://pixabay.com/"><img src="<?= plugin_dir_url(__FILE__).'img/logo.png' ?>" style="width:120px;margin:0 5px;position:relative;top:5px"></a> plugin by <a href="https://pixabay.com/service/about/">Simon Steinberger</a> and <a href="http://efs.byrev.org/">Emilian Robert Vicol</a>.
        Serbian translation by <a href="http://firstsiteguide.com/">Ogi Djuraskovic</a>.
    </p>
    <p>Find us on <a href="https://www.facebook.com/pixabay">Facebook</a>, <a href="https://plus.google.com/+Pixabay">Google+</a> and <a href="https://twitter.com/pixabay">Twitter</a>.</p>
    </div>
<?php }


function pixabay_images_options_validate($input){
    global $pixabay_images_gallery_languages;
    $options = get_option('pixabay_images_options');
    if ($pixabay_images_gallery_languages[$input['language']]) $options['language'] = $input['language'];
    if ($input['attribution']) $options['attribution'] = 'true'; else $options['attribution'] = 'false';
    if ($input['button']) $options['button'] = 'true'; else $options['button'] = 'false';
    return $options;
}
?>
