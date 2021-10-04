<?php

/**
* Plugin Name: Blaugrana API
* Plugin URI: https://github.com/blaugranano/wp-api-plugin
* Description: This WordPress plugin adds additional data to the WordPress REST API output.
* Version: 2.0.0
* Author: Blaugrana
* Author URI: https://github.com/blaugranano
*/

/**
* Define helper functions
*/

function bg_filter($str) {
  $filter = [
    '/&#8211;/' => '–',
    '/&#8212;/' => '–',
    '/&#8216;/' => '‘',
    '/&#8217;/' => '’',
    '/&#8220;/' => '«',
    '/&#8221;/' => '»',
    '/&ldquo;/' => '«',
    '/&lsquo;/' => '‘',
    '/&mdash;/' => '–',
    '/&rdquo;/' => '»',
    '/&rsquo;/' => '’',
    '/--/' => '–',
    '/\.\./' => ' ',
    '/barca(?=([^\"]*\"[^\"]*\")*[^\"]*$)/i' => 'Barça',
    '/ҫ/i' => 'ç',
    '/—/' => '–',
    '/“/' => '«',
    '/”/' => '»',
    '/<!–/' => '<!--',
    '/–>/' => '-->',
    '/Referat\/Vurderinger/i' => 'Referat',
  ];

  return preg_replace(array_keys($filter), array_values($filter), $str);
}

function bg_get_post($post) {
  global $post;

  $post_id = $post->ID;

  return [
    'wp_category' => bg_get_category($post),
    'wp_id' => $post_id,
    'wp_image' => bg_get_image($post),
    'wp_slug' => bg_get_slug($post),
    'wp_tags' => bg_get_tags($post),
    'wp_title' => bg_get_title($post),
  ];
}

/**
* Define plugin function
*/

function bg_get_adjacent($post) {
  global $post;

  $next_post = get_next_post();
  $previous_post = get_previous_post();

  return [
    'next' => bg_get_post($next_post),
    'previous' => bg_get_post($previous_post),
  ];
}

function bg_get_author($post) {
  global $post;

  $post_id = $post->ID;
  $post_object = get_post($post_id);
  $author_id = $post_object->post_author;
  $first_name = get_the_author_meta('first_name', $author_id);
  $last_name = get_the_author_meta('last_name', $author_id);

  return [
    'id' => $author_id,
    'first_name' => $first_name,
    'last_name' => $last_name,
    'name' => trim("{$first_name} {$last_name}"),
  ];
}

function bg_get_category($post) {
  global $post;

  $post_id = $post->ID;
  $category = get_the_category($post_id);

  return [
    'id' => $category[0]->term_id,
    'slug' => $category[0]->slug,
    'name' => $category[0]->name,
  ];
}

function bg_get_content($post) {
  global $post;

  $post_id = $post->ID;
  $post_object = get_post($post_id);
  $post_content = $post_object->post_content;
  $has_blocks = preg_match('/wp:paragraph/', $post_content);

  if (!$has_blocks) {
    $post_content = preg_replace("/\\n/", "\n\n", $post_content);
  }

  return [
    'raw' => bg_filter($post_content),
    'rendered' => bg_filter(apply_filters('the_content', $post_content)),
  ];
}

function bg_get_image($post) {
  global $post;

  $post_id = $post->ID;

  if (has_post_thumbnail($post_id)) {
    $image_id = get_post_thumbnail_id($post_id);
    $image_fields = function($image_id, $size) {
      $result = wp_get_attachment_image_src($image_id, $size);

      return [
        'src' => basename($result[0]),
        'width' => $result[1],
        'height' => $result[2],
      ];
    };
    $image_object = [
      'id' => $image_id,
      'small' => $image_fields($image_id, 'small'),
      'medium' => $image_fields($image_id, 'medium'),
      'large' => $image_fields($image_id, 'large'),
      'full' => $image_fields($image_id, 'full'),
    ];
    
    return $image_object;
  }

  return false;
}

function bg_get_slug($post) {
  global $post;

  $post_id = $post->ID;
  $post_object = get_post($post_id);
  $category = bg_get_category($post);
  $slug = $post_object->post_name;

  return "{$category['slug']}/{$post_id}/{$slug}";
}

function bg_get_status($post) {
  global $post;

  $post_id = $post->ID;
  $post_object = get_post($post_id);
  $status = $post_object->post_status;

  return $status;
}

function bg_get_tags($post) {
  global $post;

  $post_id = $post->ID;
  $tags = [
    'legacy' => has_tag('legacy', $post_id),
    'plus' => has_tag('plus', $post_id),
  ];

  return $tags;
}

function bg_get_timestamp($post) {
  global $post;

  $post_id = $post->ID;
  $post_object = get_post($post_id);
  $time = strtotime($post_object->post_date);

  return [
    'raw' => $time,
    'rendered' => strftime('%-d. %B %Y kl. %H.%M', $time),
  ];
}

function bg_get_title($post) {
  global $post;

  $post_id = $post->ID;
  $post_object = get_post($post_id);
  $title = $post_object->post_title;

  return [
    'raw' => bg_filter($title),
    'rendered' => bg_filter(apply_filters('the_title', $title)),
  ];
}

function bg_get_type($post) {
  global $post;
  
  $post_id = $post->ID;
  $post_object = get_post($post_id);
  $type = $post_object->post_type;

  return $type;
}

/**
* Initiate plugin
*/
function bg_register_field($field, $fn) {
  $callback = [
    'get_callback' => $fn,
    'update_callback' => null,
    'schema' => null,
  ];

  return register_rest_field('post', $field, $callback);
}

function bg_init() {
  bg_register_field('wp_adjacent', 'bg_get_adjacent');
  bg_register_field('wp_author', 'bg_get_author');
  bg_register_field('wp_category', 'bg_get_category');
  bg_register_field('wp_content', 'bg_get_content');
  bg_register_field('wp_image', 'bg_get_image');
  bg_register_field('wp_slug', 'bg_get_slug');
  bg_register_field('wp_status', 'bg_get_status');
  bg_register_field('wp_tags', 'bg_get_tags');
  bg_register_field('wp_timestamp', 'bg_get_timestamp');
  bg_register_field('wp_title', 'bg_get_title');
  bg_register_field('wp_type', 'bg_get_type');
}

/**
* Register plugin
*/
add_action('rest_api_init', 'bg_init');
