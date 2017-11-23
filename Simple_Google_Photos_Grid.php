<?php

/**
 *
 */
class Simple_Google_Photos_Grid
{
  /**
   * Default length (in minutes) to cache the photo urls retrieved from Google
   */
  const CACHE_INTERVAL = 15;

  /**
   * Default number of photos to display in the widget
   */
  const NUMBER_PHOTOS = 4;

  public function html($photos, $num_photos_to_show, $link_url) {

    $container_class = self::name();
    $cell_class = self::name() . '-cell';
    $image_class = self::name() . '-image';

    $html = '<style>' . $this->widget_css() . '</style>';
    $html .= '<div id="' . $container_class . '">';
    foreach(array_slice($photos, 0, $num_photos_to_show) as $i => $photo) {
      $html .= '<div class="'.$cell_class . '">';
      $html .= '<a href="' . $link_url . '" target="_blank"><img src="' . $photo . '" alt="Google Photo" class="' . $image_class . '"></a>';
      $html .= '</div>';
    }
    $html .= '</div>';
    $html .= '<script>' . $this->widget_js() . '</script>';

    return $html;
  }

  /**
   * Retrieve photos from cache or from google
   *
   * @param string $album_url A google photos album short or long url
   * @param integer $cache_interval Length in minutes to cache (0) for no cache
   *
   * @return array
   */
  public function get_photos($album_url, $cache_interval) {
    $album = get_option($this->album_option_name($album_url));
    if($album &&
      (isset($album['photos']) && !empty($album['photos'])) &&
      (isset($album['cache-time']) && ($album['cache-time'] + ($cache_interval * 60) > time()))) {
      $photos = $album['photos'];
    }
    else {
      $photos = $this->get_photos_from_google($album_url);
      if($cache_interval) {
        $this->cache_album($album_url, $photos);
      }
    }
    return $photos;
  }

  /**
   * Hackety-hack way to retrieve photos from a public album since google has no working api for google photos
   * Read: https://kunnas.com/google-photos-is-a-disaster/
   * And: https://productforums.google.com/forum/#!topic/photos/WuqfNazcqh4
   *
   * @param $album_url
   *
   * @return array
   */
  protected function get_photos_from_google($album_url) {
    $photos = [];
    $response = wp_remote_get( $album_url );
    if ( !is_wp_error( $response ) ) {
      $body = $response['body'];
      preg_match_all('@\["AF1Q.*?",\["(.*?)"\,@', $body, $urls);
      if(isset($urls[1])) $photos = $urls[1];
    }
    return $photos;
  }

  /**
   * A unique name for the widget option, per album
   *
   * @param $album_url
   *
   * @return string
   */
  protected function album_option_name($album_url) {
    return self::name() . '-' . md5($album_url);
  }

  /**
   * "Cache" the album urls in the options table
   * @param $album_url
   * @param $photos
   */
  public function cache_album($album_url, $photos) {
    $option_value = [
      'cache-time' => time(),
      'photos' => $photos
    ];
    add_option($this->album_option_name($album_url), $option_value);
  }

  /**
   * Style block CSS for the widget, why not?
   *
   * @return string
   */
  protected function widget_css() {
    $container_class = self::name();
    $cell_class = self::name() . '-cell';
    $image_class = self::name() . '-image';

    return <<<EOD
      div#{$container_class} {
        width:100%;
        height:100%;
        overflow:hidden;      
      }
      div.{$cell_class} {
        box-sizing:border-box;
        padding:5px;
        float:left;
        width:50%;
      }
      img.{$image_class} {
        object-fit: cover;
      }
EOD;
  }

  /**
   * Script block js for the widget, why not?
   *
   * @return string
   */
  protected function widget_js() {

    $cell_class = self::name() . '-cell';
    $image_class = self::name() . '-image';

    return <<<EOD
      (function() {
        if( window.jQuery ){
          var width = jQuery("div.{$cell_class}").first().width();
          jQuery("img.{$image_class}").css("width", width).css("height", width);
        }
      })();
EOD;
  }

  /**
   * Hook to run when uninstalling the plugin
   */
  public static function uninstall() {
    global $wpdb;

    $wpdb->query(
      "DELETE FROM $wpdb->options  WHERE `option_name` LIKE '%".self::name()."%'"
    );
  }

  /**
   * Used frequently
   *
   * @return string
   */
  public static function name() {
    return basename(__DIR__);
  }
}