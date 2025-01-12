<?php
/**
* Plugin Name: Custom Mobile REST API
* Description: Adds custom REST API endpoints for fetching and filtering posts.
* Version: 1.0
* Author: Your Name
*/

// Hook into the REST API initialization
add_action('rest_api_init', function () {
  // Register the GET route for fetching posts
  register_rest_route('cv/v1', '/get-posts', [
    'methods'  => 'GET',
    'callback' => 'cv_handle_get_posts',
    'permission_callback' => '__return_true',
  ]);

  // Register the POST route for filtering posts
  register_rest_route('custom/v1', '/filter-posts', [
    'methods'  => 'POST',
    'callback' => 'custom_filter_posts',
    'permission_callback' => function () {
      return current_user_can('edit_posts'); // Use appropriate permission check
    },
  ]);
});

/**
* Handles the GET request for fetching posts.
*
* @param WP_REST_Request $request
* @return WP_REST_Response
*/
function cv_handle_get_posts($request) {
  $parameters = $request->get_params();
  $posts_per_page = isset($parameters['per_page']) ? intval($parameters['per_page']) : 10;
  $page = isset($parameters['page']) ? intval($parameters['page']) : 1;

  $query = new WP_Query([
    'post_type' => 'post',
    'posts_per_page' => $posts_per_page,
    'paged' => $page,
    'post_status' => 'publish',
  ]);

  if (!$query->have_posts()) {
    return rest_ensure_response(['success' => false, 'message' => 'No posts found.']);
  }

  $posts = [];
  while ($query->have_posts()) {
    $query->the_post();
    $posts[] = [
      'title' => get_the_title(),
      'link' => get_permalink(),
      'image' => has_post_thumbnail() ? get_the_post_thumbnail_url() : '',
    ];
  }
  wp_reset_postdata();

  return rest_ensure_response(['success' => true, 'data' => $posts]);
}

/**
* Handles the POST request for filtering posts.
*
* @param WP_REST_Request $request
* @return WP_REST_Response
*/
function custom_filter_posts($request) {
  $parameters = $request->get_json_params();
  $query_args = [
    'post_type' => 'post',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'tax_query' => ['relation' => 'AND'],
  ];

  if (!empty($parameters['categories'])) {
    $query_args['tax_query'][] = ['taxonomy' => 'category', 'field' => 'term_id', 'terms' => array_map('intval', $parameters['categories'])];
  }
  if (!empty($parameters['tags'])) {
    $query_args['tax_query'][] = ['taxonomy' => 'post_tag', 'field' => 'term_id', 'terms' => array_map('intval', $parameters['tags'])];
  }
  if (!empty($parameters['subject'])) {
    $query_args['tax_query'][] = ['taxonomy' => 'subject', 'field' => 'term_id', 'terms' => array_map('intval', $parameters['subject'])];
  }
  if (!empty($parameters['level'])) {
    $query_args['tax_query'][] = ['taxonomy' => 'level', 'field' => 'term_id', 'terms' => array_map('intval', $parameters['level'])];
  }

  $query = new WP_Query($query_args);
  if (!$query->have_posts()) {
    return rest_ensure_response(['success' => false, 'message' => 'No posts found.']);
  }

  $posts = [];
  while ($query->have_posts()) {
    $query->the_post();
    $posts[] = [
      'id' => get_the_ID(),
      'title' => get_the_title(),
      'categories' => wp_list_pluck(get_the_category(get_the_ID()), 'name'),
      'tags' => wp_list_pluck(get_the_tags(get_the_ID()) ?: [], 'name'),
      'subject' => wp_list_pluck(wp_get_object_terms(get_the_ID(), 'subject'), 'name'),
      'level' => wp_list_pluck(wp_get_object_terms(get_the_ID(), 'level'), 'name'),
    ];
  }
  wp_reset_postdata();

  return rest_ensure_response(['success' => true, 'data' => $posts]);
}

// Ensure custom taxonomies are registered for 'subject' and 'level'
add_action('init', function () {
  register_taxonomy('subject', 'post', [
    'label' => 'Subjects',
    'hierarchical' => true,
    'show_ui' => true,
  ]);
  register_taxonomy('level', 'post', [
    'label' => 'Levels',
    'hierarchical' => true,
    'show_ui' => true,
  ]);
});

