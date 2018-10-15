<?php

/*
Copied from Simon's plugin below, and modified for VacationSoup
by Francis Wallinger.

Primary changes are:
- adding force first image in post to be Featured
- prefilling the search box with keywords
- randomising the order of returned images

Plugin URI: https://pixabay.com/blog/posts/p-36/
Description: Find quality public domain images from Pixabay and upload them with just one click.
Version: 3.0
Author: Simon Steinberger
Author URI: https://pixabay.com/users/Simon/
License: GPLv2
*/


// i18n
function soup_pixabay_images_load_textdomain() {
	load_plugin_textdomain( 'soup_pixabay_images', false, dirname( plugin_basename( __FILE__ ) ) . '/langs/' );
}

add_action( 'plugins_loaded', 'soup_pixabay_images_load_textdomain' );

// add settings
include( plugin_dir_path( __FILE__ ) . 'soup-pixabay-settings.php' );

function soup_pixabay_images_enqueue_jquery() {
	wp_enqueue_script( 'jquery' );
}

add_action( 'admin_enqueue_scripts', 'soup_pixabay_images_enqueue_jquery' );


// add tab to media upload window
function soup_media_upload_tabs_handler( $tabs ) {
	$tabs['pixabaytab'] = __( 'Pixabay Images', 'soup_pixabay_images' );

	return $tabs;
}

add_filter( 'media_upload_tabs', 'soup_media_upload_tabs_handler' );


// add button next to "Add Media"
$soup_pixabay_images_settings = get_option( 'soup_pixabay_images_options' );
if ( ! $soup_pixabay_images_settings['button'] | $soup_pixabay_images_settings['button'] == 'true' ) {
	function media_buttons_context_handler( $editor_id = '' ) {
		return '<a href="' . add_query_arg( 'tab', 'pixabaytab', esc_url( get_upload_iframe_src() ) ) . '" id="' . esc_attr( $editor_id ) . '-add_media" class="thickbox button" title="' . esc_attr__( 'Pixabay Images', 'soup_pixabay_images' ) . '"><img style="position:relative;top:-2px" src="' . plugin_dir_url( __FILE__ ) . 'img/favicon.png' . '"> Pixabay</a>';
	}

	add_filter( 'media_buttons_context', 'media_buttons_context_handler' );
}


// media tab action
// function must begin with "media_" so wp_iframe() adds media css styles
function media_soup_pixabay_images_tab() {
	media_upload_header();
	$soup_pixabay_images_settings = get_option( 'soup_pixabay_images_options' );
	?>
    <style scope>
        html, body {
            background: #fff;
        }

        ::-webkit-input-placeholder {
            color: #aaa !important;
        }

        ::-moz-placeholder {
            color: #aaa !important;
        }

        :-ms-input-placeholder {
            color: #aaa !important;
        }

        [placeholder] {
            text-overflow: ellipsis;
        }

        .flex-images {
            overflow: hidden;
        }

        .flex-images .item {
            float: left;
            margin: 4px;
            background: #f3f3f3;
            box-sizing: content-box;
            overflow: hidden;
            position: relative;
        }

        .flex-images .item > img {
            display: block;
            width: auto;
            height: 100%;
        }

        .flex-images .download {
            opacity: 0;
            transition: opacity .3s;
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            cursor: pointer;
            background: rgba(0, 0, 0, .65);
            color: #eee;
            text-align: center;
            font-size: 14px;
            line-height: 1.5;
        }

        .flex-images .item:hover .download, .flex-images .item.uploading .download {
            opacity: 1;
        }

        .flex-images .download img {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            margin: auto;
            height: 32px;
            opacity: .7;
        }

        .flex-images .download div {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 15px;
            padding: 0 5px;
        }

        .flex-images .download a {
            color: #eee;
        }

        #pixabay_settings_icon {
            opacity: .65;
            transition: .3s;
            box-shadow: none;
        }

        #pixabay_settings_icon:hover {
            opacity: 1;
        }
    </style>
    <div style="padding:10px 15px 25px">
        <form id="soup_pixabay_images_form" style="margin:0">
            <div style="line-height:1.5;margin:1em 0;max-width:500px;position:relative">
                <input id="q" type="text"  style="width:100%;padding:7px 32px 7px 9px" class="pix-search" value=""
                       autofocus
                       placeholder="<?= htmlspecialchars( __( 'Search for "red roses", "flowers -red", "city OR town", etc.', 'soup_pixabay_images' ) ); ?>">
                <button type="submit"
                        style="background:#fff;border:0;cursor:pointer;position:absolute;right:5px;top:10px;outline:0"
                        title="<?= _e( 'Search', 'soup_pixabay_images' ); ?>"><img
                            src="<?= plugin_dir_url( __FILE__ ) . 'img/search.png' ?>"></button>
            </div>
	        <?php /*
            <div style="margin:1em 0;padding-left:2px;line-height:2">
                <label style="margin-right:15px;white-space:nowrap"><input type="checkbox"
                                                                           id="filter_photos"><?= _e( 'Photos', 'soup_pixabay_images' ); ?>
                </label>
                <label style="margin-right:20px;white-space:nowrap"><input type="checkbox"
                                                                           id="filter_cliparts"><?= _e( 'Cliparts', 'soup_pixabay_images' ); ?>
                </label>
                <label style="margin-right:15px;white-space:nowrap"><input type="checkbox"
                                                                           id="filter_horizontal"><?= _e( 'Horizontal', 'soup_pixabay_images' ); ?>
                </label>
                <label style="margin-right:25px;white-space:nowrap"><input type="checkbox"
                                                                           id="filter_vertical"><?= _e( 'Vertical', 'soup_pixabay_images' ); ?>
                </label>
                <a id="soup_pixabay_settings_icon" href="options-general.php?page=soup_pixabay_images_settings"
                   target="_blank"><img style="position:relative;top:5px"
                                        src="<?= plugin_dir_url( __FILE__ ) . 'img/settings.png' ?>"
                                        title="<?= _e( 'Settings', 'soup_pixabay_images' ); ?>"></a>
            </div>
                */ ?>
        </form>
        <div id="soup_pixabay_results" class="flex-images"
             style="margin-top:20px;padding-top:25px;border-top:1px solid #ddd"></div>
    </div>
    <script>
        // flexImages
        !function (t) {
            function e(t, a, r, n) {
                function o(t) {
                    r.maxRows && d > r.maxRows || r.truncate && t && d > 1 ? w[g][0].style.display = "none" : (w[g][4] && (w[g][3].attr("src", w[g][4]), w[g][4] = ""), w[g][0].style.width = l + "px", w[g][0].style.height = u + "px", w[g][0].style.display = "block")
                }

                var g, l, s = 1, d = 1, f = t.width() - 2, w = [], c = 0, u = r.rowHeight;
                for (f || (f = t.width() - 2), i = 0; i < a.length; i++)if (w.push(a[i]), c += a[i][2] + r.margin, c >= f) {
                    var m = w.length * r.margin;
                    for (s = (f - m) / (c - m), u = Math.ceil(r.rowHeight * s), exact_w = 0, l, g = 0; g < w.length; g++)l = Math.ceil(w[g][2] * s), exact_w += l + r.margin, exact_w > f && (l -= exact_w - f), o();
                    w = [], c = 0, d++
                }
                for (g = 0; g < w.length; g++)l = Math.floor(w[g][2] * s), h = Math.floor(r.rowHeight * s), o(!0);
                n || f == t.width() || e(t, a, r, !0)
            }

            t.fn.flexImages = function (a) {
                var i = t.extend({container: ".item", object: "img", rowHeight: 180, maxRows: 0, truncate: 0}, a);
                return this.each(function () {
                    var a = t(this), r = t(a).find(i.container), n = [], o = (new Date).getTime(),
                        h = window.getComputedStyle ? getComputedStyle(r[0], null) : r[0].currentStyle;
                    for (i.margin = (parseInt(h.marginLeft) || 0) + (parseInt(h.marginRight) || 0) + (Math.round(parseFloat(h.borderLeftWidth)) || 0) + (Math.round(parseFloat(h.borderRightWidth)) || 0), j = 0; j < r.length; j++) {
                        var g = r[j], l = parseInt(g.getAttribute("data-w")),
                            s = l * (i.rowHeight / parseInt(g.getAttribute("data-h"))), d = t(g).find(i.object);
                        n.push([g, l, s, d, d.data("src")])
                    }
                    e(a, n, i), t(window).off("resize.flexImages" + a.data("flex-t")), t(window).on("resize.flexImages" + o, function () {
                        e(a, n, i)
                    }), a.data("flex-t", o)
                })
            }
            // VacationSoup specific, pre-fill the query box on load
            if (parent && parent.vs_query !== undefined) {
                var query = parent.vs_query.join(' OR ');

                // Pixabay has a 100 char limit on queries
                while (query.length > 99){
                    var lastIndex = query.lastIndexOf(' OR ');
                    query = query.substring(0, lastIndex);
                }
                jQuery('#q').val(query);
                call_api(query,1)
            }
            // End vacationSoup specific
        }(jQuery);
        function getCookie(k) {
            return (document.cookie.match('(^|; )' + k + '=([^;]*)') || 0)[2]
        }
        function setCookie(n, v, d, s) {
            var o = new Date;
            o.setTime(o.getTime() + 864e5 * d + 1000 * (s || 0)), document.cookie = n + "=" + v + ";path=/;expires=" + o.toGMTString()
        }
        function escapejs(s) {
            return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, "\\'");
        }

        // set checkbox filters
        jQuery("input[id^='filter_']").each(function () {
            if (getCookie('px_' + this.id) != '0') this.checked = true;
            jQuery(this).change(function () {
                setCookie('px_' + this.id, this.checked ? 1 : 0, 365);
            });
        });

        var post_id = <?=absint( $_REQUEST['post_id'] ) ?>,
            lang = '<?= $soup_pixabay_images_settings['language'] ? $soup_pixabay_images_settings['language'] : substr( get_locale(), 0, 2 ) ?>',
            per_page = 50, form = jQuery('#soup_pixabay_images_form'), hits, q, image_type, orientation;


        form.submit(function (e) {
            e.preventDefault();
            q = jQuery('#q', form).val();
            /** if (jQuery('#filter_photos', form).is(':checked') && !jQuery('#filter_cliparts', form).is(':checked')) image_type = 'photo';
            else if (!jQuery('#filter_photos', form).is(':checked') && jQuery('#filter_cliparts', form).is(':checked')) image_type = 'clipart';
            else image_type = 'all';
            if (jQuery('#filter_horizontal', form).is(':checked') && !jQuery('#filter_vertical', form).is(':checked')) orientation = 'horizontal';
            else if (!jQuery('#filter_horizontal', form).is(':checked') && jQuery('#filter_vertical', form).is(':checked')) orientation = 'vertical';
            else orientation = 'all'; */
            jQuery('#soup_pixabay_results').html('');
            image_type = 'photo'; // Override for Vacation Soup
            orientation = 'horizontal'; // Override for Vacation Soup
            call_api(q, 1);
        });

        function call_api(q, p) {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'https://pixabay.com/api/?key=8496593-6b701fe76874be81ba16ace4e&response_group=high_resolution&lang=' + lang + '&image_type=photo&orientation=horizontal&per_page=' + per_page + '&page=' + p + '&search_term=' + encodeURIComponent(q));
            xhr.onreadystatechange = function () {
                if (this.status == 200 && this.readyState == 4) {
                    var data = JSON.parse(this.responseText);
                    if (!(data.totalHits > 0)) {
                        jQuery('#soup_pixabay_results').html('<div style="color:#bbb;font-size:24px;text-align:center;margin:40px 0">—— <?= _e( 'No matches', 'soup_pixabay_images' ) ?> ——</div>');
                        return false;
                    } else {
                        // Randomly sort the images - VacationSoup
                        for (var i = data.hits.length - 1; i > 0; i--) {
                            var j = Math.floor(Math.random() * (i + 1));
                            var temp = data.hits[i];
                            data.hits[i] = data.hits[j];
                            data.hits[j] = temp;
                        }
                    }
                    render_px_results(q, p, data);
                }
            };
            xhr.send();
            return false;
        }

        function render_px_results(q, p, data) {
            hits = data['hits']; // store for upload click
            pages = Math.ceil(data.totalHits / per_page);
            var s = '';
            jQuery.each(data.hits, function (k, v) {
                s += '<div class="item upload" data-url="' + v.largeImageURL + '" data-user="' + v.user + '" data-w="' + v.webformatWidth + '" data-h="' + v.webformatHeight + '"><img src="' + v.webformatURL + '"><div class="download"><img src="<?= plugin_dir_url( __FILE__ ) . 'img/download.svg' ?>"><div>' + (v.webformatWidth * 2) + '×' + (v.webformatHeight * 2) + '<br><a href="https://pixabay.com/users/' + v.user + '/" target="_blank"">' + v.user + '</a> @ <a href="https://pixabay.com/' + lang + '/photos/?order=popular&image_type=' + image_type + '&orientation=' + orientation + '&q=' + escapejs(q) + '" target="_blank">Pixabay</a></div></div></div>';
            });
            jQuery('#soup_pixabay_results').html(jQuery('#soup_pixabay_results').html() + s);
            jQuery('#load_animation').remove();
            if (p < pages) {
                jQuery('#soup_pixabay_results').after('<div id="load_animation" style="clear:both;padding:15px 0 0;text-align:center"><img style="width:60px" src="<?= plugin_dir_url( __FILE__ ) . 'img/loading.svg' ?>"></div>');
                jQuery(window).scroll(function () {
                    if (jQuery(window).scrollTop() + jQuery(window).height() > jQuery(document).height() - 400) {
                        jQuery(window).off('scroll');
                        call_api(q, p + 1);
                    }
                });
            }

            jQuery('.flex-images').flexImages({rowHeight: 260});
        }

        var motdClicked = parent.motdClicked;


        var handleLightbox = function () {
            jQuery(document).off('click', '.upload');
            // loading animation
            jQuery(this).addClass('uploading').find('.download img').replaceWith('<img src="<?= plugin_dir_url( __FILE__ ) . 'img/loading.svg' ?>" style="height:80px !important">');
            jQuery.post('.', {
                    soup_pixabay_upload: "1",
                    image_url: jQuery(this).data('url'),
                    image_user: jQuery(this).data('user'),
                    vs_title: parent.vs_title,
                    vs_query: parent.vs_query,
                    wpnonce: '<?= wp_create_nonce( 'soup_pixabay_images_security_nonce' ); ?>'
                }, function (response) {
                    if (response.error === undefined) {
                        if (parent.jQuery('#featured_image').val()>0 && !motdClicked) {
                            window.location = 'media-upload.php?type=image&tab=library&post_id=' + post_id + '&attachment_id=' + response.id;
                        } else {
                            parent.jQuery('#featured_image')
                                .val(response.id);
                            parent.jQuery('#motd')
                                .html(
                                '<h1>Featured Image</h1>'+response.html
                            );
                            parent.doAutosave();
                            parent.tb_remove();
                        }
                    }
                    else alert('Oops:\n' + response.error.message);
                },
                'json');
            return false;
        };
        jQuery(document).on('click', '.upload', handleLightbox);
    </script>
	<?php
}

function media_upload_pixabaytab_handler() {
	wp_iframe( 'media_soup_pixabay_images_tab' );
}

add_action( 'media_upload_pixabaytab', 'media_upload_pixabaytab_handler' );


if ( isset( $_POST['soup_pixabay_upload'] ) ) {
	# "pluggable.php" is required for wp_verify_nonce() and other upload related helpers
	if ( ! function_exists( 'wp_verify_nonce' ) ) {
		require_once( ABSPATH . 'wp-includes/pluggable.php' );
	}
	try {

        $nonce = $_POST['wpnonce'];
        if ( ! wp_verify_nonce( $nonce, 'soup_pixabay_images_security_nonce' ) ) {
	        throw new Exception( 'Invalid request' );
        }

        $post_id                 = absint( $_REQUEST['post_id'] );
        $soup_pixabay_images_settings = get_option( 'soup_pixabay_images_options' );

        // parse image_url
        $url        = str_replace( 'https:', 'http:', $_POST['image_url'] );
        $parsed_url = parse_url( $url );
        if ( strcmp( $parsed_url['host'], 'pixabay.com' ) ) {
	        throw new Exception( 'Invalid configuration (must be pixabay.com)' );
        }

        // get image file
        $response = wp_remote_get( $url );
        if ( is_wp_error( $response ) ) {
	        throw new Exception( $response->get_error_message() );
        }

        $path_info = pathinfo( $url );
        $file_name = sanitize_file_name( str_replace( " ", "_", $_POST['vs_title'] ) . '_' . time() . '.' . $path_info['extension'] );

        $wp_upload_dir     = wp_upload_dir();
        $image_upload_path = $wp_upload_dir['path'];

        if ( ! is_dir( $image_upload_path ) ) {
            if ( ! @mkdir( $image_upload_path, 0777, true ) ) {
                throw new Exception( 'Failed to create upload folder ' . $image_upload_path );
            }
        }

        $target_file_name = $image_upload_path . '/' . $file_name;
        $result           = @file_put_contents( $target_file_name, $response['body'] );
        unset( $response['body'] );
        if ( $result === false ) {
            throw new Exception( 'Failed to write file ' . $target_file_name );
        }

        // are we dealing with an image
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        if ( ! wp_read_image_metadata( $target_file_name ) ) {
            unlink( $target_file_name );
            throw new Exception( 'File is not an image.' );
        }

        $image_title        =  $_POST['vs_title'] ;
        $attachment_caption = '';
        if ( ! $soup_pixabay_images_settings['attribution'] | $soup_pixabay_images_settings['attribution'] == 'true' ) {
            $attachment_caption = '<a href="https://pixabay.com/users/' . htmlentities( $_POST['image_user'] ) . '/">' . htmlentities( $_POST['image_user'] ) . '</a> / Pixabay';
        }

        // insert attachment
        $wp_filetype = wp_check_filetype( basename( $target_file_name ), null );
        $attachment  = array(
            'guid'           => $wp_upload_dir['url'] . '/' . basename( $target_file_name ),
            'post_mime_type' => $wp_filetype['type'],
            'post_title'     => preg_replace( '/\.[^.]+$/', '', $image_title ),
            'post_status'    => 'inherit'
        );

        // FW This bit needs a post_ID and we don't ave one, may need re-coding TODO
        $attach_id   = wp_insert_attachment( $attachment, $target_file_name, $post_id );
        if ( $attach_id == 0 ) {
	        throw new Exception( 'File attachment error' );
        }

        $attach_data = wp_generate_attachment_metadata( $attach_id, $target_file_name );
        $result      = wp_update_attachment_metadata( $attach_id, $attach_data );
        if ( $result === false ) {
            throw new Exception( 'File attachment metadata error' );
        }

        $image_data                 = array();
        $image_data['ID']           = $attach_id;
        $image_data['post_excerpt'] = $attachment_caption;
        wp_update_post( $image_data );

	    $response = [
		    'id' => $attach_id,
		    'html' => wp_get_attachment_image($attach_id,[600,500])
	    ];

    } catch (Exception $e) {
        $response = [
                'error' => [
                        'message' => $e->getMessage(),
                        'code' => $e->getCode()
                ]
        ];
    }

	header("Content-type: application/json");
	echo json_encode($response);
	exit;
}

?>
