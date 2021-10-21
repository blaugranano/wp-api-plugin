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
* Define plugin constants
*/

const BG__REST_NAMESPACE = 'bg/v3';

/**
* Define helper functions
*/

function bg__render_post($post) {
  return $post;
}

/**
* Define plugin functions
*/

function bg__menus($req) {
  $menu_id = $req->get_param('menu_id');

  return wp_get_nav_menu_items($menu_id);
}

function bg__posts($req) {
  $posts = get_posts([
    'category_name' => $req->get_param('post_category') ?: NULL,
    'p' => $req->get_param('post_id') ?: NULL,
    'name' => $req->get_param('post_name') ?: NULL,
    'offset' => $req->get_param('offset') ?: 0,
    'page_id' => $req->get_param('page_id') ?: NULL,
    'pagename' => $req->get_param('page_name') ?: NULL,
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
    'page_id' => [
      'sanitize_callback' => function ($value, $req, $key) {
        return intval($value);
      },
    ],
    'page_name' => [
      'sanitize_callback' => function ($value, $req, $key) {
        return sanitize_text_field($value);
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
    'post_name' => [
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
