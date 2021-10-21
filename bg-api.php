<?php

/**
* Plugin Name: Blaugrana API
* Plugin URI: https://github.com/blaugranano/wp-api-plugin
* Description: This WordPress plugin adds custom endpoints to the WordPress REST API.
* Version: 4.0.0
* Author: Blaugrana
* Author URI: https://github.com/blaugranano
*/

/**
* Set locale
*/

setlocale(LC_ALL, 'nb_NO.utf8');

/**
* Define plugin constants
*/

const BGv4__IMAGE_PATH = 'https://wp.blgr.app/wp-content/uploads';
const BGv4__IMAGE_SIZE = 'large';
const BGv4__REST_NAMESPACE = 'bg/v4';

/**
* Define helper functions
*/

function bgv4__filter($str) {
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
    '/\.\./' => ' ',
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

function bgv4__get_author($post_id) {
  $post_object = get_post($post_id);
  $author_id = $post_object->post_author;
  $author_firstname = get_the_author_meta('first_name', $author_id);
  $author_lastname = get_the_author_meta('last_name', $author_id);

  return trim("{$author_firstname} {$author_lastname}");
}

function bgv4__get_category($post_id) {
  $category_object = get_the_category($post_id);
  
  return $category_object[0]->name;
}

function bgv4__get_content($post_id) {
  $post_object = get_post($post_id);
  $post_content = $post_object->post_content;
  $has_blocks = preg_match('/wp:paragraph/', $post_content);

  if (!$has_blocks) {
    $post_content = preg_replace("/\\n/", "\n\n", $post_content);
  }

  return bgv4__filter(apply_filters('the_content', $post_content));
}

function bgv4__get_date($post_id) {
  $post_object = get_post($post_id);
  $timestamp = strtotime($post_object->post_date);

  return strftime('%-d. %B %Y kl. %H.%M', $timestamp);
}

function bgv4__get_image($post_id) {
  if (has_post_thumbnail($post_id)) {
    $image_id = get_post_thumbnail_id($post_id);
    $image_object = wp_get_attachment_image_src($image_id, BGv4__IMAGE_SIZE);
    $image_basename = basename($image_object[0]);

    return BGv4__IMAGE_PATH . "/{$image_basename}";
  }

  return false;
}

function bgv4__get_slug($post_id) {
  $post_object = get_post($post_id);
  $category_object = get_the_category($post_id);

  return "/{$category_object[0]->slug}/{$post_id}/{$post_object->post_name}";
}

function bgv4__get_title($post_id) {
  $post_object = get_post($post_id);
  $title = $post_object->post_title;

  return bgv4__filter(apply_filters('the_title', $title));
}

function bgv4__get_next_post($post_id) {
  global $post;

  $post = get_post($post_id);
  setup_postdata($post);
  $post_object = get_next_post();
  $post_id = $post_object->ID;
  wp_reset_postdata();

  return [
    'post_category' => bgv4__get_category($post_id),
    'post_id' => $post_id,
    'post_image' => bgv4__get_image($post_id),
    'post_slug' => bgv4__get_slug($post_id),
    'post_title' => bgv4__get_title($post_id),
  ];
}

function bgv4__get_previous_post($post_id) {
  global $post;

  $post = get_post($post_id);
  setup_postdata($post);
  $post_object = get_previous_post();
  $post_id = $post_object->ID;
  wp_reset_postdata();

  return [
    'post_category' => bgv4__get_category($post_id),
    'post_id' => $post_id,
    'post_image' => bgv4__get_image($post_id),
    'post_slug' => bgv4__get_slug($post_id),
    'post_title' => bgv4__get_title($post_id),
  ];
}

function bgv4__render_page($ref) {
  $post_id = $ref->ID;
  $post_object = get_post($post_id);
  $page_object = [
    'post_content' => bgv4__get_content($post_id),
    'post_date' => bgv4__get_date($post_id),
    'post_id' => $post_id,
    'post_image' => bgv4__get_image($post_id),
    'post_slug' => "/{$post_object->post_name}",
    'post_title' => bgv4__get_title($post_id),
  ];

  return $page_object;
}

function bgv4__render_post($ref) {
  $post_id = $ref->ID;
  $post_object = [
    'post_author' => bgv4__get_author($post_id),
    'post_category' => bgv4__get_category($post_id),
    'post_content' => bgv4__get_content($post_id),
    'post_date' => bgv4__get_date($post_id),
    'post_id' => $post_id,
    'post_image' => bgv4__get_image($post_id),
    'post_slug' => bgv4__get_slug($post_id),
    'post_title' => bgv4__get_title($post_id),
    'next_post' => bgv4__get_next_post($post_id),
    'previous_post' => bgv4__get_previous_post($post_id),
  ];

  return $post_object;
}

/**
* Define plugin functions
*/

function bgv4__menus($req) {
  $menu_id = $req->get_param('menu_id');

  return wp_get_nav_menu_items($menu_id);
}

function bgv4__pages($req) {
  $pages = get_posts([
    'p' => $req->get_param('page_id') ?: NULL,
    'name' => $req->get_param('page_slug') ?: NULL,
    'post_type' => 'page',
  ]);

  return array_map('bgv4__render_page', $pages);
}

function bgv4__posts($req) {
  $posts = get_posts([
    'category_name' => $req->get_param('post_category') ?: NULL,
    'p' => $req->get_param('post_id') ?: NULL,
    'name' => $req->get_param('post_slug') ?: NULL,
    'offset' => $req->get_param('offset') ?: 0,
    'post_type' => 'post',
    'post_status' => $req->get_param('post_status') ?: 'publish',
    'posts_per_page' => $req->get_param('limit') ?: 1,
    's' => $req->get_param('search') ?: NULL,
  ]);

  return array_map('bgv4__render_post', $posts);
}

/**
* Initiate plugin
*/

function bgv4__register_rest_route($namespace, $route, $fn, $args = []) {
  return register_rest_route($namespace, $route, [
    'methods' => 'GET',
    'callback' => $fn,
    'args' => $args,
  ]);
}

function bgv4__init() {
  // Register menus endpoint
  bgv4__register_rest_route(BGv4__REST_NAMESPACE, '/menus(|/(?P<menu_id>\d+))', 'bgv4__menus', [
    'menu_id' => [
      'sanitize_callback' => function ($value, $req, $key) {
        return intval($value);
      },
    ],
  ]);

  // Register pages endpoint
  bgv4__register_rest_route(BGv4__REST_NAMESPACE, '/pages(|/(?P<page_id>\d+))', 'bgv4__pages', [
    'page_id' => [
      'sanitize_callback' => function ($value, $req, $key) {
        return intval($value);
      },
    ],
    'page_slug' => [
      'sanitize_callback' => function ($value, $req, $key) {
        return sanitize_text_field($value);
      },
    ],
  ]);

  // Register posts endpoint
  bgv4__register_rest_route(BGv4__REST_NAMESPACE, '/posts(|/(?P<post_id>\d+))', 'bgv4__posts', [
    'limit' => [
      'sanitize_callback' => function ($value, $req, $key) {
        return intval($value);
      },
    ],
    'offset' => [
      'sanitize_callback' => function ($value, $req, $key) {
        return intval($value);
      },
    ],
    'post_category' => [
      'sanitize_callback' => function ($value, $req, $key) {
        return sanitize_text_field($value);
      },
    ],
    'post_id' => [
      'sanitize_callback' => function ($value, $req, $key) {
        return intval($value);
      },
    ],
    'post_slug' => [
      'sanitize_callback' => function ($value, $req, $key) {
        return sanitize_text_field($value);
      },
    ],
    'post_status' => [
      'validate_callback' => function ($value, $req, $key) {
        $valid_values = [
          'draft',
          'future',
          'pending',
          'preview',
          'private',
          'publish',
        ];

        if (in_array($value, $valid_values)) {
          return true;
        }

        return false;
      },
    ],
    'search' => [
      'sanitize_callback' => function ($value, $req, $key) {
        return sanitize_text_field($value);
      },
    ],
  ]);
}

add_action('rest_api_init', 'bgv4__init');
