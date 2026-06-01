<?php

class Ava_Users {
    
    /**
     * Register user-related API endpoints
     */
    public function register_endpoints() {
        register_rest_route('avaita-restro/v1', '/auth/login', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'login_user'),
            'permission_callback' => '__return_true',
            'args' => array(
                'username' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Username or email address',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'password' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'User password',
                ),
            ),
        ));
        
        register_rest_route('avaita-restro/v1', '/auth/validate', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'validate_token'),
            'permission_callback' => '__return_true',
        ));
    }
    
    /**
     * Handle user login
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function login_user($request) {
        $username = $request->get_param('username');
        $password = $request->get_param('password');
        
        // Validate required parameters
        if (empty($username) || empty($password)) {
            return new WP_Error(
                'missing_credentials',
                __('Username and password are required.', 'avaita-restro'),
                array('status' => 400)
            );
        }
        
        // Determine if username is an email
        $user_field = is_email($username) ? 'email' : 'login';
        
        // Attempt to authenticate user
        $user = wp_authenticate($username, $password);
        
        if (is_wp_error($user)) {
            return new WP_Error(
                'authentication_failed',
                __('Invalid username/email or password.', 'avaita-restro'),
                array('status' => 401)
            );
        }
        
        // Check if user account is active
        if (!$user || !($user instanceof WP_User)) {
            return new WP_Error(
                'user_not_found',
                __('User account not found or inactive.', 'avaita-restro'),
                array('status' => 404)
            );
        }
        
        // Generate authentication token (you may want to implement JWT or session tokens)
        $token = $this->generate_auth_token($user);
        
        // Prepare user data response
        $user_data = array(
            'id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'first_name' => get_user_meta($user->ID, 'first_name', true),
            'last_name' => get_user_meta($user->ID, 'last_name', true),
            'roles' => $user->roles,
            'avatar_url' => get_avatar_url($user->ID),
        );
        
        // Log successful login
        do_action('avaita_user_logged_in', $user->ID, $user);
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Login successful.', 'avaita-restro'),
            'data' => array(
                'user' => $user_data,
                'token' => $token,
            ),
        ), 200);
    }
    
    /**
     * Validate authentication token
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function validate_token($request) {
        // Get authorization header
        $authorization_header = $request->get_header('Authorization');
        
        if (empty($authorization_header)) {
            return new WP_Error(
                'missing_token',
                __('Authorization token is required.', 'avaita-restro'),
                array('status' => 401)
            );
        }
        
        // Extract Bearer token
        if (strpos($authorization_header, 'Bearer ') !== 0) {
            return new WP_Error(
                'invalid_token_format',
                __('Invalid token format. Use Bearer token.', 'avaita-restro'),
                array('status' => 401)
            );
        }
        
        $token = substr($authorization_header, 7); // Remove 'Bearer ' prefix
        
        if (empty($token)) {
            return new WP_Error(
                'empty_token',
                __('Token cannot be empty.', 'avaita-restro'),
                array('status' => 401)
            );
        }
        
        // Validate token format and extract data
        $token_parts = explode('.', $token);
        if (count($token_parts) !== 2) {
            return new WP_Error(
                'invalid_token_structure',
                __('Invalid token structure.', 'avaita-restro'),
                array('status' => 401)
            );
        }
        
        $token_data_encoded = $token_parts[0];
        $token_signature = $token_parts[1];
        
        // Decode token data
        $token_data_json = base64_decode($token_data_encoded);
        $token_data = json_decode($token_data_json, true);
        
        if (!$token_data || !isset($token_data['user_id'])) {
            return new WP_Error(
                'invalid_token_data',
                __('Invalid token data.', 'avaita-restro'),
                array('status' => 401)
            );
        }
        
        $user_id = $token_data['user_id'];
        $user = get_user_by('ID', $user_id);
        
        if (!$user) {
            return new WP_Error(
                'user_not_found',
                __('User not found.', 'avaita-restro'),
                array('status' => 404)
            );
        }
        
        // Verify token signature
        $secret_key = defined('AUTH_KEY') ? AUTH_KEY : 'avaita-secret-key';
        $expected_signature = hash_hmac('sha256', $token_data_json, $secret_key);
        
        if (!hash_equals($expected_signature, $token_signature)) {
            return new WP_Error(
                'invalid_token_signature',
                __('Invalid token signature.', 'avaita-restro'),
                array('status' => 401)
            );
        }
        
        // Check token expiry
        if (isset($token_data['expiry']) && time() > $token_data['expiry']) {
            return new WP_Error(
                'token_expired',
                __('Token has expired.', 'avaita-restro'),
                array('status' => 401)
            );
        }
        
        // Verify stored token matches (optional additional security)
        $stored_token = get_user_meta($user_id, 'avaita_auth_token', true);
        $stored_expiry = get_user_meta($user_id, 'avaita_token_expiry', true);
        
        if ($stored_token !== $token || (time() > $stored_expiry)) {
            return new WP_Error(
                'token_mismatch',
                __('Token expired or invalid.', 'avaita-restro'),
                array('status' => 401)
            );
        }
        
        // Prepare user data response (matches login response format)
        $user_data = array(
            'id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'first_name' => get_user_meta($user->ID, 'first_name', true),
            'last_name' => get_user_meta($user->ID, 'last_name', true),
            'roles' => $user->roles,
            'avatar_url' => get_avatar_url($user->ID),
        );
        
        // Fire action for successful token validation
        do_action('avaita_token_validated', $user->ID, $user, $token_data);
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Token is valid.', 'avaita-restro'),
            'data' => array(
                'user' => $user_data,
            ),
        ), 200);
    }
    
    /**
     * Generate authentication token for user
     *
     * @param WP_User $user
     * @return string
     */
    private function generate_auth_token($user) {
        // Simple token generation - you may want to implement JWT or more secure tokens
        $token_data = array(
            'user_id' => $user->ID,
            'username' => $user->user_login,
            'timestamp' => time(),
            'expiry' => time() + (24 * 60 * 60), // 24 hours
        );
        
        // Create a hash of the token data with a secret key
        $secret_key = defined('AUTH_KEY') ? AUTH_KEY : 'avaita-secret-key';
        $token = base64_encode(json_encode($token_data)) . '.' . hash_hmac('sha256', json_encode($token_data), $secret_key);
        
        // Store token in user meta for validation
        update_user_meta($user->ID, 'avaita_auth_token', $token);
        update_user_meta($user->ID, 'avaita_token_expiry', $token_data['expiry']);
        
        return $token;
    }
    
    /**
     * Get instance of the class
     *
     * @return Ava_Users
     */
    public static function get_instance() {
        return new Ava_Users();
    }
}
