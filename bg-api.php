<?php

/**
* Plugin Name: Blaugrana API
* Plugin URI: https://github.com/blaugranano/wp-api-plugin
* Description: This WordPress plugin adds custom endpoints to the WordPress REST API.
* Version: 3.0.0
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

const BG__IMAGE_PATH = 'https://wp.blgr.app/wp-content/uploads';
const BG__IMAGE_SIZE = 'large';
const BG__REST_NAMESPACE = 'bg/v3';

/**
* Define helper functions
*/

function bg__filter($str) {
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

function bg__get_author($post_id) {
  $post_object = get_post($post_id);
  $author_id = $post_object->post_author;
  $author_firstname = get_the_author_meta('first_name', $author_id);
  $author_lastname = get_the_author_meta('last_name', $author_id);

  return trim("{$author_firstname} {$author_lastname}");
}

function bg__get_category($post_id) {
  $category_object = get_the_category($post_id);
  
  return $category_object[0]->name;
}

function bg__get_content($post_id) {
  $post_object = get_post($post_id);
  $post_content = $post_object->post_content;
  $has_blocks = preg_match('/wp:paragraph/', $post_content);

  if (!$has_blocks) {
    $post_content = preg_replace("/\\n/", "\n\n", $post_content);
  }

  return bg__filter(apply_filters('the_content', $post_content));
}

function bg__get_date($post_id) {
  $post_object = get_post($post_id);
  $timestamp = strtotime($post_object->post_date);

  return strftime('%-d. %B %Y kl. %H.%M', $timestamp);
}

function bg__get_image($post_id) {
  if (has_post_thumbnail($post_id)) {
    $image_id = get_post_thumbnail_id($post_id);
    $image_object = wp_get_attachment_image_src($image_id, BG__IMAGE_SIZE);
    $image_basename = basename($image_object[0]);

    return BG__IMAGE_PATH . "/{$image_basename}";
  }

  return false;
}

function bg__get_slug($post_id) {
  $post_object = get_post($post_id);
  $category_object = get_the_category($post_id);

  return "{$category[0]->slug}/{$post_id}/{$post_object->post_name}";
}

function bg__get_title($post_id) {
  $post_object = get_post($post_id);
  $title = $post_object->post_title;

  return bg__filter(apply_filters('the_title', $title));
}

function bg__render_page($ref) {
  $post_id = $ref->ID;
  $post_object = get_post($post_id);
  $page_object = [
    'post_content' => bg__get_content($post_id),
    'post_date' => bg__get_date($post_id),
    'post_id' => $post_id,
    'post_image' => bg__get_image($post_id),
    'post_slug' => "/{$post_object->post_name}",
    'post_title' => bg__get_title($post_id),
  ];

  return $page_object;
}

function bg__render_post($ref) {
  $post_id = $ref->ID;
  $post_category = bg__get_category($post_id);
  $post_object = [
    'post_author' => bg__get_author($post_id),
    'post_category' => $post_category,
    'post_content' => bg__get_content($post_id),
    'post_date' => bg__get_date($post_id),
    'post_id' => $post_id,
    'post_image' => bg__get_image($post_id),
    'post_slug' => bg__get_slug($post_id),
    'post_title' => bg__get_title($post_id),
  ];

  return $post_object;
}

/**
* Define plugin functions
*/

function bg__menus($req) {
  $menu_id = $req->get_param('menu_id');

  return wp_get_nav_menu_items($menu_id);
}

function bg__pages($req) {
  $pages = get_posts([
    'p' => $req->get_param('page_id') ?: NULL,
    'name' => $req->get_param('page_slug') ?: NULL,
    'post_type' => 'page',
  ]);

  return array_map('bg__render_page', $pages);
}

function bg__posts($req) {
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

  return array_map('bg__render_post', $posts);
}

/**
* Initiate plugin
*/

function bg__register_rest_route($namespace, $route, $fn, $args = []) {
  return register_rest_route($namespace, $route, [
    'methods' => 'GET',
    'callback' => $fn,
    'args' => $args,
  ]);
}

function bg__init() {
  // Register menus endpoint
  bg__register_rest_route(BG__REST_NAMESPACE, '/menus', 'bg__menus', [
    'menu_id' => [
      'sanitize_callback' => function ($value, $req, $key) {
        return intval($value);
      },
    ],
  ]);

  // Register pages endpoint
  bg__register_rest_route(BG__REST_NAMESPACE, '/pages', 'bg__pages', [
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
  bg__register_rest_route(BG__REST_NAMESPACE, '/posts', 'bg__posts', [
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

add_action('rest_api_init', 'bg__init');
