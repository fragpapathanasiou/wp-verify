<?php
/*
Plugin Name: WP Verify Twilio Static
Version: 0.9.9
*/
if (!defined('ABSPATH')) exit;

final class WPVTS_Plugin {
    private static $instance;
    private $option_key = 'wpvts_settings';
    private $table_name;

    public static function instance() { if (!self::$instance) self::$instance = new self(); return self::$instance; }

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wpvts_verifications';
        add_action('admin_menu', [$this, 'admin_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('rest_api_init', [$this, 'register_rest']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_shortcode('wpvts_controls', [$this, 'shortcode_controls']);
    }

    private function log($message, array $context = []) {
        $currentUserId = is_user_logged_in() ? get_current_user_id() : 0;
        $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $base = ['userId'=>$currentUserId,'ip'=>$ipAddress,'ua'=>$userAgent];
        error_log('[WPVTS] '.$message.' '.json_encode(array_merge($base, $context)));
    }

    public function admin_page() { add_options_page('WP Verify Twilio Static', 'WP Verify Twilio', 'manage_options', 'wp-verify-twilio-static', [$this, 'render_admin']); }

    public function register_settings() {
        register_setting('wpvts', $this->option_key);
        add_settings_section('wpvts_section', '', '__return_false', 'wpvts');
        add_settings_field('account_sid', 'Twilio Account SID', [$this, 'field_account_sid'], 'wpvts', 'wpvts_section');
        add_settings_field('auth_token', 'Twilio Auth Token', [$this, 'field_auth_token'], 'wpvts', 'wpvts_section');
        add_settings_field('verify_service_sid', 'Twilio Verify Service SID', [$this, 'field_verify_service_sid'], 'wpvts', 'wpvts_section');
    }

    private function get_settings() {
        $defaults = [
            'account_sid' => '',
            'auth_token' => '',
            'verify_service_sid' => '',
            'require_both' => '1'
        ];
        $s = get_option($this->option_key, []);
        return array_merge($defaults, is_array($s) ? $s : []);
    }

    private function twilio_ready() {
        $s = $this->get_settings();
        return trim($s['account_sid'])!=='' && trim($s['auth_token'])!=='' && trim($s['verify_service_sid'])!=='';
    }

    public function field_account_sid() { $s=$this->get_settings(); echo '<input type="text" name="'.$this->option_key.'[account_sid]" value="'.esc_attr($s['account_sid']).'" class="regular-text">'; }
    public function field_auth_token() { $s=$this->get_settings(); echo '<input type="password" name="'.$this->option_key.'[auth_token]" value="'.esc_attr($s['auth_token']).'" class="regular-text">'; }
    public function field_verify_service_sid() { $s=$this->get_settings(); echo '<input type="text" name="'.$this->option_key.'[verify_service_sid]" value="'.esc_attr($s['verify_service_sid']).'" class="regular-text">'; }
    public function render_admin() { echo '<div class="wrap"><h1>WP Verify Twilio Static</h1><form method="post" action="options.php">'; settings_fields('wpvts'); do_settings_sections('wpvts'); submit_button(); echo '</form></div>'; }

    public function enqueue_assets() {
        wp_enqueue_script('wpvts', plugins_url('assets/wpvs.js', __FILE__), [], '0.9.9', true);
        wp_localize_script('wpvts', 'WPVTS', [
            'rest' => '/' . ltrim(parse_url(rest_url('wpvts/v1'), PHP_URL_PATH), '/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'currentUserId' => get_current_user_id(),
            'autoMount' => true,
            'selectors' => [
                'phone' => 'input[name="billing_phone"], input[name="phone"], input[type="tel"], #billing_phone',
                'controls' => '#wpvts-controls',
                'start' => '#wpvts-start-sms',
                'otp' => '#wpvts-otp',
                'verify' => '#wpvts-verify-sms',
                'status' => '#wpvts-status'
            ]
        ]);
    }

    public function shortcode_controls($atts) {
        $candidateId = isset($atts['candidate_id']) ? intval($atts['candidate_id']) : 0;
        $candidateAttr = $candidateId ? ' data-candidate="'.$candidateId.'"' : '';
        $userAttr = is_user_logged_in() ? ' data-user="'.get_current_user_id().'"' : '';
        return '<div id="wpvts-controls"'.$candidateAttr.$userAttr.'>
            <input id="wpvts-phone" type="tel" placeholder="+3069XXXXXXXX">
            <button id="wpvts-start-sms" type="button">Send Code</button>
            <input id="wpvts-otp" type="text" placeholder="Code">
            <button id="wpvts-verify-sms" type="button">Verify</button>
            <span id="wpvts-status"></span>
        </div>';
    }

    public function register_rest() {
        register_rest_route('wpvts/v1', '/start', [
            'methods'  => ['POST','GET'],
            'permission_callback' => '__return_true',
            'callback' => function($request){
                if ($request->get_method()==='GET') {
                    $req = new WP_REST_Request('POST', $request->get_route());
                    foreach (['channel','target','candidate_id','user_id'] as $k) $req->set_param($k, isset($_GET[$k])?$_GET[$k]:null);
                    return $this->rest_start($req);
                }
                return $this->rest_start($request);
            }
        ]);

        register_rest_route('wpvts/v1', '/verify', [
            'methods'  => ['POST','GET'],
            'permission_callback' => '__return_true',
            'callback' => function($request){
                if ($request->get_method()==='GET') {
                    $req = new WP_REST_Request('POST', $request->get_route());
                    foreach (['channel','target','code','otp','token','verification_sid','candidate_id','user_id'] as $k) $req->set_param($k, isset($_GET[$k])?$_GET[$k]:null);
                    return $this->rest_verify($req);
                }
                return $this->rest_verify($request);
            }
        ]);

        register_rest_route('wpvts/v1', '/check', [
            'methods'  => ['POST','GET'],
            'permission_callback' => '__return_true',
            'callback' => function($request){
                if ($request->get_method()==='GET') {
                    $req = new WP_REST_Request('POST', $request->get_route());
                    foreach (['channel','target','code','otp','token','verification_sid','candidate_id','user_id'] as $k) $req->set_param($k, isset($_GET[$k])?$_GET[$k]:null);
                    return $this->rest_verify($req);
                }
                return $this->rest_verify($request);
            }
        ]);

        register_rest_route('wpvts/v1', '/verification/status', [
            'methods'=>'GET',
            'permission_callback'=>function(){ return is_user_logged_in(); },
            'callback'=>function($request){ return $this->rest_verification_status($request); }
        ]);

        register_rest_route('wpvts/v1', '/candidate/enforce-pending', [
            'methods'=>['POST','GET'],
            'permission_callback'=>function(){ return is_user_logged_in(); },
            'callback'=>function($request){
                if ($request->get_method()==='GET') {
                    $req = new WP_REST_Request('POST', $request->get_route());
                    $req->set_param('candidate_id', isset($_GET['candidate_id'])?$_GET['candidate_id']:null);
                    return $this->rest_candidate_enforce_pending($req);
                }
                return $this->rest_candidate_enforce_pending($request);
            }
        ]);
    }

    private function http_post($url, $data, $accountSid, $authToken) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $accountSid.':'.$authToken);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $this->log('HTTP POST', ['url'=>$url,'http'=>$code,'err'=>$err?:'-','payload'=>$data,'body'=>substr((string)$body,0,800)]);
        return [$code>=200 && $code<300, $body, $code, $err];
    }

    private function is_e164($value) { return (bool)preg_match('/^\+[1-9]\d{7,14}$/',(string)$value); }

    private function normalize_msisdn_any($raw) {
        $raw = trim((string)$raw);
        if ($raw==='') return '';
        if ($raw[0]==='+') return preg_replace('/\s+/','',$raw);
        $digits = preg_replace('/\D+/','',$raw);
        if ($digits==='') return '';
        if ((strlen($digits)===10 || strlen($digits)===9) && str_starts_with($digits,'69')) return '+30'.$digits;
        return '+'.$digits;
    }

    private function start_verification($channel, $target) {
        $s = $this->get_settings();
        $sid = trim($s['verify_service_sid']);
        if (!$this->twilio_ready()) return [false,'twilio_settings_missing',null,null];
        $url = 'https://verify.twilio.com/v2/Services/'.$sid.'/Verifications';
        $payload = ['Channel'=>$channel,'To'=>$target];
        return $this->http_post($url, $payload, $s['account_sid'], $s['auth_token']);
    }

    private function check_verification($target, $code, $verificationSid=null) {
        $s = $this->get_settings();
        $sid = trim($s['verify_service_sid']);
        if (!$this->twilio_ready()) return [false,'twilio_settings_missing',null,null];
        $url = 'https://verify.twilio.com/v2/Services/'.$sid.'/VerificationCheck';
        $payload = ['Code'=>$code];
        if ($verificationSid) $payload['VerificationSid'] = $verificationSid; else $payload['To'] = $target;
        return $this->http_post($url, $payload, $s['account_sid'], $s['auth_token']);
    }

    private function read_user_sms_row($userId) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT id,user_id,channel,target,verification_sid,status,created_at,verified_at
             FROM {$this->table_name}
             WHERE user_id=%d AND channel='sms'
             LIMIT 1",$userId
        ),ARRAY_A);
    }

    private function read_user_email_row($userId) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT id,user_id,channel,target,verification_sid,status,created_at,verified_at
             FROM {$this->table_name}
             WHERE user_id=%d AND channel='email'
             LIMIT 1", $userId
        ), ARRAY_A);
    }

    private function read_verification_by_target($channel, $target) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT id,user_id,channel,target,verification_sid,status,created_at,verified_at
             FROM {$this->table_name}
             WHERE channel = %s AND target = %s
             ORDER BY COALESCE(verified_at, created_at) DESC
             LIMIT 1",
            $channel, $target
        ), ARRAY_A);
    }

    private function upsert_user_sms($userId, $target, $verificationSid, $status, $approvedAt) {
        global $wpdb;
        $now = current_time('mysql');
        $row = $this->read_user_sms_row($userId);
        if ($row) {
            $wpdb->update($this->table_name, ['target'=>$target,'verification_sid'=>$verificationSid,'status'=>$status,'verified_at'=>$approvedAt], ['id'=>(int)$row['id']], ['%s','%s','%s','%s'], ['%d']);
            return (int)$row['id'];
        } else {
            $wpdb->insert($this->table_name, ['user_id'=>$userId,'channel'=>'sms','target'=>$target,'verification_sid'=>$verificationSid,'status'=>$status,'created_at'=>$now,'verified_at'=>$approvedAt], ['%d','%s','%s','%s','%s','%s','%s']);
            return (int)$wpdb->insert_id;
        }
    }

    private function upsert_user_email($userId, $target, $verificationSid, $status, $approvedAt) {
        global $wpdb;
        $now = current_time('mysql');
        $existingId = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table_name} WHERE user_id=%d AND channel='email' LIMIT 1",$userId));
        if ($existingId) {
            $wpdb->update($this->table_name, ['target'=>$target,'verification_sid'=>$verificationSid,'status'=>$status,'verified_at'=>$approvedAt], ['id'=>(int)$existingId], ['%s','%s','%s','%s'], ['%d']);
            return (int)$existingId;
        } else {
            $wpdb->insert($this->table_name, ['user_id'=>$userId,'channel'=>'email','target'=>$target,'verification_sid'=>$verificationSid,'status'=>$status,'created_at'=>$now,'verified_at'=>$approvedAt], ['%d','%s','%s','%s','%s','%s','%s']);
            return (int)$wpdb->insert_id;
        }
    }

    private function debug_db_after_write($operation, $recordId) {
        global $wpdb;
        $this->log('db_write', ['operation'=>$operation,'recordId'=>$recordId,'lastError'=>$wpdb->last_error?:null,'lastQuery'=>$wpdb->last_query?:null]);
    }

    private function current_user_id_with_log() {
        $traceId = wp_generate_uuid4();
        $resolved = get_current_user_id();
        $this->log('get_current', ['traceId'=>$traceId,'resolved'=>$resolved]);
        return $resolved;
    }

    private function resolve_user_id($request, $channel, $target) {
        $traceId = wp_generate_uuid4();

        $headerUserId = intval($request->get_header('X-WPVTS-User') ?: 0);
        if ($headerUserId > 0 && get_user_by('ID', $headerUserId)) {
            $this->log('resolve_user_id_header', ['traceId'=>$traceId,'channel'=>$channel,'target'=>$target,'resolved'=>$headerUserId]);
            return $headerUserId;
        }

        $alternateHeaderUserId = intval($request->get_header('X-User-Id') ?: 0);
        if ($alternateHeaderUserId > 0 && get_user_by('ID', $alternateHeaderUserId)) {
            $this->log('resolve_user_id_alt_header', ['traceId'=>$traceId,'channel'=>$channel,'target'=>$target,'resolved'=>$alternateHeaderUserId]);
            return $alternateHeaderUserId;
        }

        $explicitUserId = intval($request->get_param('user_id') ?? 0);
        if ($explicitUserId > 0 && get_user_by('ID', $explicitUserId)) {
            $this->log('resolve_user_id_explicit', ['traceId'=>$traceId,'candidate'=>null,'channel'=>$channel,'target'=>$target,'resolved'=>$explicitUserId]);
            return $explicitUserId;
        }

        $candidateId = intval($request->get_param('candidate_id') ?? 0);
        if ($candidateId) {
            $post = get_post($candidateId);
            if ($post && $post->post_type === 'candidate' && $post->post_author) {
                $resolved = intval($post->post_author);
                $this->log('resolve_user_id_candidate_owner', ['traceId'=>$traceId,'candidate'=>$candidateId,'channel'=>$channel,'target'=>$target,'resolved'=>$resolved]);
                return $resolved;
            }
        }

        if (is_user_logged_in()) {
            $resolved = get_current_user_id();
            $this->log('resolve_user_id_logged_in_get_current', ['traceId'=>$traceId,'channel'=>$channel,'target'=>$target,'resolved'=>$resolved]);
            return $resolved;
        }

        $cookie = wp_parse_auth_cookie('', 'logged_in');
        if ($cookie && isset($cookie['user'])) {
            $resolved = (int)$cookie['user'];
            if ($resolved > 0 && get_user_by('ID', $resolved)) {
                $this->log('resolve_user_id_cookie', ['traceId'=>$traceId,'channel'=>$channel,'target'=>$target,'resolved'=>$resolved]);
                return $resolved;
            }
        }

        if ($channel === 'email' && is_email($target)) {
            $user = get_user_by('email', $target);
            if ($user) {
                $resolved = intval($user->ID);
                $this->log('resolve_user_id_by_email', ['traceId'=>$traceId,'email'=>$target,'resolved'=>$resolved]);
                return $resolved;
            }
        }

        if ($channel === 'sms' && $this->is_e164($target)) {
            $byBilling = get_users(['meta_key'=>'billing_phone','meta_value'=>$target,'number'=>1,'fields'=>'ID']);
            if (!empty($byBilling)) {
                $resolved = intval($byBilling[0]);
                $this->log('resolve_user_id_by_billing_phone', ['traceId'=>$traceId,'phone'=>$target,'resolved'=>$resolved]);
                return $resolved;
            }
            $byPhone = get_users(['meta_key'=>'phone','meta_value'=>$target,'number'=>1,'fields'=>'ID']);
            if (!empty($byPhone)) {
                $resolved = intval($byPhone[0]);
                $this->log('resolve_user_id_by_phone_meta', ['traceId'=>$traceId,'phone'=>$target,'resolved'=>$resolved]);
                return $resolved;
            }
        }

        $this->log('resolve_user_id_failed', ['traceId'=>$traceId,'channel'=>$channel,'target'=>$target,'resolved'=>0]);
        return 0;
    }

    public function rest_start($request) {
        $traceId = wp_generate_uuid4();
        $channel = sanitize_text_field($request->get_param('channel')??'');
        $rawTarget = $request->get_param('target')??'';
        $target = '';
        if ($channel==='sms') { $target=$this->normalize_msisdn_any($rawTarget); if (!$this->is_e164($target)) return new WP_REST_Response(['ok'=>false,'reason'=>'invalid_phone'],200); }
        elseif ($channel==='email') { $target=sanitize_email($rawTarget); if (!is_email($target)) return new WP_REST_Response(['ok'=>false,'reason'=>'invalid_email'],200); }
        else { return new WP_REST_Response(['ok'=>false,'reason'=>'invalid_channel'],200); }
        if (!$this->twilio_ready()) return new WP_REST_Response(['ok'=>false,'reason'=>'twilio_settings_missing'],200);

        [$ok,$responseBody,$httpCode,$curlError] = $this->start_verification($channel,$target);
        $this->log('twilio_start_response', ['traceId'=>$traceId,'ok'=>$ok,'http'=>$httpCode,'err'=>$curlError?:null]);
        if (!$ok) return new WP_REST_Response(['ok'=>false,'http'=>$httpCode,'body'=>$responseBody?:$curlError,'reason'=>$httpCode? 'twilio_http_error':'transport_or_settings'],200);

        $json = json_decode((string)$responseBody,true);
        $verificationSid = is_array($json)&&isset($json['sid'])?(string)$json['sid']:'';
        $resolvedUserId = $this->resolve_user_id($request, $channel, $target);
        $this->log('rest_start_resolved', ['traceId'=>$traceId,'channel'=>$channel,'target'=>$target,'userId'=>$resolvedUserId,'verificationSid'=>$verificationSid?:null]);

       if ($resolvedUserId > 0) {
    if ($channel === 'sms') {
        $recordId = $this->upsert_user_sms($resolvedUserId, $effectiveTo, $finalSid, $finalStatus, $approvedAt);
        $this->debug_db_after_write('upsert_user_sms_verification', $recordId);
    } else {
        $recordId = $this->upsert_user_email($resolvedUserId, $effectiveTo, $finalSid, $finalStatus, $approvedAt);
        $this->debug_db_after_write('upsert_user_email_verification', $recordId);
    }
}


        return new WP_REST_Response(['ok'=>true,'verificationSid'=>$verificationSid,'userId'=>$resolvedUserId ?: null],200);
    }

    public function rest_verify($request) {
        $traceId = wp_generate_uuid4();
        $channel = sanitize_text_field($request->get_param('channel') ?? '');
        $targetParam = $request->get_param('target') ?? '';
        $codeParam = $request->get_param('code');
        $otpParam = $request->get_param('otp');
        if ($codeParam === null || $codeParam === '') $codeParam = $otpParam;
        $tokenParam = $request->get_param('token') ?? '';
        $verificationSidParam = $request->get_param('verification_sid') ?? '';
        $verificationSid = sanitize_text_field($tokenParam ?: $verificationSidParam);

        $uiTarget = '';
        if ($channel === 'sms' && $targetParam) { $n = $this->normalize_msisdn_any($targetParam); if ($this->is_e164($n)) $uiTarget = $n; }
        if ($channel === 'email' && $targetParam) { $e = sanitize_email($targetParam); if (is_email($e)) $uiTarget = $e; }

        if ($codeParam === null || $codeParam === '') return new WP_REST_Response(['ok'=>false,'reason'=>'missing_code'], 200);
        if (!$this->twilio_ready()) return new WP_REST_Response(['ok'=>false,'reason'=>'twilio_settings_missing'], 200);

        $checkTarget = '';
        if (!$verificationSid) {
            if ($channel === 'sms' && !$uiTarget) return new WP_REST_Response(['ok'=>false,'reason'=>'invalid_phone'], 200);
            if ($channel === 'email' && !$uiTarget) return new WP_REST_Response(['ok'=>false,'reason'=>'invalid_email'], 200);
            $checkTarget = $uiTarget;
        }

        [$ok,$responseBody,$httpCode,$curlError] = $this->check_verification($checkTarget, $codeParam, $verificationSid ?: null);
        $this->log('twilio_check_response', ['traceId'=>$traceId,'ok'=>$ok,'http'=>$httpCode,'err'=>$curlError?:null]);
        if (!$ok) return new WP_REST_Response(['ok'=>false,'http'=>$httpCode,'body'=>$responseBody?:$curlError,'reason'=>$httpCode ? 'twilio_http_error' : 'transport_or_settings'], 200);

        $json = json_decode((string)$responseBody, true);
        $finalStatus = is_array($json) && isset($json['status']) ? (string)$json['status'] : 'pending';
        $finalToTwilio = is_array($json) && isset($json['to']) ? (string)$json['to'] : '';
        $finalSid = is_array($json) && isset($json['sid']) ? (string)$json['sid'] : ($verificationSid ?: '');
        $effectiveTo = $uiTarget ?: ($finalToTwilio ?: '');

        $approvedAt = ($finalStatus === 'approved') ? current_time('mysql') : null;

        $resolvedUserId = $this->resolve_user_id($request, $channel, $effectiveTo);
        $this->log('rest_verify_resolved', ['traceId'=>$traceId,'channel'=>$channel,'to'=>$effectiveTo,'status'=>$finalStatus,'userId'=>$resolvedUserId,'verificationSid'=>$finalSid?:null]);

      if ($resolvedUserId > 0) {
    if ($channel === 'sms') {
        $recordId = $this->upsert_user_sms($resolvedUserId, $effectiveTo, $finalSid, $finalStatus, $approvedAt);
        $this->debug_db_after_write('upsert_user_sms_verification', $recordId);
    } else {
        $recordId = $this->upsert_user_email($resolvedUserId, $effectiveTo, $finalSid, $finalStatus, $approvedAt);
        $this->debug_db_after_write('upsert_user_email_verification', $recordId);
    }
}


        return new WP_REST_Response(['ok'=>true,'status'=>$finalStatus,'to'=>$effectiveTo,'userId'=>$resolvedUserId ?: null], 200);
    }

   public function rest_verification_status($request) {
    $resolvedUserId = $this->resolve_user_id($request, 'sms', '');
    if (!$resolvedUserId) $resolvedUserId = $this->current_user_id_with_log();

    $smsStatus = '';
    $smsTo = '';
    $emailStatus = '';
    $emailTo = '';

    $requestedSms = $request->get_param('sms_to') ?: $request->get_param('phone') ?: $request->get_param('target');
    $requestedEmail = $request->get_param('email_to') ?: $request->get_param('email');

    if ($requestedSms) {
        $normalized = $this->normalize_msisdn_any($requestedSms);
        if ($this->is_e164($normalized)) {
            $byPhone = $this->read_verification_by_target('sms', $normalized);
            $smsTo = $normalized;
            $smsStatus = $byPhone ? (string)$byPhone['status'] : 'pending';
        }
    }

    if ($requestedEmail) {
        $sanitized = sanitize_email($requestedEmail);
        if (is_email($sanitized)) {
            $byEmail = $this->read_verification_by_target('email', $sanitized);
            $emailTo = $sanitized;
            $emailStatus = $byEmail ? (string)$byEmail['status'] : 'pending';
        }
    }

    if (!$requestedSms || !$smsTo) {
        if ($resolvedUserId) {
            $row = $this->read_user_sms_row($resolvedUserId);
            if ($row) { $smsStatus = (string)$row['status']; $smsTo = (string)$row['target']; }
        }
    }

    if (!$requestedEmail || !$emailTo) {
        if ($resolvedUserId) {
            $row = $this->read_user_email_row($resolvedUserId);
            if ($row) { $emailStatus = (string)$row['status']; $emailTo = (string)$row['target']; }
        }
    }

    return new WP_REST_Response([
        'ok' => true,
        'smsStatus' => (string)$smsStatus,
        'smsTo' => (string)$smsTo,
        'emailStatus' => (string)$emailStatus,
        'emailTo' => (string)$emailTo,
        'userId' => $resolvedUserId ?: null
    ], 200);
}


    public function rest_candidate_enforce_pending($request) {
        $candidateId = intval($request->get_param('candidate_id'));
        if (!$candidateId) return new WP_REST_Response(['ok'=>false,'reason'=>'missing_candidate_id'],200);
        $post = get_post($candidateId);
        if (!$post || $post->post_type!=='candidate') return new WP_REST_Response(['ok'=>false,'reason'=>'invalid_candidate'],200);
        if (!current_user_can('edit_post',$candidateId)) return new WP_REST_Response(['ok'=>false,'reason'=>'forbidden'],200);

        $resolvedUserId = $this->resolve_user_id($request, 'sms', '');
        if (!$resolvedUserId) $resolvedUserId = $this->current_user_id_with_log();
        if (!$resolvedUserId) return new WP_REST_Response(['ok'=>false,'reason'=>'unauthorized'],200);

        $row = $this->read_user_sms_row($resolvedUserId);
        $isApproved = $row && isset($row['status']) && $row['status']==='approved';
        if ($isApproved) return new WP_REST_Response(['ok'=>true,'candidateId'=>$candidateId,'postStatus'=>get_post_status($candidateId),'action'=>'none','userId'=>$resolvedUserId],200);
        $currentStatus = get_post_status($candidateId);
        if ($currentStatus!=='pending') {
            wp_update_post(['ID'=>$candidateId,'post_status'=>'pending']);
            return new WP_REST_Response(['ok'=>true,'candidateId'=>$candidateId,'postStatus'=>get_post_status($candidateId),'action'=>'set_pending','userId'=>$resolvedUserId],200);
        }
        return new WP_REST_Response(['ok'=>true,'candidateId'=>$candidateId,'postStatus'=>'pending','action'=>'already_pending','userId'=>$resolvedUserId],200);
    }
}
WPVTS_Plugin::instance();
